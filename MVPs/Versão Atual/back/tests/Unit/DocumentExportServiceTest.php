<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Minuta;
use App\Services\DocumentExportService;
use ZipArchive;

class DocumentExportServiceTest extends TestCase
{
    private DocumentExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentExportService();
    }

    /**
     * RF-32 & ADR 012: Testa se a exportação gera um arquivo .docx fisicamente válido.
     */
    public function test_exporta_docx_gera_arquivo_fisico_valido(): void
    {
        $minuta = new Minuta();
        $minuta->id = 999;
        $minuta->titulo_projeto = 'Plataforma de Inteligência em Editais Nexus';
        $minuta->autor_nome = 'Consultoria Modelo';
        $minuta->versao = 1;
        $minuta->secoes_estruturadas = [
            'identificacao_proponente' => 'Dados da empresa proponente e CNPJ.',
            'caracterizacao_problema' => 'Dificuldade de startups acessarem subvenção pública.',
            'solucao_proposta' => 'Sistema autônomo com spiders e match inteligente.',
            'diferencial_inovacao' => 'Arquitetura híbrida de custo zero de IA.',
            'modelo_negocios' => 'SaaS B2B para consultorias de inovação.',
            'estagio_desenvolvimento' => 'TRL 7 - Protótipo validado em ambiente operacional.',
            'objeto_objetivos' => 'Desenvolver e homologar a esteira de editais.',
            'metodologia_plano_trabalho' => 'Metodologia ágil estruturada em sprints.',
            'metas_entregaveis' => 'Meta 1: Spiders ativos; Meta 2: Kanban funcional.',
            'matriz_riscos' => 'Risco: Queda de portais públicos -> Mitigação: Fallback multinível.',
            'plano_aplicacao_recursos' => 'Total R$ 300.000,00 com contrapartida de 10%.'
        ];

        $arquivoGerado = $this->service->exportarDocx($minuta);

        $this->assertFileExists($arquivoGerado);
        $this->assertStringEndsWith('.docx', $arquivoGerado);
        $this->assertGreaterThan(0, filesize($arquivoGerado));

        // Valida que o arquivo é um pacote ZIP válido (especificação OpenXML)
        $zip = new ZipArchive();
        $abertura = $zip->open($arquivoGerado);
        $this->assertTrue($abertura === true, 'O arquivo .docx deve ser um pacote ZIP válido.');

        // Valida a presença dos arquivos fundamentais da especificação ISO/IEC 29500
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'), 'Deve conter [Content_Types].xml');
        $this->assertNotFalse($zip->locateName('_rels/.rels'), 'Deve conter _rels/.rels');
        $this->assertNotFalse($zip->locateName('word/document.xml'), 'Deve conter word/document.xml');

        // Valida que o conteúdo XML possui o título do projeto e seções geradas
        $documentXml = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('Plataforma de Inteligência em Editais Nexus', $documentXml);
        $this->assertStringContainsString('Desenvolver e homologar a esteira de editais.', $documentXml);
        $this->assertStringContainsString('Metodologia ágil estruturada em sprints.', $documentXml);

        $zip->close();

        // Limpeza do arquivo temporário gerado no teste
        if (file_exists($arquivoGerado)) {
            unlink($arquivoGerado);
        }
    }
}
