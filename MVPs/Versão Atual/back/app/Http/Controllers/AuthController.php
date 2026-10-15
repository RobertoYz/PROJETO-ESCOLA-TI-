<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroCadastroRequest; 
use App\Services\RegistroService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Exception;

class AuthController extends Controller
{
    protected $registroService;

    //Constructor do Service
    public function __construct(RegistroService $registroService)
    {
        $this->registroService = $registroService;
    }

    public function registrar(RegistroCadastroRequest $request): JsonResponse
    {
        $dadosSeguros = $request->validated();

        try {
            //Pega os dados já verificados e usa o Service para registrar no banco
            $resultado = $this->registroService->registrarNovaAgenciaEUsuario($dadosSeguros);

            $usuario = $resultado['usuario'];
            $token = $usuario->createToken('token_de_acesso')->plainTextToken;

            //Código 201
            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Agência e Usuário criados com sucesso!',
                'checkout_url' => $resultado['checkout_url'],
                'dados' => [
                    'agencia' => $resultado['agencia']->nome,
                    'usuario_admin' => $resultado['usuario']->email,
                    'token' => $token,
                    'link_pagamento' => $resultado['checkout_url'],
                    'checkout_url' => $resultado['checkout_url']
                ]
            ], 201);

        // Código 500
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao registrar conta no sistema.',
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validacao = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'password.required' => 'A senha é obrigatória.',
        ]);

        $usuario = User::with('agencia')->where('email', $validacao['email'])->first();

        if (!$usuario || !Hash::check($validacao['password'], $usuario->password)) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Credenciais incorretas. Verifique seu e-mail e senha.'
            ], 401);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Login realizado com sucesso!',
            'token' => $token,
            'dados' => [
                'usuario' => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'perfil_acesso' => $usuario->perfil_acesso,
                ],
                'agencia' => $usuario->agencia ? [
                    'id' => $usuario->agencia->id,
                    'nome' => $usuario->agencia->nome,
                    'status_pagamento' => $usuario->agencia->status_pagamento,
                ] : null,
            ]
        ], 200);
    }
}