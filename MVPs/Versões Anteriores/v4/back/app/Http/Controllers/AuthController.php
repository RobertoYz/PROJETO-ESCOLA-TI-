<?php

namespace App\Http\Controllers;

use App\Services\RegistroService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Exception;

class AuthController extends Controller
{
    protected $registroService;

    // Constructor do Service
    public function __construct(RegistroService $registroService)
    {
        $this->registroService = $registroService;
    }

    public function registrar(Request $request)
    {
        $dadosValidados = $request->validate([
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