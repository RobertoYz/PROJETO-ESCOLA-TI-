<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EquipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipeController extends Controller
{
    protected EquipeService $equipeService;

    public function __construct(EquipeService $equipeService)
    {
        $this->equipeService = $equipeService;
    }

    public function index(Request $request): JsonResponse
    {
        $dados = $this->equipeService->listarMembros($request->user());

        return response()->json($dados, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $dadosValidados = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'permissoes' => 'nullable|array',
            'permissoes.*' => 'string',
        ], [
            'name.required' => 'O nome do consultor é obrigatório.',
            'email.required' => 'O e-mail do consultor é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
        ]);

        $resultado = $this->equipeService->convidar($request->user(), $dadosValidados, $request);

        return response()->json([
            'message' => 'Convite gerado com sucesso! Envie o código de ativação de 6 dígitos ao consultor.',
            'membro' => $resultado['membro'],
            'link_convite' => $resultado['link_convite'],
            'codigo_ativacao' => $resultado['codigo_ativacao'],
            'expira_em' => $resultado['expira_em'],
            'email_enviado' => $resultado['email_enviado'],
        ], 201);
    }

    public function atualizarPermissoes(Request $request, int $id): JsonResponse
    {
        $dadosValidados = $request->validate([
            'permissoes' => 'present|array',
            'permissoes.*' => 'string',
        ], [
            'permissoes.present' => 'Informe a lista de permissões do consultor.',
            'permissoes.array' => 'O formato das permissões é inválido.',
        ]);

        $alvo = User::findOrFail($id);
        $membroAtualizado = $this->equipeService->atualizarPermissoes(
            $request->user(),
            $alvo,
            $dadosValidados['permissoes']
        );

        return response()->json([
            'message' => 'Permissões atualizadas com sucesso.',
            'membro' => $membroAtualizado,
        ], 200);
    }

    public function atualizarStatus(Request $request, int $id): JsonResponse
    {
        $alvo = User::findOrFail($id);

        $novoStatus = $request->input('status');
        if ($novoStatus === null) {
            $novoStatus = ($alvo->status === 'inativo') ? 'ativo' : 'inativo';
        }

        $membroAtualizado = $this->equipeService->atualizarStatus(
            $request->user(),
            $alvo,
            (string) $novoStatus
        );

        $mensagem = $membroAtualizado['status'] === 'inativo'
            ? 'Acesso do consultor desativado e sessões encerradas imediatamente.'
            : 'Acesso do consultor reativado com sucesso.';

        return response()->json([
            'message' => $mensagem,
            'membro' => $membroAtualizado,
        ], 200);
    }

    public function reenviarConvite(Request $request, int $id): JsonResponse
    {
        $alvo = User::findOrFail($id);
        $resultado = $this->equipeService->reenviarConvite($request->user(), $alvo, $request);

        return response()->json([
            'message' => 'Novo convite e código PIN de 6 dígitos gerados com sucesso.',
            'membro' => $resultado['membro'],
            'link_convite' => $resultado['link_convite'],
            'codigo_ativacao' => $resultado['codigo_ativacao'],
            'expira_em' => $resultado['expira_em'],
            'email_enviado' => $resultado['email_enviado'],
        ], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $alvo = User::findOrFail($id);
        $eraPendente = ($alvo->status ?? 'ativo') === 'pendente';

        $this->equipeService->remover($request->user(), $alvo);

        return response()->json([
            'message' => $eraPendente
                ? 'Convite pendente cancelado e removido com sucesso.'
                : 'Consultor removido da equipe e sessões revogadas. Os dados da consultoria foram preservados.',
        ], 200);
    }

    public function validarConvitePublico(string $token): JsonResponse
    {
        $dados = $this->equipeService->consultarConvitePublico($token);

        return response()->json($dados, 200);
    }

    public function aceitarConvitePublico(Request $request): JsonResponse
    {
        $dadosValidados = $request->validate([
            'token' => 'required|string',
            'codigo' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'token.required' => 'O token do convite é obrigatório.',
            'codigo.required' => 'Informe o código de verificação de 6 dígitos.',
            'codigo.regex' => 'O código de verificação deve conter exatamente 6 dígitos numéricos.',
            'password.required' => 'Crie uma senha para ativar sua conta.',
            'password.min' => 'A senha deve conter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não coincide.',
        ]);

        $usuario = $this->equipeService->aceitarConvite($dadosValidados);

        return response()->json([
            'message' => 'Conta ativada com sucesso! Você já pode fazer login na plataforma.',
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'status' => $usuario->status,
            ],
        ], 200);
    }
}
