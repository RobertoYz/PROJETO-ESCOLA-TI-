<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Edital;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class SyncFinepDetalhes extends Command
{
    protected $signature = 'finep:sync-detalhes {--limit= : Quantidade de editais para processar}';
    protected $description = 'Sincroniza os detalhes (documentos, regiao, nicho) de cada edital raspando a pagina HTML';

    public function handle()
    {
        $limit = $this->option('limit');
        
        // Pega editais que já tem URL de detalhes, mas ainda não tem documentos mapeados
        $query = Edital::whereNull('documentos')
            ->whereNotNull('source_url')
            ->where('source_url', 'like', '%/e/chamada-publica/%');
        
        if ($limit) {
            $query->limit($limit);
        }

        $editais = $query->get();

        if ($editais->isEmpty()) {
            $this->info("Nenhum edital pendente de sincronização de detalhes.");
            return;
        }

        $this->info("Encontrados " . $editais->count() . " editais para sincronizar detalhes.");

        $bar = $this->output->createProgressBar($editais->count());

        foreach ($editais as $edital) {
            try {
                // Desativa verificação SSL e define timeout para evitar travamentos longos
                $response = Http::withoutVerifying()->timeout(15)->get($edital->source_url);
                
                if ($response->successful()) {
                    $html = $response->body();
                    $crawler = new Crawler($html);
                    
                    $documentos = [];
                    
                    // Procuramos por links comuns do Liferay (geralmente em /documents/ ou terminados em ext)
                    $crawler->filter('a')->each(function (Crawler $node) use (&$documentos) {
                        $href = $node->attr('href');
                        if ($href && (
                            str_contains(strtolower($href), '/documents/') || 
                            str_contains(strtolower($href), '/c/document_library/') ||
                            preg_match('/\.(pdf|doc|docx|csv|xls|xlsx|odt|zip)$/i', $href)
                        )) {
                            
                            if (str_starts_with($href, '/')) {
                                $href = 'https://finep.gov.br' . $href;
                            }
                            
                            $texto = trim($node->text());
                            // Se o link for só um ícone, tenta pegar o texto do elemento pai ou da linha
                            if (empty($texto)) {
                                try {
                                    $texto = trim($node->closest('tr')->filter('td')->eq(1)->text());
                                } catch (\Exception $e) {
                                    $texto = 'Anexo';
                                }
                            }
                            
                            // Evita duplicados exatos
                            if (!collect($documentos)->contains('link', $href)) {
                                $documentos[] = [
                                    'titulo' => $texto ?: 'Anexo',
                                    'link' => $href,
                                ];
                            }
                        }
                    });

                    // Estratégia em Cascata (Fallback)
                    $regiao = 'Nacional';
                    $publicoAlvo = null;
                    
                    // Tentativa 1: Padrão "BRICs" (Painel Lateral / Textos padronizados com 'Chave: Valor')
                    if (preg_match('/Região:[\s\n]*([^\<]+)/i', $html, $matches)) {
                        $regiao = trim($matches[1]);
                    }
                    
                    // Tentativa 2: Padrão "Centelha" (Textos contínuos com subtítulos em negrito ou quebras)
                    if (preg_match('/Perfil:\s*(.*?)(?=(Objetivo:|Fonte de Recursos:|<br>|<\/p>))/is', $html, $matches)) {
                        $publicoAlvo = trim(strip_tags($matches[1]));
                    }
                    
                    if (!$publicoAlvo && preg_match('/Público-alvo:\s*([^\<]+)/i', $html, $matches)) {
                        $publicoAlvo = trim($matches[1]);
                    }

                    $updateData = [
                        'documentos' => $documentos,
                        'regiao' => $regiao,
                    ];
                    
                    if ($publicoAlvo) {
                        $updateData['publico'] = $publicoAlvo;
                    }

                    $edital->update($updateData);
                }
            } catch (\Exception $e) {
                // Ignora erro de um edital especifico e continua
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sincronização concluída com sucesso!");
    }
}
