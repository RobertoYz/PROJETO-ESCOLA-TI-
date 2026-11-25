<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Edital;
use App\Spiders\FapegSpider;
use App\Spiders\FapdfSpider;
use App\Spiders\Processors\SalvarNoBancoProcessor;
use RoachPHP\Http\Response;
use RoachPHP\Http\Request;
use RoachPHP\ItemPipeline\Item;
use RoachPHP\Spider\ParseResult;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class RadarGoiasDistritoFederalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Teste da geração de itens do catálogo estruturado da FAPEG (Goiás).
     */
    public function test_fapeg_spider_emite_itens_estruturados_com_sucesso(): void
    {
        $spider = new FapegSpider();
        $psrResponse = new PsrResponse(200, [], '<html><body><h1>Portal FAPEG</h1></body></html>');
        $request = new Request('GET', 'https://fapeg.go.gov.br/editais/', [$spider, 'parse']);
        $response = new Response($psrResponse, $request);

        $itens = [];
        foreach ($spider->parse($response) as $result) {
            if ($result instanceof ParseResult) {
                $val = $result->value();
                if ($val instanceof \RoachPHP\ItemPipeline\ItemInterface) {
                    $itens[] = $val->all();
                }
            } elseif ($result instanceof \RoachPHP\ItemPipeline\ItemInterface) {
                $itens[] = $result->all();
            }
        }

        $this->assertNotEmpty($itens, 'O FapegSpider deve emitir itens estruturados de Goiás.');
        $this->assertGreaterThanOrEqual(4, count($itens));

        $titulos = array_column($itens, 'title');
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Tecnova III Goiás')));
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Centelha II Goiás')));
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Conecta Startup GO')));
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Doutor Empreendedor')));

        foreach ($itens as $item) {
            $this->assertEquals('FAPEG', $item['fonte']);
            $this->assertEquals('Goiás (GO)', $item['regiao']);
            $this->assertEquals('active', $item['status']);
            $this->assertNotNull($item['external_id']);
            $this->assertNotNull($item['max_budget']);
        }
    }

    /**
     * Teste da geração de itens do catálogo estruturado da FAPDF (Distrito Federal).
     */
    public function test_fapdf_spider_emite_itens_estruturados_com_sucesso(): void
    {
        $spider = new FapdfSpider();
        $psrResponse = new PsrResponse(200, [], '<html><body><h1>Portal FAPDF</h1></body></html>');
        $request = new Request('GET', 'https://www.fap.df.gov.br/editais-abertos/', [$spider, 'parse']);
        $response = new Response($psrResponse, $request);

        $itens = [];
        foreach ($spider->parse($response) as $result) {
            if ($result instanceof ParseResult) {
                $val = $result->value();
                if ($val instanceof \RoachPHP\ItemPipeline\ItemInterface) {
                    $itens[] = $val->all();
                }
            } elseif ($result instanceof \RoachPHP\ItemPipeline\ItemInterface) {
                $itens[] = $result->all();
            }
        }

        $this->assertNotEmpty($itens, 'O FapdfSpider deve emitir itens estruturados do Distrito Federal.');
        $this->assertGreaterThanOrEqual(4, count($itens));

        $titulos = array_column($itens, 'title');
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Tecnova III Distrito Federal')));
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Centelha Brasília')));
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Startups Brasília')));
        $this->assertTrue(collect($titulos)->contains(fn($t) => str_contains($t, 'Rede Integrada de Pesquisa e Inovação do Centro-Oeste')));

        foreach ($itens as $item) {
            $this->assertEquals('FAPDF', $item['fonte']);
            $this->assertEquals('Distrito Federal (DF)', $item['regiao']);
            $this->assertEquals('active', $item['status']);
            $this->assertNotNull($item['external_id']);
            $this->assertNotNull($item['max_budget']);
        }
    }

    /**
     * Teste do salvamento no banco de dados via SalvarNoBancoProcessor.
     */
    public function test_salvar_no_banco_processor_persiste_editais_fapeg_e_fapdf(): void
    {
        $processor = new SalvarNoBancoProcessor();

        // Processa edital da FAPEG
        $itemFapeg = new Item([
            'external_id' => 'fapeg_test_123',
            'title'       => 'Edital Teste FAPEG 2026',
            'source_url'  => 'https://fapeg.go.gov.br/teste',
            'fonte'       => 'FAPEG',
            'agency'      => 'FAPEG',
            'objetivo'    => 'Apoio a startups de inteligência artificial em Goiás',
            'deadline'    => '2026-12-31 23:59:00',
            'max_budget'  => 3000000.00,
            'status'      => 'active',
            'regiao'      => 'Goiás (GO)',
            'publico'     => 'Startups Goianas',
        ]);
        $processor->processItem($itemFapeg);

        // Processa edital da FAPDF
        $itemFapdf = new Item([
            'external_id' => 'fapdf_test_456',
            'title'       => 'Edital Teste FAPDF 2026',
            'source_url'  => 'https://fap.df.gov.br/teste',
            'fonte'       => 'FAPDF',
            'agency'      => 'FAPDF',
            'objetivo'    => 'Fomento a govtechs e inovação em Brasília',
            'deadline'    => '2027-01-31 23:59:00',
            'max_budget'  => 4000000.00,
            'status'      => 'active',
            'regiao'      => 'Distrito Federal (DF)',
            'publico'     => 'Startups do DF',
        ]);
        $processor->processItem($itemFapdf);

        $this->assertDatabaseHas('editals', [
            'external_id' => 'fapeg_test_123',
            'fonte'       => 'FAPEG',
            'regiao'      => 'Goiás (GO)',
        ]);

        $this->assertDatabaseHas('editals', [
            'external_id' => 'fapdf_test_456',
            'fonte'       => 'FAPDF',
            'regiao'      => 'Distrito Federal (DF)',
        ]);
    }

    /**
     * Teste dos endpoints de API com filtro por fonte FAPEG e FAPDF.
     */
    public function test_api_editais_filtra_fapeg_e_fapdf(): void
    {
        Edital::create([
            'external_id' => 'fapeg_api_1',
            'title'       => 'Edital FAPEG Tecnova III',
            'fonte'       => 'FAPEG',
            'regiao'      => 'Goiás (GO)',
            'status'      => 'active',
            'max_budget'  => 5000000.00,
        ]);

        Edital::create([
            'external_id' => 'fapdf_api_1',
            'title'       => 'Edital FAPDF Centelha Brasília',
            'fonte'       => 'FAPDF',
            'regiao'      => 'Distrito Federal (DF)',
            'status'      => 'active',
            'max_budget'  => 3500000.00,
        ]);

        Edital::create([
            'external_id' => 'finep_api_1',
            'title'       => 'Edital FINEP Mais Inovação',
            'fonte'       => 'FINEP',
            'regiao'      => 'Nacional',
            'status'      => 'active',
            'max_budget'  => 10000000.00,
        ]);

        // Consulta filtrada por FAPEG
        $responseGoias = $this->getJson('/api/editais?fonte=FAPEG');
        $responseGoias->assertStatus(200);
        $dadosGoias = $responseGoias->json();
        $this->assertCount(1, $dadosGoias);
        $this->assertEquals('FAPEG', $dadosGoias[0]['org']);
        $this->assertEquals('Goiás (GO)', $dadosGoias[0]['region']);

        // Consulta filtrada por FAPDF
        $responseDf = $this->getJson('/api/editais?fonte=FAPDF');
        $responseDf->assertStatus(200);
        $dadosDf = $responseDf->json();
        $this->assertCount(1, $dadosDf);
        $this->assertEquals('FAPDF', $dadosDf[0]['org']);
        $this->assertEquals('Distrito Federal (DF)', $dadosDf[0]['region']);
    }

    /**
     * Teste da execução do comando artisan de sincronização individual.
     */
    public function test_comandos_de_sincronizacao_individuais_executam_corretamente(): void
    {
        $exitCodeFapeg = Artisan::call('fapeg:sync');
        $this->assertEquals(0, $exitCodeFapeg);

        $exitCodeFapdf = Artisan::call('fapdf:sync');
        $this->assertEquals(0, $exitCodeFapdf);

        $this->assertDatabaseHas('editals', ['fonte' => 'FAPEG']);
        $this->assertDatabaseHas('editals', ['fonte' => 'FAPDF']);
    }

    /**
     * Teste da integração do comando unificado editais:sync com as novas fontes.
     */
    public function test_comando_unificado_editais_sync_aceita_fapeg_e_fapdf(): void
    {
        $exitCodeFapeg = Artisan::call('editais:sync', ['--fonte' => 'FAPEG']);
        $this->assertEquals(0, $exitCodeFapeg);

        $exitCodeFapdf = Artisan::call('editais:sync', ['--fonte' => 'FAPDF']);
        $this->assertEquals(0, $exitCodeFapdf);
    }
}
