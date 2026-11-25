<?php

namespace App\Services;

use App\Mail\CodigoVerificacaoCadastroMail;
use App\Models\EmailVerificacaoCadastro;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class VerificacaoEmailCadastroService
{
    public function validarEmailFiltro(string $email): void
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw $this->erro('EMAIL_FORMATO_INVALIDO', 'Informe um endereço de e-mail válido.');
        }

        $partes = explode('@', $email);
        if (count($partes) !== 2) {
            throw $this->erro('EMAIL_FORMATO_INVALIDO', 'Informe um endereço de e-mail válido.');
        }

        [$usuario, $dominio] = $partes;
        $usuario = trim($usuario);
        $dominio = trim($dominio);

        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $dominio) || str_contains($dominio, '..')) {
            throw $this->erro('DOMINIO_EMAIL_INVALIDO', 'O domínio do e-mail é inválido.');
        }

        $dominiosProibidos = [
            'teste.com', 'test.com', 'teste.com.br', 'test.com.br',
            'exemplo.com', 'example.com', 'example.org', 'example.net',
            'fake.com', 'fakedoamin.com', 'emailfake.com', 'naoexiste.com',
            'mailinator.com', 'yopmail.com', '10minutemail.com', 'tempmail.com',
            'temp-mail.org', 'guerrillamail.com', 'sharklasers.com', 'trashmail.com',
            'throwawaymail.com', 'dispostable.com', 'fakeinbox.com', 'mohmal.com',
            'crazymailing.com', 'getairmail.com', 'generator.email', 'nada.ltd',
            'burnermail.io', 'dropmail.me', 'fakemailgenerator.com'
        ];

        if (in_array($dominio, $dominiosProibidos, true)) {
            throw $this->erro('EMAIL_TEMPORARIO_OU_INVALIDO', 'E-mails temporários, fictícios ou de teste não são permitidos.');
        }

        if (in_array($usuario, ['teste', 'test', 'fake', 'asdf', 'naoexiste'], true) && in_array($dominio, ['email.com', 'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'], true)) {
            throw $this->erro('EMAIL_TEMPORARIO_OU_INVALIDO', 'Informe um e-mail válido para receber o código.');
        }

        if (!app()->runningUnitTests() && function_exists('checkdnsrr')) {
            $temMx = @checkdnsrr($dominio, 'MX');
            $temA = $temMx ? true : @checkdnsrr($dominio, 'A');
            if (!$temMx && !$temA) {
                throw $this->erro('DOMINIO_EMAIL_SEM_DNS', 'O domínio informado não possui servidores ativos para receber e-mails.');
            }
        }
    }

    public function enviarCodigo(string $email, ?string $nome = null): array
    {
        $email = strtolower(trim($email));
        $this->validarEmailFiltro($email);

        return DB::transaction(function () use ($email, $nome) {
            DB::table('email_verificacoes_cadastro')->upsert([
                ['email' => $email, 'created_at' => now(), 'updated_at' => now()],
            ], ['email'], ['email']);
            $registro = EmailVerificacaoCadastro::where('email', $email)->lockForUpdate()->firstOrFail();

            if (User::where('email', $email)->lockForUpdate()->first()) {
                throw $this->erro('EMAIL_JA_CADASTRADO', 'Este e-mail já está cadastrado. Faça login para continuar.');
            }

            $restante = $registro->ultimo_envio_em?->timestamp + 60 - now()->timestamp;
            if ($restante > 0) {
                throw $this->erro('AGUARDE_REENVIO', 'Aguarde para solicitar outro código.', 429, ['segundos_restantes' => $restante]);
            }

            do {
                $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while ($registro->codigo_hash && Hash::check($codigo, $registro->codigo_hash));
            $registro->update([
                'codigo_hash' => Hash::make($codigo),
                'codigo_expira_em' => now()->addMinutes(15),
                'ultimo_envio_em' => now(),
                'tentativas' => 0,
                'verification_token_hash' => null,
                'token_expira_em' => null,
                'verificado_em' => null,
                'consumido_em' => null,
            ]);

            try {
                Mail::to($email)->send(new CodigoVerificacaoCadastroMail($codigo, $nome));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao despachar e-mail de verificação para {$email}: " . $e->getMessage());
                if (!app()->environment('local') && !config('app.debug')) {
                    throw $e;
                }
            }

            \Illuminate\Support\Facades\Log::info("Código de verificação gerado para {$email}: {$codigo}");

            return ['expira_em_segundos' => 900, 'reenvio_em_segundos' => 60];
        });
    }

    public function verificarCodigo(string $email, string $codigo): array
    {
        $resultado = DB::transaction(function () use ($email, $codigo) {
            $registro = EmailVerificacaoCadastro::where('email', strtolower(trim($email)))->lockForUpdate()->first();

            if ($registro && $registro->tentativas >= 5) {
                return $this->erro('LIMITE_TENTATIVAS_EXCEDIDO', 'Limite de tentativas atingido. Solicite outro código.', 429);
            }
            if (!$registro?->codigo_hash) {
                return $this->erro('CODIGO_INVALIDO', 'Código inválido. Confira o código recebido.');
            }
            if (!$registro->codigo_expira_em?->isFuture()) {
                return $this->erro('CODIGO_EXPIRADO', 'Código expirado. Solicite outro código.');
            }
            if (!Hash::check($codigo, $registro->codigo_hash)) {
                $registro->tentativas++;
                if ($registro->tentativas >= 5) {
                    $registro->codigo_hash = null;
                }
                $registro->save();

                return $this->erro('CODIGO_INVALIDO', 'Código incorreto.', 422, ['tentativas_restantes' => 5 - $registro->tentativas]);
            }

            $token = bin2hex(random_bytes(32));
            $registro->update([
                'verification_token_hash' => hash('sha256', $token),
                'token_expira_em' => now()->addHour(),
                'verificado_em' => now(),
                'codigo_hash' => null,
                'codigo_expira_em' => null,
            ]);

            return ['verification_token' => $token, 'expira_em_segundos' => 3600];
        });

        if ($resultado instanceof HttpResponseException) {
            throw $resultado;
        }

        return $resultado;
    }

    public function validarEConsumirToken(string $email, string $verificationToken): void
    {
        $email = strtolower(trim($email));
        $this->validarEmailFiltro($email);
        $registro = EmailVerificacaoCadastro::where('email', $email)->lockForUpdate()->first();
        if (!$registro?->verification_token_hash || $registro->consumido_em || !$registro->token_expira_em?->isFuture()
            || !hash_equals($registro->verification_token_hash, hash('sha256', $verificationToken))) {
            throw $this->erro('VERIFICACAO_EMAIL_INVALIDA', 'Confirme seu e-mail novamente para concluir o cadastro.');
        }

        $registro->update(['consumido_em' => now(), 'verification_token_hash' => null]);
    }

    private function erro(string $codigo, string $mensagem, int $status = 422, array $detalhes = []): HttpResponseException
    {
        return new HttpResponseException(response()->json(['codigo_erro' => $codigo, 'message' => $mensagem] + $detalhes, $status));
    }
}
