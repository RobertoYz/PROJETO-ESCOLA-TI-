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
                'nome' => 'Plano Standart',
                'preco' => 0.00,
                'id_plataforma_pagamento' => 'prod_sWfDg0eZXTLbtZEHYpqnMLKP', // ID real do AbacatePay para o ID 1
                'limite_startups' => 2,
                'limite_membros_equipe' => 1
            ],
            [
                'nome' => 'Plano Pro',
                'preco' => 99.90,
                'id_plataforma_pagamento' => 'prod_EXEMPLO2',
                'limite_startups' => 10,
                'limite_membros_equipe' => 5
            ],
            [
                'nome' => 'Plano Enterprise',
                'preco' => 299.90,
                'id_plataforma_pagamento' => 'prod_EXEMPLO3',
                'limite_startups' => 50,
                'limite_membros_equipe' => 15
            ]
        ];

        // Um laço de repetição para salvar cada plano no DB
        foreach ($planos as $plano) {
            Plano::create($plano);
        }
    }
}