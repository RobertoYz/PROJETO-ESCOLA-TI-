<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Startup;
use App\Models\Edital;
use App\Services\MinutaAiService;

class MatchServiceTest extends TestCase
{
    private MinutaAiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MinutaAiService();
    }

    /**
     * RF-47: Testa score de match quando há alinhamento direto de nicho tecnológico (+20 pts).
     */
    public function test_match_adiciona_pontos_quando_nicho_alinhado(): void
    {
        $startup = new Startup([
            'nicho_atuacao' => 'Inteligência Artificial e Tecnologia da Informação',
            'porte' => 'MICROEMPRESA',
            'faturamento_anual' => 150000
        ]);

        $edital = new Edital([
            'id' => 1,
            'title' => 'Edital FAPESC Inovação em Software e Tecnologia',
            'fonte' => 'FAPESC',
            'objetivo' => 'Fomento a projetos de software, IA e tecnologia.',
            'publico' => 'Microempresas e Startups de TI',
            'max_budget' => 300000.00,
            'deadline' => '2026-12-31'
        ]);

        $resultado = $this->service->calcularMatchEditais($startup, [$edital]);

        $this->assertCount(1, $resultado);
        $item = $resultado[0];

        // Base 50 + Nicho 20 + Porte 15 + Orçamento 10 = 95
        $this->assertEquals(95, $item['match_percentual']);
        $this->assertNotEmpty($item['pontos_fortes']);
        $this->assertStringContainsString('Aderência direta entre o nicho', $item['justificativa_fit']);
    }

    /**
     * RF-47: Testa que o score de fit respeita o piso de 45% e teto de 98%.
     */
    public function test_match_respeita_limites_minimo_e_maximo(): void
    {
        $startupMinima = new Startup([
            'nicho_atuacao' => 'Varejo Tradicional',
            'porte' => 'GRANDE PORTE',
            'faturamento_anual' => 50000000
        ]);

        $editalIncompativel = new Edital([
            'id' => 2,
            'title' => 'Edital Exclusivo de Biotecnologia',
            'fonte' => 'FINEP',
            'objetivo' => 'Pesquisa laboratorial avançada em genética.',
            'publico' => 'Institutos de Ciência e Tecnologia (ICTs)',
            'max_budget' => 0.00
        ]);

        $resultadoMinimo = $this->service->calcularMatchEditais($startupMinima, [$editalIncompativel]);
        $this->assertGreaterThanOrEqual(45, $resultadoMinimo[0]['match_percentual']);

        // Startup ideal com todos os critérios satisfeitos
        $startupIdeal = new Startup([
            'nicho_atuacao' => 'Tecnologia da Informação e Software',
            'porte' => 'STARTUP',
            'faturamento_anual' => 200000
        ]);

        $editalIdeal = new Edital([
            'id' => 3,
            'title' => 'Programa Anjo Inovador para Startups de TI',
            'fonte' => 'FAPESC',
            'objetivo' => 'Apoio financeiro direto para startups de tecnologia.',
            'publico' => 'Startups e Microempresas',
            'max_budget' => 500000.00
        ]);

        $resultadoIdeal = $this->service->calcularMatchEditais($startupIdeal, [$editalIdeal]);
        $this->assertLessThanOrEqual(98, $resultadoIdeal[0]['match_percentual']);
    }

    /**
     * RF-47: Testa que a lista é retornada em ordem decrescente de pontuação de match.
     */
    public function test_match_ordena_ranking_do_maior_para_o_menor_score(): void
    {
        $startup = new Startup([
            'nicho_atuacao' => 'Software e Tecnologia',
            'porte' => 'MICROEMPRESA'
        ]);

        $editalForte = new Edital([
            'title' => 'Fomento a Software',
            'publico' => 'Startups',
            'max_budget' => 250000.00,
            'objetivo' => 'Projetos em tecnologia e software.'
        ]);
        $editalForte->id = 10;

        $editalFraco = new Edital([
            'title' => 'Edital Geral sem Orçamento',
            'publico' => 'Pessoas Físicas',
            'max_budget' => 0.00,
            'objetivo' => 'Agropecuária tradicional.'
        ]);
        $editalFraco->id = 20;

        $ranking = $this->service->calcularMatchEditais($startup, [$editalFraco, $editalForte]);

        $this->assertCount(2, $ranking);
        $this->assertEquals(10, $ranking[0]['edital_id']); // Edital forte deve ser o 1º
        $this->assertEquals(20, $ranking[1]['edital_id']); // Edital fraco deve ser o 2º
        $this->assertGreaterThan($ranking[1]['match_percentual'], $ranking[0]['match_percentual']);
    }
}
