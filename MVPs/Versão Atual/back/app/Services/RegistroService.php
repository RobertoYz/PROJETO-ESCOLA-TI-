<?php

namespace App\Services;

use App\Models\Agencia;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistroService
{
    public function registrarNovaAgenciaEUsuario(array $dados)
    {
        return DB::transaction(function () use ($dados) {
            $planoPadrao = Plano::first();

            $nomeAgencia = !empty($dados['nome_agencia'])
                ? $dados['nome_agencia']
                : 'Consultoria de ' . $dados['name'];

            $agencia = Agencia::create([
                'nome' => $nomeAgencia,
                'nome_fantasia' => $nomeAgencia,
                'plano_id' => $planoPadrao ? $planoPadrao->id : 1,
                'status_pagamento' => 'pendente',
            ]);

            $usuario = User::create([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => Hash::make($dados['password']),
                'agencia_id' => $agencia->id,
                'role' => 'consultor_admin',
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