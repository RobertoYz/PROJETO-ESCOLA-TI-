<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Carbon\Carbon;

class PdfExtractorService
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extrai o texto limpo de um arquivo PDF local ou URL remota.
     */
    public function extrairTexto(?string $pdfPathOrUrl): string
    {
        if (empty($pdfPathOrUrl)) {
            return '';
        }

        try {
            // Se for uma URL HTTP/HTTPS, baixa o conteúdo em memória
            if (str_starts_with($pdfPathOrUrl, 'http://') || str_starts_with($pdfPathOrUrl, 'https://')) {
                $content = @file_get_contents($pdfPathOrUrl);
                if (!$content) {
                    return '';
                }
                $pdf = $this->parser->parseContent($content);
            } else {
                if (!file_exists($pdfPathOrUrl)) {
                    return '';
                }
                $pdf = $this->parser->parseFile($pdfPathOrUrl);
            }

            $textoBruto = $pdf->getText();
            return trim(preg_replace('/\s+/', ' ', $textoBruto));

        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Extrai deterministicamente (Custo Zero de IA) dados de um PDF de edital.
     */
    public function extrairDadosEstruturados(string $textoPdf): array
    {
        $deadline    = $this->extrairDataLimite($textoPdf);
        $budget      = $this->extrairOrcamentoMaximo($textoPdf);
        $faturamento = $this->extrairFaturamento($textoPdf);

        return [
            'deadline'    => $deadline,
            'max_budget'  => $budget,
            'faturamento' => $faturamento,
            'resumo'      => mb_substr($textoPdf, 0, 1200)
        ];
    }

    private function extrairDataLimite(string $texto): ?string
    {
        if (preg_match('/(?:data-limite|data limite|prazo limite|inscriç(?:ões|ao)|recebimento de propostas)[^:0-9]*:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $texto, $matches)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $matches[1])->format('Y-m-d');
            } catch (\Throwable $e) {}
        }
        return null;
    }

    private function extrairOrcamentoMaximo(string $texto): ?float
    {
        if (preg_match('/(?:R\$\s*|valor total de\s*|orçamento de\s*)([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/i', $texto, $matches)) {
            $valorStr = str_replace(['.', ','], ['', '.'], $matches[1]);
            return (float) $valorStr;
        }
        return null;
    }

    private function extrairFaturamento(string $texto): ?string
    {
        if (preg_match('/(?:receita|faturamento)[^.]*(?:R\$\s*|até\s*)([0-9.,]+\s*(?:milhões|mi|mil)?)/i', $texto, $matches)) {
            return trim($matches[0]);
        }
        return null;
    }
}
