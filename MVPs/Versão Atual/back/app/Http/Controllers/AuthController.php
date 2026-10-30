<?php

namespace App\Http\Controllers;

use App\Services\RegistroService;
use App\Services\AbacatePayService;
use App\Models\User;
use App\Models\Agencia;
use App\Models\Plano;
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
    protected $registroService;

    public function __construct(RegistroService $registroService)
    {
        $this->registroService = $registroService;
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

        $usuarioExistente = User::where('email', $requisicao->email)->exists();

        if ($usuarioExistente) {
            return response()->json([
                'disponivel' => false,
                'message' => 'Este e-mail já está cadastrado. Faça login para acessar ou gerenciar sua conta.',
                'mensagem' => 'Este e-mail já está cadastrado. Faça login para acessar ou gerenciar sua conta.'
            ], 422);
        }

        return response()->json([
            'disponivel' => true,
            'mensagem' => 'E-mail disponível para cadastro.'
        ], 200);
    }

    /**
     * 2. Registra o Usuário e a Agência vinculados ao plano e gera cobrança no AbacatePay
     * POST /api/auth/registrar-com-plano
     */
    public function registrarComPlano(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $dadosValidados = $requisicao->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'plano_id' => 'required|exists:planos,id',
            'nome_agencia' => 'nullable|string|max:255',
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado. Faça login para acessar sua conta.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve conter no mínimo 8 caracteres.',
            'plano_id.required' => 'Por favor, selecione um plano.',
            'plano_id.exists' => 'O plano selecionado não foi encontrado.',
        ]);

        DB::beginTransaction();

        try {
            $plano = Plano::findOrFail($dadosValidados['plano_id']);

            $nomeAgencia = !empty($dadosValidados['nome_agencia'])
                ? $dadosValidados['nome_agencia']
                : 'Consultoria de ' . $dadosValidados['name'];

            // Criação da Agência com status de pagamento pendente
            $agencia = Agencia::create([
                'nome' => $nomeAgencia,
                'nome_fantasia' => $nomeAgencia,
                'plano_id' => $plano->id,
                'status_pagamento' => 'pendente',
                'pagamento_ativo' => false,
                'pagamento_expira_em' => Carbon::now()->addMinutes(60),
            ]);

            // Criação do Usuário com perfil de consultor_admin
            $usuario = User::create([
                'name' => $dadosValidados['name'],
                'email' => $dadosValidados['email'],
                'password' => Hash::make($dadosValidados['password']),
                'agencia_id' => $agencia->id,
                'perfil_acesso' => 'consultor_admin',
            ]);

            // Geração da cobrança real no AbacatePay
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $urlCheckout = is_array($cobranca) ? ($cobranca['url'] ?? null) : $cobranca;
            $idCobranca = is_array($cobranca) ? ($cobranca['id'] ?? null) : null;

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            // Salva link e identificador de cobrança na Agência
            $agencia->checkout_url = $urlCheckout;
            $agencia->url_checkout = $urlCheckout;
            $agencia->billing_id = $idCobranca;
            $agencia->id_cobranca = $idCobranca;
            $agencia->id_plataforma_pagamento = $idCobranca;
            $agencia->save();

            DB::commit();

            return response()->json([
                'mensagem' => 'Conta criada com pagamento pendente. Redirecionando para o pagamento...',
                'checkout_url' => $urlCheckout,
                'url_checkout' => $urlCheckout,
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email
                ]
            ], 201);

        } catch (Exception $excecao) {
            DB::rollBack();

            return response()->json([
                'mensagem' => 'Erro ao processar cadastro e geração de cobrança: ' . $excecao->getMessage()
            ], 500);
        }
    }

    /**
     * Cadastro legado mantido para compatibilidade
     * POST /api/auth/registro
     */
    public function registrar(Request $requisicao)
    {
        $dadosValidados = $requisicao->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'nome_agencia' => 'nullable|string|max:255',
        ]);

        $resultado = $this->registroService->registrarNovaAgenciaEUsuario($dadosValidados);

        return response()->json([
            'mensagem' => 'Cadastro inicial realizado. Selecione o seu plano.',
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
                'mensagem' => 'Credenciais incorretas. Verifique seu e-mail e senha.'
            ], 401);
        }

        $agencia = $usuario->agencia;

        // Se o usuário possui agência com pagamento NÃO realizado, bloqueia e emite token de pendência
        if ($agencia && (!$agencia->pagamento_ativo && $agencia->status_pagamento !== 'ativo')) {
            $tokenPendencia = $usuario->createToken('token_pendencia', ['pagamento:gerenciar'])->plainTextToken;

            return response()->json([
                'codigo' => 'PAGAMENTO_PENDENTE',
                'codigo_erro' => 'PAGAMENTO_PENDENTE',
                'mensagem' => 'Seu cadastro possui um pagamento pendente.',
                'access_token' => $tokenPendencia,
                'token' => $tokenPendencia,
                'token_pendencia' => $tokenPendencia,
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email
                ],
                'agencia' => [
                    'id' => $agencia->id,
                    'nome' => $agencia->nome,
                    'status_pagamento' => $agencia->status_pagamento,
                    'checkout_url' => $agencia->checkout_url,
                    'url_checkout' => $agencia->checkout_url,
                    'pagamento_expira_em' => $agencia->pagamento_expira_em,
                ]
            ], 403);
        }

        // Login aprovado com assinatura ativa
        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Login realizado com sucesso!',
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
                    'pagamento_ativo' => $agencia->pagamento_ativo,
                ] : null,
            ]
        ], 200);
    }

    /**
     * 4. Consulta a janela de pagamento anterior
     * GET /api/pagamentos/consultar-anterior
     */
    public function consultarPagamentoAnterior(Request $requisicao, AbacatePayService $servicoAbacatePay): JsonResponse
    {
        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$agencia) {
            return response()->json(['mensagem' => 'Agência não vinculada a este usuário.'], 404);
        }

        // Se já estiver pago localmente, confirma a liberação
        if ($agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo') {
            return response()->json([
                'expirado' => false,
                'pago' => true,
                'mensagem' => 'O pagamento já foi confirmado!'
            ], 200);
        }

        // Consulta em tempo real no gateway de pagamento
        $idCobranca = $agencia->billing_id ?? $agencia->id_cobranca ?? $agencia->id_plataforma_pagamento;
        if (!empty($idCobranca)) {
            $statusGateway = $servicoAbacatePay->consultarStatusCobranca($idCobranca);
            if ($statusGateway === 'PAID') {
                $agencia->pagamento_ativo = true;
                $agencia->status_pagamento = 'ativo';
                $agencia->save();

                return response()->json([
                    'expirado' => false,
                    'pago' => true,
                    'mensagem' => 'O pagamento foi confirmado com sucesso!'
                ], 200);
            }
        }

        $urlCheckout = $agencia->checkout_url ?? $agencia->url_checkout;
        $expirado = $servicoAbacatePay->estaExpirado($agencia);

        if ($expirado || empty($urlCheckout)) {
            return response()->json([
                'expirado' => true,
                'mensagem' => "O pagamento anterior expirou! Por favor, clique na opção 'Gerar uma nova cobrança' para escolher um plano novamente."
            ], 400);
        }

        return response()->json([
            'expirado' => false,
            'checkout_url' => $urlCheckout,
            'url_checkout' => $urlCheckout,
            'mensagem' => 'Janela de pagamento ainda válida.'
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

        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$agencia) {
            return response()->json(['mensagem' => 'Agência não encontrada para este usuário.'], 404);
        }

        $plano = Plano::findOrFail($requisicao->plano_id);

        try {
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $urlCheckout = is_array($cobranca) ? ($cobranca['url'] ?? null) : $cobranca;
            $idCobranca = is_array($cobranca) ? ($cobranca['id'] ?? null) : null;

            if (empty($urlCheckout) || !str_starts_with($urlCheckout, 'http') || !str_contains($urlCheckout, 'abacatepay.com')) {
                throw new Exception('O gateway de pagamento AbacatePay não retornou uma URL de checkout válida.');
            }

            $agencia->plano_id = $plano->id;
            $agencia->checkout_url = $urlCheckout;
            $agencia->url_checkout = $urlCheckout;
            $agencia->billing_id = $idCobranca;
            $agencia->id_cobranca = $idCobranca;
            $agencia->id_plataforma_pagamento = $idCobranca;
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);
            $agencia->status_pagamento = 'pendente';
            $agencia->pagamento_ativo = false;
            $agencia->save();

            return response()->json([
                'checkout_url' => $urlCheckout,
                'url_checkout' => $urlCheckout,
                'mensagem' => 'Nova cobrança gerada com sucesso.'
            ], 200);

        } catch (Exception $excecao) {
            return response()->json([
                'mensagem' => 'Erro ao gerar nova cobrança: ' . $excecao->getMessage()
            ], 500);
        }
    }

    /**
     * 6. Cancela o pagamento pendente e remove permanentemente a conta (Hard Delete)
     * DELETE /api/auth/cancelar-cadastro-pendente
     */
    public function cancelarCadastroPendente(Request $requisicao): JsonResponse
    {
        $usuario = $requisicao->user();

        if (!$usuario) {
            return response()->json(['mensagem' => 'Usuário não autenticado.'], 401);
        }

        $agencia = $usuario->agencia;

        // Por segurança, impede excluir se o pagamento já estiver aprovado
        if ($agencia && ($agencia->pagamento_ativo || $agencia->status_pagamento === 'ativo')) {
            return response()->json([
                'mensagem' => 'Não é permitido cancelar uma conta com assinatura ativa.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($usuario, $agencia) {
                // Revoga todos os tokens Sanctum do usuário
                $usuario->tokens()->delete();

                // Exclui a agência e seus dados vinculados
                if ($agencia) {
                    $agencia->startups()->delete();
                    $agencia->usuarios()->forceDelete();
                    $agencia->forceDelete();
                } else {
                    $usuario->forceDelete();
                }
            });

            return response()->json([
                'mensagem' => 'Pagamento cancelado e conta excluída com sucesso.'
            ], 200);

        } catch (Exception $excecao) {
            return response()->json([
                'mensagem' => 'Erro ao cancelar cadastro e excluir dados: ' . $excecao->getMessage()
            ], 500);
        }
    }

    /**
     * Solicita a recuperação de senha (apenas para e-mails já cadastrados no sistema).
     */
    public function solicitarRecuperacaoSenha(Request $requisicao)
    {
        $requisicao->validate([
            'email' => 'required|string|email|max:255',
        ], [
            'email.required' => 'Por favor, informe o seu endereço de e-mail.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        $email = strtolower(trim($requisicao->email));

        // Ferramenta de verificação estrita: verifica se o e-mail pertence a um usuário cadastrado
        $usuario = User::where('email', $email)->first();

        if (!$usuario) {
            return response()->json([
                'mensagem' => 'Nenhuma conta foi encontrada com este endereço de e-mail. Verifique o e-mail digitado ou crie uma conta.',
                'message' => 'Nenhuma conta foi encontrada com este endereço de e-mail. Verifique o e-mail digitado ou crie uma conta.'
            ], 404);
        }

        // Gera token seguro e grava na tabela password_reset_tokens
        $tokenPuro = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $usuario->email],
            [
                'token' => Hash::make($tokenPuro),
                'created_at' => Carbon::now()
            ]
        );

        // Descobre a URL base do Front-end (Live Server ou Produção) a partir do cabeçalho X-Frontend-Url
        $urlOrigemFront = $requisicao->header('X-Frontend-Url') ?: $requisicao->header('Referer');
        if ($urlOrigemFront && str_contains($urlOrigemFront, '/paginas/')) {
            $urlBasePaginas = substr($urlOrigemFront, 0, strpos($urlOrigemFront, '/paginas/') + 9);
        } else {
            $urlBasePaginas = 'http://127.0.0.1:5500/MVPs/Vers%C3%A3o%20Atual/front/paginas/';
        }

        $linkRedefinicao = $urlBasePaginas . 'redefinir-senha.html?token=' . urlencode($tokenPuro) . '&email=' . urlencode($usuario->email);

        // Monta o corpo do e-mail em HTML com a identidade visual do Anjo Nexus
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
                'mensagem' => 'Falha ao enviar o e-mail de recuperação: ' . $erro->getMessage(),
                'message' => 'Falha ao enviar o e-mail de recuperação: ' . $erro->getMessage()
            ], 500);
        }

        return response()->json([
            'mensagem' => 'Enviamos um link de recuperação para o seu e-mail! Verifique sua caixa de entrada e o spam.'
        ], 200);
    }

    /**
     * Valida o token recebido e redefine a senha do usuário no banco de dados.
     */
    public function redefinirSenha(Request $requisicao)
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
                'mensagem' => 'Conta de usuário não encontrada.'
            ], 404);
        }

        $registroToken = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$registroToken) {
            return response()->json([
                'mensagem' => 'Solicitação de redefinição inválida ou já utilizada. Solicite um novo link.'
            ], 400);
        }

        // Verifica expiração de 60 minutos
        if (Carbon::parse($registroToken->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'mensagem' => 'O link de recuperação expirou (validade de 60 minutos). Solicite um novo link.'
            ], 400);
        }

        // Verifica se o token da URL corresponde ao hash salvo
        if (!Hash::check($requisicao->token, $registroToken->token)) {
            return response()->json([
                'mensagem' => 'Token de recuperação inválido. Verifique se abriu o último e-mail enviado.'
            ], 400);
        }

        // Atualiza a senha do usuário e remove o token utilizado
        $usuario->password = Hash::make($requisicao->password);
        $usuario->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'mensagem' => 'Sua senha foi redefinida com sucesso! Redirecionando para o login...'
        ], 200);
    }
}