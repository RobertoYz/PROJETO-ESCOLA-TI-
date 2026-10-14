<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnpjValidationRule implements ValidationRule
{
    /**
     * Valida o atributo conforme o algoritmo oficial de dígitos verificadores da Receita Federal.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !is_numeric($value)) {
            $fail('O campo :attribute deve ser um CNPJ válido.');
            return;
        }

        if (!self::isValid((string)$value)) {
            $fail('O CNPJ informado é inválido.');
        }
    }

    /**
     * Remove caracteres não numéricos.
     */
    public static function sanitize(?string $cnpj): string
    {
        if ($cnpj === null) {
            return '';
        }
        return preg_replace('/\D/', '', $cnpj);
    }

    /**
     * Formata uma string de 14 dígitos no padrão 00.000.000/0001-00.
     */
    public static function format(string $cnpj): string
    {
        $digits = self::sanitize($cnpj);
        if (strlen($digits) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    /**
     * Valida a integridade matemática do CNPJ.
     */
    public static function isValid(string $cnpj): bool
    {
        $digits = self::sanitize($cnpj);

        // Deve conter exatamente 14 dígitos
        if (strlen($digits) !== 14) {
            return false;
        }

        // Rejeita sequências repetidas conhecidas (00000000000000, 11111111111111, etc.)
        if (preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        // Validação do 1º dígito verificador
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum1 = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum1 += (int)$digits[$i] * $weights1[$i];
        }

        $remainder1 = $sum1 % 11;
        $digit1 = $remainder1 < 2 ? 0 : 11 - $remainder1;

        if ((int)$digits[12] !== $digit1) {
            return false;
        }

        // Validação do 2º dígito verificador
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum2 = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum2 += (int)$digits[$i] * $weights2[$i];
        }

        $remainder2 = $sum2 % 11;
        $digit2 = $remainder2 < 2 ? 0 : 11 - $remainder2;

        return (int)$digits[13] === $digit2;
    }
}
