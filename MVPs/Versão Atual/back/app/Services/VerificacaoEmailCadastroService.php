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
    public function enviarCodigo(string $email, ?string $nome = null): array
    {
        $email = strtolower(trim($email));

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
            Mail::to($email)->send(new CodigoVerificacaoCadastroMail($codigo, $nome));

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
        $registro = EmailVerificacaoCadastro::where('email', strtolower(trim($email)))->lockForUpdate()->first();
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
