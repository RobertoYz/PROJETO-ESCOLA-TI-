<?php

namespace App\Services;

use App\Models\Agencia;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistroService
{
    public function registrarNovaAgenciaEUsuario(array $dados): array
    {
        return DB::transaction(function () use ($dados) {
            $planoPadrao = Plano::first();

            $agencia = Agencia::create([
                'nome' => $dados['nome_agencia'],
                'plano_id' => $planoPadrao ? $planoPadrao->id : 1,
                'status_pagamento' => 'pendente',
            ]);

            $usuario = User::create([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => Hash::make($dados['password']),
                'agencia_id' => $agencia->id,
                'perfil_acesso' => 'consultor_admin',
            ]);

            $token = $usuario->createToken('auth_token')->plainTextToken;

            return [
                'agencia' => $agencia,
                'usuario' => $usuario,
                'token' => $token,
            ];
        });
    }
}
