<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plano;

class PlanoSeeder extends Seeder
{
    public function run(): void
    {
        $planos = [
            [
                'id' => 1,
                'nome' => 'Standard',
                'preco' => 49.90,
                'id_plataforma_pagamento' => 'prod_sWfDg0eZXTLbtZEHYpqnMLKP', // ID real do AbacatePay
                'limite_startups' => 2,
                'limite_membros_equipe' => 1
            ],
            [
                'id' => 2,
                'nome' => 'Pro',
                'preco' => 99.90,
                'id_plataforma_pagamento' => 'prod_sWfDg0eZXTLbtZEHYpqnMLKP',
                'limite_startups' => 10,
                'limite_membros_equipe' => 5
            ],
            [
                'id' => 3,
                'nome' => 'Enterprise',
                'preco' => 299.90,
                'id_plataforma_pagamento' => 'prod_sWfDg0eZXTLbtZEHYpqnMLKP',
                'limite_startups' => 50,
                'limite_membros_equipe' => 15
            ]
        ];

        foreach ($planos as $dadosPlano) {
            $id = $dadosPlano['id'];
            unset($dadosPlano['id']);
            Plano::updateOrCreate(
                ['id' => $id],
                $dadosPlano
            );
        }
    }
}