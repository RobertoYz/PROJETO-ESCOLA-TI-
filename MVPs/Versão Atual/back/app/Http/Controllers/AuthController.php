<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroCadastroRequest; 
use App\Services\RegistroService;
use Illuminate\Http\JsonResponse;
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
                'dados' => [
                    'agencia' => $resultado['agencia']->nome,
                    'usuario_admin' => $resultado['usuario']->email,
                    'token' => $token,
                    'link_pagamento' => $resultado['checkout_url']
                ]
            ], 201);

        // Código 500
        } catch (Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Falha ao registrar conta no sistema.',
                //SOMENTE PARA TESTE no POSTMAN -> 
                
                'detalhe' => $e->getMessage()
            ], 500);
        }
    }
}