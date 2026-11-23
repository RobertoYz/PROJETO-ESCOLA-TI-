<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Rules\CnpjValidationRule;

class CnpjValidationRuleTest extends TestCase
{
    /**
     * Testa CNPJs válidos conhecidos (formatados e desformatados).
     */
    public function test_valida_cnpjs_corretos(): void
    {
        $validCnpjs = [
            '34.406.665/0001-11', // Startup do Seeder
            '34406665000111',
            '33.000.167/0001-01', // Petrobras
            '33000167000101',
            '06.990.590/0001-23', // Google Brasil
            '06990590000123',
            '00.000.000/0001-91', // Banco do Brasil
            '00000000000191'
        ];

        foreach ($validCnpjs as $cnpj) {
            $this->assertTrue(
                CnpjValidationRule::isValid($cnpj),
                "O CNPJ {$cnpj} deveria ser considerado válido."
            );
        }
    }

    /**
     * Testa CNPJs com dígitos verificadores matematicamente incorretos.
     */
    public function test_rejeita_cnpjs_com_digitos_verificadores_incorretos(): void
    {
        $invalidCnpjs = [
            '34.406.665/0001-12', // Último dígito alterado
            '33.000.167/0001-99',
            '06.990.590/0001-00',
            '12.345.678/0001-99',
            '12345678000199'
        ];

        foreach ($invalidCnpjs as $cnpj) {
            $this->assertFalse(
                CnpjValidationRule::isValid($cnpj),
                "O CNPJ {$cnpj} deveria ser considerado inválido."
            );
        }
    }

    /**
     * Testa rejeição de CNPJs formados por dígitos repetidos.
     */
    public function test_rejeita_cnpjs_com_digitos_repetidos(): void
    {
        $repeated = [
            '00.000.000/0000-00',
            '11.111.111/1111-11',
            '22222222222222',
            '33333333333333',
            '99999999999999'
        ];

        foreach ($repeated as $cnpj) {
            $this->assertFalse(
                CnpjValidationRule::isValid($cnpj),
                "O CNPJ {$cnpj} com dígitos repetidos deveria ser rejeitado."
            );
        }
    }

    /**
     * Testa rejeição de valores com comprimento inadequado ou vazios.
     */
    public function test_rejeita_comprimento_invalido(): void
    {
        $this->assertFalse(CnpjValidationRule::isValid(''));
        $this->assertFalse(CnpjValidationRule::isValid('123'));
        $this->assertFalse(CnpjValidationRule::isValid('1234567890123')); // 13 dígitos
        $this->assertFalse(CnpjValidationRule::isValid('123456789012345')); // 15 dígitos
    }

    /**
     * Testa a higienização e remoção de caracteres não numéricos.
     */
    public function test_sanitiza_corretamente(): void
    {
        $this->assertEquals('34406665000111', CnpjValidationRule::sanitize('34.406.665/0001-11'));
        $this->assertEquals('33000167000101', CnpjValidationRule::sanitize('  33.000.167/0001-01  '));
        $this->assertEquals('', CnpjValidationRule::sanitize(null));
    }

    /**
     * Testa a formatação no padrão 00.000.000/0001-00.
     */
    public function test_formata_cnpj(): void
    {
        $this->assertEquals('34.406.665/0001-11', CnpjValidationRule::format('34406665000111'));
        $this->assertEquals('34.406.665/0001-11', CnpjValidationRule::format('34.406.665/0001-11'));
    }
}
