<?php

namespace App\Http\Controllers;

use App\Adapters\AbacatePayAdapter;
use App\Services\RegistroService;
use App\Services\AbacatePayService;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
use App\Models\Startup;
use App\Models\Minuta;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\KanbanCard;
use App\Models\EditalFavorito;
use App\Models\AlertaMatch;
use App\Models\AlertaPrazo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class AuthController extends Controller
{
    protected RegistroService $registroService;
    protected AbacatePayAdapter $abacatePayAdapter;

    public function __construct(RegistroService $registroService, ?AbacatePayAdapter $abacatePayAdapter = null)
    {
        $this->registroService = $registroService;
        $this->abacatePayAdapter = $abacatePayAdapter ?? new AbacatePayAdapter();
    }

    /**
     * 1. Valida se o e-mail já está em uso antes da escolha do plano
     * POST /api/auth/verificar-email
     */
    public function verificarEmail(Request $requisicao): JsonResponse
    {
        $requisicao->validate([
            'email' => 'required|string|email|max:255',
        ], [
            'email.required' => 'Informe o seu endereço de e-mail.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        $usuarioExistente = User::with('agencia')->where('email', strtolower(trim((string) $requisicao->email)))->first();

        if ($usuarioExistente) {
            $agencia = $usuarioExistente->agencia;
            $pagamentoPendente = $agencia && !$agencia->isPagamentoAtivo();

            return response()->json([
                'disponivel' => false,
                'pagamento_pendente' => (bool) $pagamentoPendente,
                'message' => $pagamentoPendente
                    ? 'Este e-mail já possui um cadastro com pagamento pendente. Conclua o pagamento para liberar seu acesso.'
                    : 'Este e-mail já está cadastrado. Faça login para acessar ou gerenciar sua conta.',
            ], 422);
        }

        return response()->json([
            'disponivel' => true,
            'message' => 'E-mail disponível para cadastro.',
        ], 200);
    }

    /**
     * 2. Registra o Usuário e a Agência vinculados ao plano e gera cobrança no AbacatePay
     * POST /api/auth/registrar-com-plano
     */
    public function registrarComPlano(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $emailNormalizado = strtolower(trim((string) $requisicao->input('email')));
        $usuarioExistente = $emailNormalizado ? User::with('agencia')->where('email', $emailNormalizado)->first() : null;

        if ($usuarioExistente && $usuarioExistente->agencia && $usuarioExistente->agencia->isPagamentoAtivo()) {
            return response()->json([
                'message' => 'Este e-mail já está cadastrado e possui assinatura ativa. Faça login para acessar sua conta.',
            ], 422);
        }

        $regraEmail = $usuarioExistente
            ? 'required|string|email|max:255'
            : 'required|string|email|max:255|unique:users,email';

        $dadosValidados = $requisicao->validate([
            'name' => 'required|string|max:255',
            'email' => $regraEmail,
            'password' => 'required|string|min:8',
            'plano_id' => 'required|exists:planos,id',
            'nome_agencia' => 'required|string|max:255',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado. Faça login para acessar sua conta.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve conter no mínimo 8 caracteres.',
            'plano_id.required' => 'Por favor, selecione um plano.',
            'plano_id.exists' => 'O plano selecionado não foi encontrado.',
            'nome_agencia.required' => 'O nome da agência/consultoria é obrigatório.',
        ]);

        DB::beginTransaction();

        try {
            $plano = Plano::findOrFail($dadosValidados['plano_id']);
            $nomeAgencia = trim($dadosValidados['nome_agencia']);

            if ($usuarioExistente) {
                $usuario = $usuarioExistente;
                $usuario->name = $dadosValidados['name'];
                $usuario->password = Hash::make($dadosValidados['password']);
                $usuario->save();

                $agencia = $usuario->agencia;
                if (!$agencia) {
                    $agencia = Agencia::create([
                        'nome' => $nomeAgencia,
                        'plano_id' => $plano->id,
                        'status_pagamento' => 'pendente',
                        'pagamento_expira_em' => Carbon::now()->addMinutes(60),
                    ]);
                    $usuario->agencia_id = $agencia->id;
                    $usuario->save();
                } else {
                    $agencia->nome = $nomeAgencia;
                    $agencia->plano_id = $plano->id;
                    $agencia->status_pagamento = 'pendente';
                    $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);
                    $agencia->save();
                }
            } else {
                $agencia = Agencia::create([
                    'nome' => $nomeAgencia,
                    'plano_id' => $plano->id,
                    'status_pagamento' => 'pendente',
                    'pagamento_expira_em' => Carbon::now()->addMinutes(60),
                ]);

                $usuario = User::create([
                    'name' => $dadosValidados['name'],
                    'email' => strtolower(trim($dadosValidados['email'])),
                    'password' => Hash::make($dadosValidados['password']),
                    'agencia_id' => $agencia->id,
                    'perfil_acesso' => 'consultor_admin',
                ]);
            }

            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $dadosCobranca = $this->abacatePayAdapter->normalizarRetornoCobranca($cobranca);
            $urlCheckout = $dadosCobranca['url_checkout'];
            $idTransacao = $dadosCobranca['id_transacao'];

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            $agencia->url_checkout = $urlCheckout;
            $agencia->id_transacao = $idTransacao;
            $agencia->save();

            $tokenPendencia = $usuario->createToken('token_pendencia', ['pagamento:gerenciar'])->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => 'Conta criada com pagamento pendente. Redirecionando para o pagamento...',
                'url_checkout' => $urlCheckout,
                'token_pendencia' => $tokenPendencia,
                'access_token' => $tokenPendencia,
                'token' => $tokenPendencia,
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                ],
                'agencia' => [
                    'id' => $agencia->id,
                    'nome' => $agencia->nome,
                    'status_pagamento' => $agencia->status_pagamento,
                    'url_checkout' => $agencia->url_checkout,
                    'pagamento_expira_em' => $agencia->pagamento_expira_em,
                ],
            ], 201);

        } catch (Exception $excecao) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erro ao processar cadastro e geração de cobrança: ' . $excecao->getMessage(),
            ], 500);
        }
    }

    /**
     * Cadastro inicial de agência e usuário
     * POST /api/auth/registro
     */
    public function registrar(Request $requisicao): JsonResponse
    {
        $dadosValidados = $requisicao->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'nome_agencia' => 'required|string|max:255',
        ], [
            'nome_agencia.required' => 'O nome da agência/consultoria é obrigatório.',
        ]);

        $resultado = $this->registroService->registrarNovaAgenciaEUsuario($dadosValidados);

        return response()->json([
            'message' => 'Cadastro inicial realizado. Selecione o seu plano.',
            'token' => $resultado['token'],
            'usuario' => $resultado['usuario'],
            'agencia' => $resultado['agencia'],
        ], 201);
    }

    /**
     * 3. Login com verificação do pagamento da assinatura
     * POST /api/auth/login
     */
    public function login(Request $requisicao): JsonResponse
    {
        $validacao = $requisicao->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'password.required' => 'A senha é obrigatória.',
        ]);

        $usuario = User::with('agencia.plano')->where('email', $validacao['email'])->first();

        if (!$usuario || !Hash::check($validacao['password'], $usuario->password)) {
            return response()->json([
                'status' => 'erro',
                'message' => 'Credenciais incorretas. Verifique seu e-mail e senha.',
            ], 401);
        }

        $agencia = $usuario->agencia;

        if ($agencia && !$agencia->isPagamentoAtivo()) {
            $tokenPendencia = $usuario->createToken('token_pendencia', ['pagamento:gerenciar'])->plainTextToken;

            return response()->json([
                'codigo' => 'PAGAMENTO_PENDENTE',
                'codigo_erro' => 'PAGAMENTO_PENDENTE',
                'message' => 'Seu cadastro possui um pagamento pendente.',
                'access_token' => $tokenPendencia,
                'token' => $tokenPendencia,
                'token_pendencia' => $tokenPendencia,
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                ],
                'agencia' => [
                    'id' => $agencia->id,
                    'nome' => $agencia->nome,
                    'status_pagamento' => $agencia->status_pagamento,
                    'url_checkout' => $agencia->url_checkout,
                    'pagamento_expira_em' => $agencia->pagamento_expira_em,
                ],
            ], 403);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'sucesso',
            'message' => 'Login realizado com sucesso!',
            'token' => $token,
            'access_token' => $token,
            'dados' => [
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'perfil_acesso' => $usuario->perfil_acesso,
                ],
                'agencia' => $agencia ? [
                    'id' => $agencia->id,
                    'nome' => $agencia->nome,
                    'status_pagamento' => $agencia->status_pagamento,
                ] : null,
            ],
        ], 200);
    }

    /**
     * Resolve o usuário com pagamento pendente priorizando o e-mail da conta pendente exibida na tela,
     * ou via Bearer token / Sanctum guard.
     */
    protected function resolverUsuarioPendente(Request $requisicao): ?User
    {
        $email = $requisicao->input('email')
            ?: $requisicao->query('email')
            ?: $requisicao->header('X-Pending-Email');

        if (!empty($email)) {
            $usuarioPorEmail = User::with('agencia')->where('email', strtolower(trim($email)))->first();
            if ($usuarioPorEmail) {
                return $usuarioPorEmail;
            }
        }

        $tokenBruto = $requisicao->bearerToken()
            ?: $requisicao->header('X-Pending-Token')
            ?: $requisicao->input('token_pendencia')
            ?: $requisicao->query('token_pendencia');

        if (!empty($tokenBruto) && class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenBruto);
            if ($tokenModel && $tokenModel->tokenable instanceof User) {
                return $tokenModel->tokenable;
            }
        }

        $usuario = $requisicao->user('sanctum') ?? $requisicao->user();
        if ($usuario) {
            return $usuario;
        }

        return null;
    }

    /**
     * 4. Consulta a janela de pagamento anterior
     * GET /api/pagamentos/consultar-anterior
     */
    public function consultarPagamentoAnterior(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $usuario = $this->resolverUsuarioPendente($requisicao);
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$usuario || !$agencia) {
            return response()->json(['message' => 'Agência não vinculada a este usuário.'], 404);
        }

        if ($agencia->isPagamentoAtivo()) {
            return response()->json([
                'expirado' => false,
                'pago' => true,
                'message' => 'O pagamento já foi confirmado!',
            ], 200);
        }

        if (!empty($agencia->id_transacao)) {
            $statusGateway = $servicoAbacatePay->consultarStatusCobranca($agencia->id_transacao);
            if ($this->abacatePayAdapter->traduzirStatus($statusGateway) === 'ativo') {
                $agencia->status_pagamento = 'ativo';
                $agencia->assinatura_expira_em = now()->addDays(30);
                $agencia->save();

                return response()->json([
                    'expirado' => false,
                    'pago' => true,
                    'message' => 'O pagamento foi confirmado com sucesso!',
                ], 200);
            }
        }

        $urlCheckout = $agencia->url_checkout;
        $expirado = $servicoAbacatePay->estaExpirado($agencia);

        if ($expirado || empty($urlCheckout)) {
            return response()->json([
                'expirado' => true,
                'message' => "O pagamento anterior expirou! Por favor, clique na opção 'Gerar uma nova cobrança' para escolher um plano novamente.",
            ], 400);
        }

        return response()->json([
            'expirado' => false,
            'url_checkout' => $urlCheckout,
            'message' => 'Janela de pagamento ainda válida.',
        ], 200);
    }

    /**
     * 5. Gera uma nova cobrança no AbacatePay para conta pendente
     * POST /api/planos/gerar-nova-cobranca
     */
    public function gerarNovaCobranca(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $requisicao->validate([
            'plano_id' => 'required|exists:planos,id',
        ], [
            'plano_id.required' => 'Por favor, selecione um plano.',
            'plano_id.exists' => 'O plano selecionado não existe.',
        ]);

        $usuario = $this->resolverUsuarioPendente($requisicao);
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$usuario || !$agencia) {
            return response()->json(['message' => 'Agência não encontrada para este usuário.'], 404);
        }

        $plano = Plano::findOrFail($requisicao->plano_id);

        try {
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $dadosCobranca = $this->abacatePayAdapter->normalizarRetornoCobranca($cobranca);
            $urlCheckout = $dadosCobranca['url_checkout'];
            $idTransacao = $dadosCobranca['id_transacao'];

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            $agencia->plano_id = $plano->id;
            $agencia->url_checkout = $urlCheckout;
            $agencia->id_transacao = $idTransacao;
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);
            $agencia->status_pagamento = 'pendente';
            $agencia->save();

            return response()->json([
                'url_checkout' => $urlCheckout,
                'message' => 'Nova cobrança gerada com sucesso.',
            ], 200);

        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao gerar nova cobrança: ' . $excecao->getMessage(),
            ], 500);
        }
    }

    /**
     * 6. Cancela o pagamento pendente e remove permanentemente a conta (Hard Delete)
     * DELETE /api/auth/cancelar-cadastro-pendente
     */
    public function cancelarCadastroPendente(Request $requisicao): JsonResponse
    {
        $usuario = $this->resolverUsuarioPendente($requisicao);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $agencia = $usuario->agencia;

        if ($agencia && $agencia->isPagamentoAtivo()) {
            return response()->json([
                'message' => 'Não é permitido cancelar uma conta com assinatura ativa.',
            ], 400);
        }

        try {
            DB::transaction(function () use ($usuario, $agencia) {
                $emailUsuario = $usuario->email;
                $idsUsuarios = [$usuario->id];

                if ($agencia) {
                    $idsDaAgencia = User::where('agencia_id', $agencia->id)->pluck('id')->toArray();
                    $idsUsuarios = array_values(array_unique(array_merge($idsUsuarios, $idsDaAgencia)));
                }

                if (class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
                    \Laravel\Sanctum\PersonalAccessToken::where('tokenable_type', User::class)
                        ->whereIn('tokenable_id', $idsUsuarios)
                        ->delete();
                } else {
                    $usuario->tokens()->delete();
                }

                DB::table('password_reset_tokens')->where('email', $emailUsuario)->delete();
                DB::table('sessions')->whereIn('user_id', $idsUsuarios)->delete();

                if ($agencia) {
                    $agencia->startups()->delete();
                    User::whereIn('id', $idsUsuarios)->forceDelete();
                    $agencia->forceDelete();
                } else {
                    User::where('id', $usuario->id)->forceDelete();
                }
            });

            return response()->json([
                'message' => 'Pagamento cancelado e conta excluída com sucesso.',
            ], 200);

        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao cancelar cadastro e excluir dados: ' . $excecao->getMessage(),
            ], 500);
        }
    }

    /**
     * Solicita a recuperação de senha (apenas para e-mails já cadastrados no sistema).
     */
    public function solicitarRecuperacaoSenha(Request $requisicao): JsonResponse
    {
        $requisicao->validate([
            'email' => 'required|string|email|max:255',
        ], [
            'email.required' => 'Por favor, informe o seu endereço de e-mail.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        $email = strtolower(trim($requisicao->email));
        $usuario = User::where('email', $email)->first();

        if (!$usuario) {
            return response()->json([
                'message' => 'Nenhuma conta foi encontrada com este endereço de e-mail. Verifique o e-mail digitado ou crie uma conta.',
            ], 404);
        }

        $tokenPuro = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $usuario->email],
            [
                'token' => Hash::make($tokenPuro),
                'created_at' => Carbon::now(),
            ]
        );

        $urlOrigemFront = $requisicao->header('X-Frontend-Url') ?: $requisicao->header('Referer');
        if ($urlOrigemFront && str_contains($urlOrigemFront, '/paginas/')) {
            $urlBasePaginas = substr($urlOrigemFront, 0, strpos($urlOrigemFront, '/paginas/') + 9);
        } else {
            $urlBasePaginas = 'http://127.0.0.1:5500/MVPs/Vers%C3%A3o%20Atual/front/paginas/';
        }

        $linkRedefinicao = $urlBasePaginas . 'redefinir-senha.html?token=' . urlencode($tokenPuro) . '&email=' . urlencode($usuario->email);

        $htmlEmail = "
        <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; background-color: #0d0f1a; color: #f8fafc; padding: 32px; border-radius: 12px; border: 1px solid #2e364f;'>
            <h2 style='color: #818cf8; margin-top: 0;'>Recuperação de Senha — Anjo Nexus</h2>
            <p style='color: #cbd5e1; line-height: 1.6;'>Olá, <strong>{$usuario->name}</strong>!</p>
            <p style='color: #cbd5e1; line-height: 1.6;'>Recebemos uma solicitação para redefinir a senha da sua conta no <strong>Anjo Nexus</strong>. Clique no botão abaixo para criar uma nova senha:</p>
            <div style='text-align: center; margin: 28px 0;'>
                <a href='{$linkRedefinicao}' style='background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Redefinir Minha Senha</a>
            </div>
            <p style='color: #94a3b8; font-size: 13px; line-height: 1.5;'>Este link expira em 60 minutos. Caso o botão não funcione, copie e cole o endereço abaixo no seu navegador:</p>
            <p style='word-break: break-all; color: #818cf8; font-size: 12px;'>{$linkRedefinicao}</p>
            <hr style='border: none; border-top: 1px solid #1e293b; margin: 24px 0;'>
            <p style='color: #64748b; font-size: 12px;'>Se você não solicitou esta alteração, ignore este e-mail com segurança.</p>
        </div>";

        try {
            Mail::html($htmlEmail, function ($mensagem) use ($usuario) {
                $mensagem->to($usuario->email, $usuario->name)
                         ->subject('Recuperação de Senha - Anjo Nexus');
            });
        } catch (\Exception $erro) {
            return response()->json([
                'message' => 'Falha ao enviar o e-mail de recuperação: ' . $erro->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Enviamos um link de recuperação para o seu e-mail! Verifique sua caixa de entrada e o spam.',
        ], 200);
    }

    /**
     * Valida o token recebido e redefine a senha do usuário no banco de dados.
     */
    public function redefinirSenha(Request $requisicao): JsonResponse
    {
        $requisicao->validate([
            'email' => 'required|string|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.min' => 'A nova senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não coincide.',
        ]);

        $email = strtolower(trim($requisicao->email));

        $usuario = User::where('email', $email)->first();
        if (!$usuario) {
            return response()->json([
                'message' => 'Conta de usuário não encontrada.',
            ], 404);
        }

        $registroToken = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$registroToken) {
            return response()->json([
                'message' => 'Solicitação de redefinição inválida ou já utilizada. Solicite um novo link.',
            ], 400);
        }

        if (Carbon::parse($registroToken->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'O link de recuperação expirou (validade de 60 minutos). Solicite um novo link.',
            ], 400);
        }

        if (!Hash::check($requisicao->token, $registroToken->token)) {
            return response()->json([
                'message' => 'Token de recuperação inválido. Verifique se abriu o último e-mail enviado.',
            ], 400);
        }

        $usuario->password = Hash::make($requisicao->password);
        $usuario->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'message' => 'Sua senha foi redefinida com sucesso! Redirecionando para o login...',
        ], 200);
    }

    /**
     * Retorna os dados de cadastro do Consultor autenticado
     * GET /api/auth/perfil
     */
    public function obterPerfil(Request $requisicao): JsonResponse
    {
        $usuario = $requisicao->user();

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        return response()->json([
            'status' => 'sucesso',
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'perfil_acesso' => $usuario->perfil_acesso,
            ],
        ], 200);
    }

    /**
     * Atualiza as informações de cadastro do Consultor autenticado (nome, email, senha)
     * PUT /api/auth/perfil
     */
    public function atualizarPerfil(Request $requisicao): JsonResponse
    {
        $usuario = $requisicao->user();

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $dadosValidados = $requisicao->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'password' => 'nullable|string|min:8',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso por outra conta.',
            'password.min' => 'A nova senha deve conter no mínimo 8 caracteres.',
        ]);

        $usuario->name = trim($dadosValidados['name']);
        $usuario->email = strtolower(trim($dadosValidados['email']));

        if (!empty($dadosValidados['password'])) {
            $usuario->password = Hash::make($dadosValidados['password']);
        }

        $usuario->save();

        return response()->json([
            'status' => 'sucesso',
            'message' => 'Perfil atualizado com sucesso!',
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'perfil_acesso' => $usuario->perfil_acesso,
            ],
        ], 200);
    }

    /**
     * Encerra a conta da consultoria com reautenticação por senha e aceite LGPD (EDTAN-158)
     * DELETE|POST /api/conta/cancelar
     */
    public function cancelarConta(Request $requisicao): JsonResponse
    {
        $usuario = $requisicao->user();

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        if ($usuario->perfil_acesso !== 'consultor_admin') {
            return response()->json([
                'message' => 'Apenas o administrador da consultoria pode encerrar a conta.',
            ], 403);
        }

        $requisicao->validate([
            'password' => 'required|string',
            'ciente_lgpd' => 'required|accepted',
        ], [
            'password.required' => 'Informe sua senha atual para confirmar o cancelamento.',
            'ciente_lgpd.required' => 'Você deve confirmar ciência sobre a exclusão irreversível de dados conforme a LGPD.',
            'ciente_lgpd.accepted' => 'Você deve confirmar ciência sobre a exclusão irreversível de dados conforme a LGPD.',
        ]);

        if (!Hash::check($requisicao->password, $usuario->password)) {
            return response()->json([
                'message' => 'A senha informada está incorreta.',
                'errors' => [
                    'password' => ['A senha informada está incorreta.'],
                ],
            ], 422);
        }

        try {
            DB::transaction(function () use ($usuario) {
                $agencia = $usuario->agencia;

                $idsUsuarios = [$usuario->id];
                if ($agencia) {
                    $idsDaAgencia = User::where('agencia_id', $agencia->id)->pluck('id')->toArray();
                    $idsUsuarios = array_values(array_unique(array_merge($idsUsuarios, $idsDaAgencia)));
                }

                $usuariosParaExcluir = User::whereIn('id', $idsUsuarios)->get();
                $emailsUsuarios = $usuariosParaExcluir->pluck('email')->filter()->toArray();

                foreach ($usuariosParaExcluir as $membro) {
                    $membro->tokens()->delete();
                }

                if (class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
                    \Laravel\Sanctum\PersonalAccessToken::where('tokenable_type', User::class)
                        ->whereIn('tokenable_id', $idsUsuarios)
                        ->delete();
                }

                $idsStartups = $agencia
                    ? Startup::where('agencia_id', $agencia->id)->pluck('id')->toArray()
                    : [];

                $idsBoards = $agencia
                    ? KanbanBoard::where('agencia_id', $agencia->id)->pluck('id')->toArray()
                    : [];

                $idsColumns = !empty($idsBoards)
                    ? KanbanColumn::whereIn('board_id', $idsBoards)->pluck('id')->toArray()
                    : [];

                $idsCards = (!empty($idsBoards) || !empty($idsColumns))
                    ? KanbanCard::where(function ($query) use ($idsBoards, $idsColumns) {
                        if (!empty($idsBoards)) {
                            $query->whereIn('board_id', $idsBoards);
                        }
                        if (!empty($idsColumns)) {
                            $query->orWhereIn('column_id', $idsColumns);
                        }
                    })->pluck('id')->toArray()
                    : [];

                // 1. Minutas
                Minuta::where(function ($query) use ($idsStartups, $idsUsuarios, $idsCards) {
                    $query->whereIn('user_id', $idsUsuarios);
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                    if (!empty($idsCards)) {
                        $query->orWhereIn('kanban_card_id', $idsCards);
                    }
                })->delete();

                // 2. Alertas e Notificações
                AlertaPrazo::where(function ($query) use ($idsUsuarios, $idsStartups, $idsCards) {
                    $query->whereIn('user_id', $idsUsuarios);
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                    if (!empty($idsCards)) {
                        $query->orWhereIn('kanban_card_id', $idsCards);
                    }
                })->delete();

                AlertaMatch::where(function ($query) use ($idsUsuarios, $idsStartups) {
                    $query->whereIn('user_id', $idsUsuarios);
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                })->delete();

                // 3. Favoritos
                EditalFavorito::where(function ($query) use ($idsUsuarios, $idsStartups) {
                    $query->whereIn('user_id', $idsUsuarios);
                    if (!empty($idsStartups)) {
                        $query->orWhereIn('startup_id', $idsStartups);
                    }
                })->delete();

                // 4. Kanban (Cards, Colunas e Boards)
                if (!empty($idsCards)) {
                    KanbanCard::whereIn('id', $idsCards)->delete();
                }
                if (!empty($idsColumns)) {
                    KanbanColumn::whereIn('id', $idsColumns)->delete();
                }
                if (!empty($idsBoards)) {
                    KanbanBoard::whereIn('id', $idsBoards)->delete();
                }

                // 5. Startups
                if (!empty($idsStartups)) {
                    Startup::whereIn('id', $idsStartups)->delete();
                }

                // 6. Tokens de recuperação de senha e sessões
                if (!empty($emailsUsuarios)) {
                    DB::table('password_reset_tokens')->whereIn('email', $emailsUsuarios)->delete();
                }
                DB::table('sessions')->whereIn('user_id', $idsUsuarios)->delete();

                // 7. Usuários e Agência
                User::whereIn('id', $idsUsuarios)->forceDelete();

                if ($agencia) {
                    $agencia->forceDelete();
                }
            });

            return response()->json([
                'message' => 'Sua conta, assinatura e todos os dados vinculados foram permanentemente excluídos conforme a LGPD.',
            ], 200);
        } catch (Exception $excecao) {
            return response()->json([
                'message' => 'Erro ao excluir conta e dados vinculados: ' . $excecao->getMessage(),
            ], 500);
        }
    }
}
