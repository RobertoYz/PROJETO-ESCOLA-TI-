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
}