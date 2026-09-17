<?php

namespace App\Services;

use App\Models\Agencia;
use App\Models\User;
use App\Models\Plano;
use Illuminate\Support\Facades\DB;
use App\Services\AbacatePayService;

class RegistroService {
    protected $abacatePayService;

        public function __construct(AbacatePayService $abacatePayService) {
            $this->abacatePayService = $abacatePayService;
        }

        public function registrarNovaAgenciaEUsuario(array $dados) {

        return DB::transaction(function () use ($dados) {

        //Eloquent vai buscar o plano "standart" no banco pelo nome
        $planoPadrao = Plano::where('nome', 'Plano Standart')->first();
            
            //Cria a agencia no Banco
            $agencia = Agencia::create([
                'nome' => $dados['nome_agencia'],
                'plano_id' => $planoPadrao->id,
                'status_pagamento' => 'pendente'
            ]);

            //Cria o User no banco
            $user = User::create([
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => $dados['password'], //Hash automático no Model
                'agencia_id' => $agencia->id,
                'perfil_acesso' => 'consultor_admin',
            ]);

            $linkCheckout = $this->abacatePayService->gerarCobranca($agencia, $user, $planoPadrao);

            //Retorna os objts criados para o Controller
            return [
                'agencia' => $agencia,
                'usuario' => $user,
                'checkout_url' => $linkCheckout
            ];
        });
    }
}