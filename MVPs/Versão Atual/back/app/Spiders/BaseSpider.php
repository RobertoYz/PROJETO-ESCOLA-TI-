<?php

namespace App\Spiders;

use RoachPHP\Spider\BasicSpider;
use App\Spiders\Processors\SalvarNoBancoProcessor;
use Carbon\Carbon;
use Illuminate\Support\Str;

abstract class BaseSpider extends BasicSpider
{
    public int $concurrency = 1;

    public array $itemProcessors = [
        SalvarNoBancoProcessor::class,
    ];

    /**
     * Limpa espaços em branco excessivos e quebras de linha de uma string.
     */
    protected function limparTexto(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }
        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Tenta extrair e converter uma data no formato DD/MM/YYYY para Y-m-d (MySQL).
     */
    protected function parseDateBr(?string $texto): ?string
    {
        if (empty($texto)) {
            return null;
        }

        if (preg_match('/([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $texto, $matches)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $matches[1])->format('Y-m-d');
            } catch (\Exception $e) {
                // Ignore e tenta fallback
            }
        }

        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Gera um HASH MD5 único para o edital com base nos parâmetros fornecidos.
     */
    protected function gerarHashUnico(string ...$partes): string
    {
        return md5(implode('|', array_map('trim', $partes)));
    }
}
