<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Startup;
use App\Models\Agencia;

class StartupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agencia = Agencia::first();
        if (!$agencia) return;

        $startups = [
            [
                'agencia_id' => $agencia->id,
                'cnpj' => '34.406.665/0001-11',
                'razao_social' => 'PILAR I9 TECNOLOGIA DA INFORMACAO LTDA',
                'nome_fantasia' => 'PILAR I9',
                'data_abertura' => '2019-07-24',
                'email' => 'contato@pilari9.com.br',
                'telefone' => '(44) 99937-3873',
                'cep' => '87075-824',
                'logradouro' => 'Rua Jose Maria Matias',
                'numero' => '56',
                'bairro' => 'Zona 07',
                'cidade' => 'Maringá',
                'estado' => 'PR',
                'porte' => 'Microempresa (ME)',
                'regime_tributario' => 'Simples Nacional',
                'nicho_atuacao' => 'Cidades Inteligentes e Inteligência Artificial',
                'pitch' => 'Desenvolvemos soluções para automação de processos em cidades inteligentes e órgãos públicos através de visão computacional e inteligência artificial.'
            ],
            [
                'agencia_id' => $agencia->id,
                'cnpj' => '41.892.123/0001-90',
                'razao_social' => 'AGROTITAN INOVACOES DO CAMPO LTDA',
                'nome_fantasia' => 'AGROTITAN',
                'data_abertura' => '2021-03-15',
                'email' => 'tech@agrotitan.com.br',
                'telefone' => '(49) 98822-1144',
                'cep' => '89801-000',
                'logradouro' => 'Avenida Getúlio Vargas',
                'numero' => '1200',
                'bairro' => 'Centro',
                'cidade' => 'Chapecó',
                'estado' => 'SC',
                'porte' => 'Empresa de Pequeno Porte (EPP)',
                'regime_tributario' => 'Simples Nacional',
                'nicho_atuacao' => 'Agronegócio e Biotecnologia',
                'pitch' => 'Plataforma IoT e sensoriamento espectral para monitoramento da saúde do solo e predição de pragas com redução de 30% no uso de defensivos agrícolas.'
            ]
        ];

        foreach ($startups as $data) {
            Startup::firstOrCreate(['cnpj' => $data['cnpj']], $data);
        }
    }
}
