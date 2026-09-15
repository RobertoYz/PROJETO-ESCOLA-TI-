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
                'id_plano_mercado_pago' => null,
                'limite_startups' => 2,
                'limite_membros_equipe' => 1
            ],
            [
                'nome' => 'Plano Pro',
                'preco' => 99.90,
                'id_plano_mercado_pago' => 'mercado_pago_pro_id',
                'limite_startups' => 10,
                'limite_membros_equipe' => 5
            ],
            [
                'nome' => 'Plano Enterprise',
                'preco' => 299.90,
                'id_plano_mercado_pago' => 'mercado_pago_enterprise_id',
                'limite_startups' => 50,
                'limite_membros_equipe' => 15
            ]
        ];

        //Um laço de repetição para salvar cada plano no DB
        foreach ($planos as $plano) {
            Plano::create($plano);
        }
    }
}
