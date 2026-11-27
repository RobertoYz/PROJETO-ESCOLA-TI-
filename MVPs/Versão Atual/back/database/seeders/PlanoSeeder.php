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
                'id_transacao' => 'prod_NKNk5t4saLSRLGEEZ21qL0sG',
                'limite_startups' => 2,
                'limite_armazenamento_mb' => 1024,
                'limite_arquivo_mb' => 50,
                'limite_membros_equipe' => 1,
                'limite_minutas_ia' => 3
            ],
            [
                'id' => 2,
                'nome' => 'Pro',
                'preco' => 99.90,
                'id_transacao' => 'prod_BrNPMy2ajTtF0MzhzWwdCkpp',
                'limite_startups' => 10,
                'limite_armazenamento_mb' => 5120,
                'limite_arquivo_mb' => 50,
                'limite_membros_equipe' => 5,
                'limite_minutas_ia' => 15
            ],
            [
                'id' => 3,
                'nome' => 'Enterprise',
                'preco' => 299.90,
                'id_transacao' => 'prod_yjeeY2u5sXwGaZJZGGpEgW1g',
                'limite_startups' => 50,
                'limite_armazenamento_mb' => 25600,
                'limite_arquivo_mb' => 50,
                'limite_membros_equipe' => 15,
                'limite_minutas_ia' => 50
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
