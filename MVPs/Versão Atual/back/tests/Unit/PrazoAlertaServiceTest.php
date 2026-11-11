<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PrazoAlertaService;
use Carbon\Carbon;

class PrazoAlertaServiceTest extends TestCase
{
    protected PrazoAlertaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrazoAlertaService();
    }

    /**
     * Testa deadline nulo ou em branco (fluxo contínuo).
     */
    public function test_deve_retornar_fluxo_continuo_para_deadline_nulo(): void
    {
        $resultado = $this->service->calcularCriticidade(null);

        $this->assertFalse($resultado['has_deadline']);
        $this->assertEquals('sem_prazo', $resultado['status']);
        $this->assertEquals('Fluxo Contínuo / A Definir', $resultado['rotulo']);
        $this->assertFalse($resultado['is_critico']);
        $this->assertFalse($resultado['is_expirado']);
    }

    /**
     * Testa prazo com menos de 24 horas (Crítico Urgente).
     */
    public function test_deve_identificar_prazo_critico_urgente_menor_que_24_horas(): void
    {
        $dataLimite24h = Carbon::now()->addHours(12)->toDateTimeString();

        $resultado = $this->service->calcularCriticidade($dataLimite24h);

        $this->assertTrue($resultado['has_deadline']);
        $this->assertEquals('critico_24h', $resultado['status']);
        $this->assertEquals('urgente', $resultado['nivel']);
        $this->assertTrue($resultado['is_critico']);
        $this->assertTrue($resultado['is_urgente']);
        $this->assertFalse($resultado['is_expirado']);
    }

    /**
     * Testa prazo entre 24h e 72 horas (Alerta 72h).
     */
    public function test_deve_identificar_prazo_alerta_menor_que_72_horas(): void
    {
        $dataLimite48h = Carbon::now()->addHours(48)->toDateTimeString();

        $resultado = $this->service->calcularCriticidade($dataLimite48h);

        $this->assertTrue($resultado['has_deadline']);
        $this->assertEquals('alerta_72h', $resultado['status']);
        $this->assertEquals('alerta', $resultado['nivel']);
        $this->assertTrue($resultado['is_critico']);
        $this->assertFalse($resultado['is_urgente']);
    }

    /**
     * Testa prazo moderado entre 3 e 7 dias.
     */
    public function test_deve_identificar_prazo_moderado_menor_que_7_dias(): void
    {
        $dataLimite5Dias = Carbon::now()->addDays(5)->toDateTimeString();

        $resultado = $this->service->calcularCriticidade($dataLimite5Dias);

        $this->assertTrue($resultado['has_deadline']);
        $this->assertEquals('moderado_7dias', $resultado['status']);
        $this->assertEquals('moderado', $resultado['nivel']);
        $this->assertFalse($resultado['is_critico']);
    }

    /**
     * Testa prazo expirado no passado.
     */
    public function test_deve_identificar_prazo_expirado(): void
    {
        $dataPassada = Carbon::now()->subDays(2)->toDateTimeString();

        $resultado = $this->service->calcularCriticidade($dataPassada);

        $this->assertTrue($resultado['has_deadline']);
        $this->assertEquals('expirado', $resultado['status']);
        $this->assertTrue($resultado['is_expirado']);
        $this->assertFalse($resultado['is_critico']);
    }
}

