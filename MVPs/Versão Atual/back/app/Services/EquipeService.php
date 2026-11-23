<?php

namespace App\Services;

use App\Enums\Permissao;
use App\Models\Agencia;
use App\Models\User;
use App\Policies\EquipePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class EquipeService
{
    public const MAX_TENTATIVAS_PIN = 5;
    public const HORAS_EXPIRACAO_CONVITE = 48;

    protected EquipePolicy $policy;
    protected PlanoQuotaService $quotaService;

    public function __construct(?EquipePolicy $policy = null, ?PlanoQuotaService $quotaService = null)
    {
        $this->policy = $policy ?? new EquipePolicy();
        $this->quotaService = $quotaService ?? new PlanoQuotaService();
    }

    public function obterResumoCotaEquipe(Agencia $agencia): array
    {
        return $this->quotaService->obterResumoCotaEquipe($agencia);
    }

    /**
     * @return array<string, mixed>
     */
    public function listarMembros(User $actor): array
    {
        if (!$this->policy->viewAny($actor)) {
            throw new AuthorizationException('Você não possui permissão para visualizar ou gerenciar a equipe.');
        }

        $agencia = $actor->agencia?->fresh();
        $membros = User::where('agencia_id', $actor->agencia_id)
            ->orderByRaw("CASE WHEN id = ? THEN 0 ELSE 1 END", [$agencia?->owner_id ?? 0])
            ->orderBy('name', 'asc')
            ->get();

        $membrosFormatados = $membros->map(function (User $membro) use ($actor) {
            return $this->formatarMembro($membro, $actor);
        })->values()->all();

        $atorEhOwner = $actor->isOwner();

        return [
            'agencia' => $agencia ? [
                'id' => $agencia->id,
                'nome' => $agencia->nome,
                'owner_id' => $agencia->owner_id,
            ] : null,
            'cota_equipe' => $agencia ? $this->quotaService->obterResumoCotaEquipe($agencia) : null,
            'membros' => $membrosFormatados,
            'catalogo_permissoes' => Permissao::catalogo($atorEhOwner),
            'presets' => array_values(Permissao::presets($atorEhOwner)),
            'ator' => [
                'id' => $actor->id,
                'is_owner' => $atorEhOwner,
                'permissoes' => $actor->permissoesEfetivas(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    public function convidar(User $actor, array $dados): array
    {
        if (!$this->policy->create($actor)) {
            throw new AuthorizationException('Você não possui permissão para convidar novos consultores.');
        }

        $permissoesSolicitadas = $this->normalizarEValidarPermissoes($actor, $dados['permissoes'] ?? []);
        $emailNormalizado = strtolower(trim((string) $dados['email']));
        $nomeLimpo = trim((string) $dados['name']);

        if (User::where('email', $emailNormalizado)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Este endereço de e-mail já está cadastrado na plataforma.'],
            ]);
        }

        $tokenConvite = $this->gerarTokenUnico();
        $pinAtivacao = (string) random_int(100000, 999999);

        [$membro, $agencia] = DB::transaction(function () use (
            $actor,
            $nomeLimpo,
            $emailNormalizado,
            $permissoesSolicitadas,
            $tokenConvite,
            $pinAtivacao
        ) {
            $agencia = Agencia::where('id', $actor->agencia_id)->lockForUpdate()->firstOrFail();
            $this->quotaService->validarLimiteEquipe($agencia, 'convidar');

            $novoMembro = User::create([
                'agencia_id' => $actor->agencia_id,
                'perfil_acesso' => 'consultor_padrao',
                'permissoes' => $permissoesSolicitadas,
                'status' => 'pendente',
                'name' => $nomeLimpo,
                'email' => $emailNormalizado,
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(40)),
                'convite_token' => $tokenConvite,
                'convite_codigo_criptografado' => $pinAtivacao,
                'convite_expira_em' => now()->addHours(self::HORAS_EXPIRACAO_CONVITE),
                'convite_tentativas' => 0,
                'convidado_por_id' => $actor->id,
            ]);

            return [$novoMembro, $agencia];
        });

        $linkConvite = $this->construirLinkConvite($tokenConvite);
        $emailEnviado = $this->dispararEmailConviteBestEffort($membro, $actor, $linkConvite);

        return [
            'membro' => $this->formatarMembro($membro->fresh(), $actor),
            'link_convite' => $linkConvite,
            'codigo_ativacao' => $pinAtivacao,
            'expira_em' => $membro->convite_expira_em?->toIso8601String(),
            'email_enviado' => $emailEnviado,
            'cota_equipe' => $this->quotaService->obterResumoCotaEquipe($agencia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reenviarConvite(User $actor, User $target): array
    {
        if (!$this->policy->manageMember($actor, $target)) {
            throw new AuthorizationException('Você não possui permissão para gerenciar este membro.');
        }

        if (($target->status ?? 'ativo') !== 'pendente') {
            throw ValidationException::withMessages([
                'status' => ['Apenas membros com convite pendente podem receber um novo link de ativação.'],
            ]);
        }

        $novoToken = $this->gerarTokenUnico();
        $novoPin = (string) random_int(100000, 999999);

        $target->convite_token = $novoToken;
        $target->convite_codigo_criptografado = $novoPin;
        $target->convite_expira_em = now()->addHours(self::HORAS_EXPIRACAO_CONVITE);
        $target->convite_tentativas = 0;
        $target->convidado_por_id = $actor->id;
        $target->save();

        $linkConvite = $this->construirLinkConvite($novoToken);
        $emailEnviado = $this->dispararEmailConviteBestEffort($target, $actor, $linkConvite);

        return [
            'membro' => $this->formatarMembro($target->fresh(), $actor),
            'link_convite' => $linkConvite,
            'codigo_ativacao' => $novoPin,
            'expira_em' => $target->convite_expira_em?->toIso8601String(),
            'email_enviado' => $emailEnviado,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function consultarConvitePublico(string $token): array
    {
        $user = User::with('agencia')->where('convite_token', trim($token))->first();

        if (!$user || ($user->status ?? 'ativo') !== 'pendente') {
            throw ValidationException::withMessages([
                'token' => ['Convite inválido, já utilizado ou cancelado.'],
            ]);
        }

        if ($user->convite_expira_em && $user->convite_expira_em->isPast()) {
            $user->convite_token = null;
            $user->convite_codigo_criptografado = null;
            $user->convite_expira_em = null;
            $user->save();

            throw ValidationException::withMessages([
                'token' => ['Este convite expirou. Solicite um novo convite ao titular da consultoria.'],
            ]);
        }

        if ((int) $user->convite_tentativas >= self::MAX_TENTATIVAS_PIN) {
            throw ValidationException::withMessages([
                'token' => ['Este convite foi bloqueado por excesso de tentativas incorretas. Solicite o reenvio ao administrador.'],
            ]);
        }

        return [
            'valido' => true,
            'agencia_nome' => $user->agencia?->nome ?? 'Consultoria Anjo Nexus',
            'convidado_nome' => $user->name,
            'email_mascarado' => $this->mascararEmail($user->email),
            'expira_em' => $user->convite_expira_em?->toIso8601String(),
            'tentativas_restantes' => max(0, self::MAX_TENTATIVAS_PIN - (int) $user->convite_tentativas),
        ];
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function aceitarConvite(array $dados): User
    {
        $token = trim((string) ($dados['token'] ?? ''));
        $codigo = preg_replace('/\D/', '', (string) ($dados['codigo'] ?? ''));
        $password = (string) ($dados['password'] ?? '');

        $user = User::with('agencia')->where('convite_token', $token)->first();

        if (!$user || ($user->status ?? 'ativo') !== 'pendente') {
            throw ValidationException::withMessages([
                'token' => ['Convite inválido, já utilizado ou cancelado.'],
            ]);
        }

        if ($user->convite_expira_em && $user->convite_expira_em->isPast()) {
            $user->convite_token = null;
            $user->convite_codigo_criptografado = null;
            $user->convite_expira_em = null;
            $user->save();

            throw ValidationException::withMessages([
                'token' => ['Este convite expirou (validade de 48 horas). Solicite o reenvio do convite ao titular.'],
            ]);
        }

        if ((int) $user->convite_tentativas >= self::MAX_TENTATIVAS_PIN) {
            throw ValidationException::withMessages([
                'codigo' => ['Convite bloqueado por atingir o limite de 5 tentativas. Solicite um novo convite ao titular.'],
            ]);
        }

        $pinArmazenado = (string) ($user->convite_codigo_criptografado ?? '');

        if ($pinArmazenado === '' || !hash_equals($pinArmazenado, (string) $codigo)) {
            $novasTentativas = ((int) $user->convite_tentativas) + 1;
            $user->convite_tentativas = $novasTentativas;

            if ($novasTentativas >= self::MAX_TENTATIVAS_PIN) {
                $user->convite_token = null;
                $user->convite_codigo_criptografado = null;
                $user->convite_expira_em = null;
                $user->save();

                throw ValidationException::withMessages([
                    'codigo' => ['Código de verificação incorreto. O limite de 5 tentativas foi atingido e o convite foi invalidado por segurança.'],
                ]);
            }

            $user->save();
            $restantes = self::MAX_TENTATIVAS_PIN - $novasTentativas;

            throw ValidationException::withMessages([
                'codigo' => ["Código de verificação de 6 dígitos incorreto. Tentativas restantes: {$restantes}."],
            ]);
        }

        $user->password = Hash::make($password);
        $user->status = 'ativo';
        $user->email_verified_at = now();
        $user->convite_token = null;
        $user->convite_codigo_criptografado = null;
        $user->convite_expira_em = null;
        $user->convite_tentativas = 0;
        $user->save();

        return $user->fresh();
    }

    /**
     * @param array<int, string> $permissoes
     * @return array<string, mixed>
     */
    public function atualizarPermissoes(User $actor, User $target, array $permissoes): array
    {
        if (!$this->policy->manageMember($actor, $target)) {
            throw new AuthorizationException('Você não possui permissão para alterar as permissões deste membro.');
        }

        $permissoesValidadas = $this->normalizarEValidarPermissoes($actor, $permissoes, $target);

        $target->permissoes = $permissoesValidadas;
        $target->save();

        return $this->formatarMembro($target->fresh(), $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function atualizarStatus(User $actor, User $target, string $novoStatus): array
    {
        if (!$this->policy->manageMember($actor, $target)) {
            throw new AuthorizationException('Você não possui permissão para alterar o status deste membro.');
        }

        if (($target->status ?? 'ativo') === 'pendente') {
            throw ValidationException::withMessages([
                'status' => ['Este membro ainda está com convite pendente de ativação.'],
            ]);
        }

        if (!in_array($novoStatus, ['ativo', 'inativo'], true)) {
            throw ValidationException::withMessages([
                'status' => ['O status informado deve ser ativo ou inativo.'],
            ]);
        }

        if ($novoStatus === 'ativo' && $target->status === 'inativo') {
            DB::transaction(function () use ($actor, $target) {
                $agencia = Agencia::where('id', $actor->agencia_id)->lockForUpdate()->firstOrFail();
                $this->quotaService->validarLimiteEquipe($agencia, 'reativar');

                $target->status = 'ativo';
                $target->save();
            });
        } else {
            $target->status = $novoStatus;
            $target->save();

            if ($novoStatus === 'inativo') {
                $this->revogarSessoesETokens($target);
            }
        }

        return $this->formatarMembro($target->fresh(), $actor);
    }

    public function remover(User $actor, User $target): void
    {
        if (!$this->policy->manageMember($actor, $target)) {
            throw new AuthorizationException('Você não possui permissão para remover este membro da equipe.');
        }

        DB::transaction(function () use ($target) {
            $this->revogarSessoesETokens($target);
            DB::table('password_reset_tokens')->where('email', $target->email)->delete();
            $target->delete();
        });
    }

    public function revogarSessoesETokens(User $membro): void
    {
        $membro->tokens()->delete();

        if (class_exists(PersonalAccessToken::class)) {
            PersonalAccessToken::where('tokenable_type', User::class)
                ->where('tokenable_id', $membro->id)
                ->delete();
        }

        DB::table('sessions')->where('user_id', $membro->id)->delete();
    }

    /**
     * @param array<int, mixed> $permissoes
     * @return array<int, string>
     */
    protected function normalizarEValidarPermissoes(User $actor, array $permissoes, ?User $target = null): array
    {
        $listaLimpa = array_values(array_unique(array_filter(
            array_map(fn ($p) => is_string($p) ? trim($p) : '', $permissoes),
            fn ($p) => $p !== ''
        )));

        $valoresConhecidos = Permissao::valores();
        foreach ($listaLimpa as $codigo) {
            if (!in_array($codigo, $valoresConhecidos, true)) {
                throw ValidationException::withMessages([
                    'permissoes' => ["A permissão '{$codigo}' não é válida."],
                ]);
            }
        }

        if (!$this->policy->assignPermissions($actor, $listaLimpa, $target)) {
            throw new AuthorizationException('Você não possui permissão para conceder uma ou mais permissões solicitadas (proibida delegação transitiva ou financeira).');
        }

        return $listaLimpa;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatarMembro(User $membro, ?User $actor = null): array
    {
        $ehOwner = $membro->isOwner();
        $agencia = $membro->agencia;
        if (!$ehOwner && $agencia && $agencia->owner_id !== null && (int) $agencia->owner_id === (int) $membro->id) {
            $ehOwner = true;
        }

        $status = $membro->status ?? 'ativo';
        $permissoesArmazenadas = $ehOwner
            ? Permissao::valores()
            : (is_array($membro->permissoes) ? array_values($membro->permissoes) : $membro->permissoesEfetivas());

        $podeGerenciar = $actor ? $actor->can('manageMember', $membro) : false;

        $conviteValido = $status === 'pendente'
            && !empty($membro->convite_token)
            && !empty($membro->convite_codigo_criptografado)
            && $membro->convite_expira_em !== null
            && $membro->convite_expira_em->isFuture()
            && (int) $membro->convite_tentativas < self::MAX_TENTATIVAS_PIN;

        $exporCredenciaisConvite = $conviteValido && $podeGerenciar;

        $codigoAtivacao = $exporCredenciaisConvite
            ? (string) $membro->convite_codigo_criptografado
            : null;

        $linkConvite = $exporCredenciaisConvite
            ? $this->construirLinkConvite((string) $membro->convite_token)
            : null;

        $conviteExpiradoOuBloqueado = $status === 'pendente' && !$conviteValido;

        return [
            'id' => $membro->id,
            'name' => $membro->name,
            'email' => $membro->email,
            'perfil_acesso' => $membro->perfil_acesso,
            'is_owner' => $ehOwner,
            'status' => $status,
            'permissoes' => $permissoesArmazenadas,
            'permissoes_efetivas' => $membro->permissoesEfetivas(),
            'email_verified_at' => $membro->email_verified_at?->toIso8601String(),
            'convite_expira_em' => $membro->convite_expira_em?->toIso8601String(),
            'convite_expirado' => $conviteExpiradoOuBloqueado,
            'codigo_ativacao' => $codigoAtivacao,
            'link_convite' => $linkConvite,
            'pode_gerenciar' => $podeGerenciar,
            'created_at' => $membro->created_at?->toIso8601String(),
        ];
    }

    protected function gerarTokenUnico(): string
    {
        do {
            $token = Str::random(64);
        } while (User::where('convite_token', $token)->exists());

        return $token;
    }

    protected function construirLinkConvite(string $token): string
    {
        $frontUrl = rtrim((string) config('app.front_url', 'http://localhost:5500'), '/');
        if (str_ends_with($frontUrl, '/paginas')) {
            return $frontUrl . '/aceitar-convite.html?token=' . urlencode($token);
        }

        return $frontUrl . '/paginas/aceitar-convite.html?token=' . urlencode($token);
    }

    protected function dispararEmailConviteBestEffort(User $convidado, User $actor, string $linkConvite): bool
    {
        $nomeAgencia = $actor->agencia?->nome ?? 'sua consultoria no Anjo Nexus';

        $htmlEmail = "
        <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; background-color: #0d0f1a; color: #f8fafc; padding: 32px; border-radius: 12px; border: 1px solid #2e364f;'>
            <h2 style='color: #818cf8; margin-top: 0;'>Convite para Equipe — Anjo Nexus</h2>
            <p style='color: #cbd5e1; line-height: 1.6;'>Olá, <strong>{$convidado->name}</strong>!</p>
            <p style='color: #cbd5e1; line-height: 1.6;'>Você foi convidado por <strong>{$actor->name}</strong> para integrar a equipe da consultoria <strong>{$nomeAgencia}</strong> no <strong>Anjo Nexus</strong>.</p>
            <p style='color: #cbd5e1; line-height: 1.6;'>Para ativar seu acesso e criar sua senha pessoal, clique no botão abaixo e informe o <strong>código de verificação de 6 dígitos</strong> fornecido pelo administrador da consultoria:</p>
            <div style='text-align: center; margin: 28px 0;'>
                <a href='{$linkConvite}' style='background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Ativar Minha Conta</a>
            </div>
            <p style='color: #94a3b8; font-size: 13px; line-height: 1.5;'>Este convite é válido por 48 horas. Caso o botão não funcione, copie e cole o link abaixo no seu navegador:</p>
            <p style='word-break: break-all; color: #818cf8; font-size: 12px;'>{$linkConvite}</p>
            <hr style='border: none; border-top: 1px solid #1e293b; margin: 24px 0;'>
            <p style='color: #64748b; font-size: 12px;'>Por segurança, o código PIN de 6 dígitos nunca é enviado neste e-mail. Solicite-o diretamente ao administrador da consultoria.</p>
        </div>";

        try {
            Mail::html($htmlEmail, function ($mensagem) use ($convidado, $nomeAgencia) {
                $mensagem->to($convidado->email, $convidado->name)
                    ->subject("Convite para a consultoria {$nomeAgencia} - Anjo Nexus");
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Envio de e-mail de convite em best-effort falhou (SMTP indisponível ou não configurado): ' . $e->getMessage(), [
                'email_destino' => $convidado->email,
                'agencia_id' => $convidado->agencia_id,
            ]);

            return false;
        }
    }

    protected function mascararEmail(string $email): string
    {
        $partes = explode('@', $email);
        if (count($partes) !== 2) {
            return $email;
        }

        [$local, $dominio] = $partes;
        $len = strlen($local);

        if ($len <= 2) {
            $localMascarado = substr($local, 0, 1) . '***';
        } else {
            $localMascarado = substr($local, 0, 2) . str_repeat('*', min(5, $len - 2));
        }

        return $localMascarado . '@' . $dominio;
    }
}
