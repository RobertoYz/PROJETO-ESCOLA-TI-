<?php

namespace App\Services;

use App\Models\Minuta;

class DocumentExportService
{
    /**
     * RF-32: Exporta a proposta estruturada no formato Microsoft Word (.docx)
     * Utiliza a estrutura padrão OpenXML/WordprocessingML nativa via ZipArchive,
     * garantindo 100% de compatibilidade sem demandar extensões externas pesadas.
     */
    public function exportarDocx(Minuta $minuta): string
    {
        $tempPath = storage_path('app/minuta_' . $minuta->id . '_' . time() . '.docx');

        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Não foi possível inicializar o arquivo .docx');
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // Monta o XML do documento Word
        $documentXml = $this->gerarWordXml($minuta);
        $zip->addFromString('word/document.xml', $documentXml);

        $zip->close();

        return $tempPath;
    }

    /**
     * RF-32.1: Exporta a proposta estruturada em formato PDF amigável para impressão institucional
     */
    public function gerarHtmlParaPdf(Minuta $minuta): string
    {
        $titulo = htmlspecialchars($minuta->titulo_projeto ?: 'Plano de Trabalho - Minuta de Inovação');
        $startup = $minuta->startup;
        $edital = $minuta->edital;

        $secoes = $minuta->secoes_estruturadas ?: [];
        $conteudo = $minuta->conteudo_gerado;

        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>' . $titulo . '</title>
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #222; line-height: 1.6; font-size: 11pt; }
        .cabecalho { border-bottom: 2px solid #0056b3; padding-bottom: 12px; margin-bottom: 25px; }
        .cabecalho h1 { color: #0056b3; font-size: 16pt; margin: 0 0 6px 0; text-transform: uppercase; }
        .cabecalho .sub { font-size: 10pt; color: #555; }
        .box-info { background: #f8f9fa; border-left: 4px solid #0056b3; padding: 12px; margin-bottom: 20px; font-size: 9.5pt; }
        .box-info table { width: 100%; border-collapse: collapse; }
        .box-info td { padding: 4px 6px; }
        .secao { margin-bottom: 22px; page-break-inside: avoid; }
        .secao h2 { background: #e9ecef; color: #1c3d5a; font-size: 11.5pt; padding: 6px 10px; margin: 0 0 10px 0; border-radius: 4px; border-left: 3px solid #1c3d5a; }
        .secao p { margin: 0 0 10px 0; text-align: justify; white-space: pre-line; }
        .rodape { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 10px; font-size: 8pt; color: #777; text-align: center; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="cabecalho">
        <h1>Plano de Trabalho / Minuta de Inovação</h1>
        <div class="sub">Edital de Fomento: <strong>' . htmlspecialchars($edital->title ?? 'Edital de Inovação') . ' (' . htmlspecialchars($edital->fonte ?? 'Órgão de Fomento') . ')</strong></div>
    </div>

    <div class="box-info">
        <table>
            <tr>
                <td><strong>Proponente:</strong> ' . htmlspecialchars($startup->razao_social ?? 'Não informado') . '</td>
                <td><strong>CNPJ:</strong> ' . htmlspecialchars($startup->cnpj ?? 'Não informado') . '</td>
            </tr>
            <tr>
                <td><strong>Porte / Nicho:</strong> ' . htmlspecialchars(($startup->porte ?? '-') . ' / ' . ($startup->nicho_atuacao ?? '-')) . '</td>
                <td><strong>Versão da Minuta:</strong> v' . $minuta->versao . ' (Auditada em ' . $minuta->created_at->format('d/m/Y H:i') . ')</td>
            </tr>
        </table>
    </div>';

        $nomesSecoes = [
            'identificacao' => '1. Identificação da Empresa Proponente',
            'enquadramento_ods' => '2. Enquadramento Temático e Alinhamento com ODS',
            'resumo_executivo' => '3. Resumo Executivo (Pitch da Proposta)',
            'problema_justificativa' => '4. Problema, Dor de Mercado e Justificativa',
            'objeto_objetivos' => '5. Objeto do Projeto e Objetivos Específicos',
            'grau_inovacao_trl' => '6. Grau de Inovação e Maturidade Tecnológica (TRL)',
            'metas_entregaveis' => '7. Metas a serem Atingidas e Entregáveis',
            'metodologia_plano_trabalho' => '8. Metodologia e Plano de Trabalho Sintético',
            'plano_aplicacao_recursos' => '9. Recursos Solicitados, Contrapartida e Orçamento',
            'matriz_riscos' => '10. Matriz de Riscos e Contingenciamento',
            'impactos_socioeconomicos' => '11. Impactos Socioeconômicos e Ambientais'
        ];

        if (!empty($secoes)) {
            foreach ($nomesSecoes as $chave => $rotulo) {
                if (!empty($secoes[$chave])) {
                    $html .= '<div class="secao">
                        <h2>' . $rotulo . '</h2>
                        <p>' . htmlspecialchars($secoes[$chave]) . '</p>
                    </div>';
                }
            }
        } else {
            $html .= '<div class="secao">
                <h2>Conteúdo Integral da Proposta</h2>
                <p>' . htmlspecialchars($conteudo) . '</p>
            </div>';
        }

        $html .= '<div class="rodape">
            Documento gerado automaticamente pelo Módulo de IA (EDTAN-122). Auditado em ' . date('d/m/Y H:i:s') . ' por ' . htmlspecialchars($minuta->autor_nome ?? 'Consultor Autorizado') . '.
        </div>
</body>
</html>';

        return $html;
    }

    private function gerarWordXml(Minuta $minuta): string
    {
        $titulo = htmlspecialchars($minuta->titulo_projeto ?: 'Plano de Trabalho - Minuta de Inovação', ENT_XML1);
        $startup = $minuta->startup;
        $edital = $minuta->edital;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>';

        // Título Principal
        $xml .= '<w:p><w:pPr><w:jc w:val="center"/><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="4" w:color="0056B3"/></w:pBdr></w:pPr>'
            . '<w:r><w:rPr><w:b/><w:color w:val="0056B3"/><w:sz w:val="32"/></w:rPr><w:t>' . $titulo . '</w:t></w:r></w:p>';

        // Subtítulo
        $editalTxt = htmlspecialchars("Edital: " . ($edital->title ?? '') . " | Proponente: " . ($startup->razao_social ?? ''), ENT_XML1);
        $xml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="300"/></w:pPr>'
            . '<w:r><w:rPr><w:i/><w:color w:val="555555"/><w:sz w:val="20"/></w:rPr><w:t>' . $editalTxt . '</w:t></w:r></w:p>';

        $secoes = $minuta->secoes_estruturadas ?: [];
        $nomesSecoes = [
            'identificacao' => '1. IDENTIFICAÇÃO DA EMPRESA PROPONENTE',
            'enquadramento_ods' => '2. ENQUADRAMENTO TEMÁTICO E ALINHAMENTO COM ODS',
            'resumo_executivo' => '3. RESUMO EXECUTIVO (PITCH DA PROPOSTA)',
            'problema_justificativa' => '4. PROBLEMA, DOR DE MERCADO E JUSTIFICATIVA',
            'objeto_objetivos' => '5. OBJETO DO PROJETO E OBJETIVOS ESPECÍFICOS',
            'grau_inovacao_trl' => '6. GRAU DE INOVAÇÃO E MATURIDADE TECNOLÓGICA (TRL)',
            'metas_entregaveis' => '7. METAS A SEREM ATINGIDAS E PRODUTOS ENTREGÁVEIS',
            'metodologia_plano_trabalho' => '8. METODOLOGIA E PLANO DE TRABALHO SINTÉTICO',
            'plano_aplicacao_recursos' => '9. RECURSOS SOLICITADOS, CONTRAPARTIDA E ORÇAMENTO',
            'matriz_riscos' => '10. MATRIZ DE RISCOS E PLANO DE CONTINGENCIAMENTO',
            'impactos_socioeconomicos' => '11. IMPACTOS SOCIOECONÔMICOS E AMBIENTAIS'
        ];

        foreach ($nomesSecoes as $chave => $rotulo) {
            $conteudo = $secoes[$chave] ?? '';
            if (empty($conteudo)) continue;

            // Cabeçalho da Seção
            $xml .= '<w:p><w:pPr><w:spacing w:before="240" w:after="80"/><w:shd w:val="clear" w:color="auto" w:fill="F0F4F8"/></w:pPr>'
                . '<w:r><w:rPr><w:b/><w:color w:val="1C3D5A"/><w:sz w:val="24"/></w:rPr><w:t>' . htmlspecialchars($rotulo, ENT_XML1) . '</w:t></w:r></w:p>';

            // Linhas do Conteúdo
            $linhas = explode("\n", $conteudo);
            foreach ($linhas as $linha) {
                if (trim($linha) === '') continue;
                $xml .= '<w:p><w:pPr><w:spacing w:after="80"/></w:pPr>'
                    . '<w:r><w:rPr><w:sz w:val="22"/><w:rFonts w:ascii="Segoe UI" w:hAnsi="Segoe UI"/></w:rPr><w:t>' . htmlspecialchars($linha, ENT_XML1) . '</w:t></w:r></w:p>';
            }
        }

        // Rodapé de Auditoria
        $auditTxt = htmlspecialchars("Documento gerado em " . date('d/m/Y H:i') . " via Módulo de IA. Autor: " . ($minuta->autor_nome ?: 'Consultor'), ENT_XML1);
        $xml .= '<w:p><w:pPr><w:spacing w:before="400"/><w:jc w:val="center"/></w:pPr>'
            . '<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="888888"/></w:rPr><w:t>' . $auditTxt . '</w:t></w:r></w:p>';

        $xml .= '</w:body></w:document>';

        return $xml;
    }
}
