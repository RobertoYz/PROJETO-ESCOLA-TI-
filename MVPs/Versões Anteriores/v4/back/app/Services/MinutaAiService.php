<?php

namespace App\Services;

use App\Models\Startup;
use App\Models\Edital;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MinutaAiService
{
    /**
     * Gera a Minuta Técnica baseada no Modelo Oficial de Fomento (Anjo Inovador / FINEP / FAPESC)
     */
    public function gerarMinuta(Startup $startup, Edital $edital, string $ideiaProjeto): array
    {
        $dadosContexto = $this->prepararContexto($startup, $edital, $ideiaProjeto);
        $prompt = $this->montarPromptMinuta($dadosContexto);

        $systemPrompt = "Você é um especialista sênior em elaboração de projetos e propostas técnicas para editais de subvenção econômica e inovação (FINEP, FAPESC, SEIA Anjo Inovador). "
            . "Sua função é gerar um Plano de Trabalho formal, altamente convincente e tecnicamente rigoroso, pronto para submissão oficial. "
            . "Siga estritamente a estrutura solicitada. NÃO use placeholders genéricos quando houver dados fornecidos. "
            . "Retorne EXCLUSIVAMENTE um objeto JSON válido, sem blocos de markdown adicionais ao redor.";

        // Cascata de Fallback: 1. Gemini -> 2. DeepSeek -> 3. Groq -> 4. Geração Determinística Segura
        $resultado = $this->callGemini($systemPrompt, $prompt);
        $modeloUsado = 'Google Gemini (gemini-2.5-flash)';

        if (!$resultado) {
            Log::warning('MinutaAiService: Gemini falhou. Acionando fallback para DeepSeek...');
            $resultado = $this->callDeepSeek($systemPrompt, $prompt);
            $modeloUsado = 'DeepSeek (deepseek-chat)';
        }

        if (!$resultado) {
            Log::warning('MinutaAiService: DeepSeek falhou. Acionando fallback para Groq...');
            $resultado = $this->callGroq($systemPrompt, $prompt);
            $modeloUsado = 'Groq (Llama-3)';
        }

        if (!$resultado) {
            Log::warning('MinutaAiService: Todas as APIs externas indisponíveis. Gerando Minuta Estruturada local...');
            $resultado = $this->gerarMinutaFallback($startup, $edital, $ideiaProjeto);
            $modeloUsado = 'Motor Determinístico Local (Fallback Seguro)';
        }

        return [
            'modelo_usado' => $modeloUsado,
            'secoes' => $resultado['secoes'] ?? [],
            'titulo_projeto' => $resultado['titulo_projeto'] ?? ($startup->nome_fantasia . ' - ' . $edital->title),
            'conteudo_formatado' => $this->formatarTextoCompleto($resultado['secoes'] ?? [], $startup, $edital)
        ];
    }

    /**
     * RF-47: Calcula o Match Inteligente e a justificativa semântica de compatibilidade
     */
    public function calcularMatchEditais(Startup $startup, $editais): array
    {
        $listaFormatada = [];

        foreach ($editais as $edital) {
            $score = 50;
            $justificativas = [];
            $pontosFortes = [];
            $pontosAtencao = [];

            // Análise 1: Nicho de Atuação
            $nichoStartup = mb_strtolower($startup->nicho_atuacao ?? '');
            $nichoEdital = mb_strtolower($edital->ai_nicho ?? $edital->objetivo ?? '');
            
            if (!empty($nichoStartup) && (str_contains($nichoEdital, $nichoStartup) || str_contains($nichoStartup, 'tecnologia') || str_contains($nichoStartup, 'software'))) {
                $score += 20;
                $pontosFortes[] = "Aderência direta entre o nicho da startup ({$startup->nicho_atuacao}) e as temáticas prioritárias do edital.";
            } else {
                $pontosAtencao[] = "Nicho da startup pode demandar enquadramento transdisciplinar na temática do edital.";
            }

            // Análise 2: Porte Empresarial
            $porte = mb_strtolower($startup->porte ?? '');
            $publico = mb_strtolower($edital->publico ?? '');
            if (str_contains($publico, 'startup') || str_contains($publico, 'micro') || str_contains($publico, 'pequena') || str_contains($publico, 'mei')) {
                $score += 15;
                $pontosFortes[] = "Porte da empresa ({$startup->porte}) é plenamente elegível às regras de subvenção.";
            } else {
                $score += 5;
            }

            // Análise 3: Teto Orçamentário e Capacidade
            if ($edital->max_budget > 0) {
                $score += 10;
                $pontosFortes[] = "Teto orçamentário definido (R$ " . number_format($edital->max_budget, 2, ',', '.') . ") compatível com o plano de aplicação.";
            }

            $score = min(98, max(45, $score));

            $listaFormatada[] = [
                'edital_id' => $edital->id,
                'titulo' => $edital->title,
                'fonte' => $edital->fonte,
                'match_percentual' => $score,
                'prazo' => $edital->deadline ? date('d/m/Y', strtotime($edital->deadline)) : 'Fluxo Contínuo / A Definir',
                'orcamento_maximo' => $edital->max_budget,
                'justificativa_fit' => implode(' ', $pontosFortes) . ' ' . implode(' ', $pontosAtencao),
                'pontos_fortes' => $pontosFortes,
                'pontos_atencao' => $pontosAtencao
            ];
        }

        // Ordena do maior match para o menor
        usort($listaFormatada, fn($a, $b) => $b['match_percentual'] <=> $a['match_percentual']);

        return $listaFormatada;
    }

    private function prepararContexto(Startup $startup, Edital $edital, string $ideiaProjeto): array
    {
        return [
            'startup' => [
                'razao_social' => $startup->razao_social,
                'nome_fantasia' => $startup->nome_fantasia,
                'cnpj' => $startup->cnpj,
                'porte' => $startup->porte,
                'regime_tributario' => $startup->regime_tributario,
                'nicho_atuacao' => $startup->nicho_atuacao,
                'cidade_estado' => ($startup->cidade ? $startup->cidade . ' - ' . $startup->estado : 'Não informado'),
                'pitch' => $startup->pitch ?? 'Desenvolvimento de soluções tecnológicas inovadoras.'
            ],
            'edital' => [
                'titulo' => $edital->title,
                'fonte' => $edital->fonte,
                'objetivo' => $edital->objetivo ?? $edital->conteudo_completo ?? '',
                'publico' => $edital->publico ?? 'Startups e Microempresas',
                'teto_orcamento' => $edital->max_budget ? 'R$ ' . number_format($edital->max_budget, 2, ',', '.') : 'Conforme Edital',
                'documentos_exigidos' => $edital->documentos_exigidos ?? []
            ],
            'ideia_projeto' => $ideiaProjeto
        ];
    }

    private function montarPromptMinuta(array $ctx): string
    {
        return "Elabore um Plano de Trabalho / Minuta de Proposta de Inovação oficial seguindo rigorosamente o modelo institucional de editais (baseado no padrão SEIA Anjo Inovador e FINEP).\n\n"
            . "DADOS DA EMPRESA PROPONENTE:\n"
            . "- Razão Social: {$ctx['startup']['razao_social']}\n"
            . "- Nome Fantasia: {$ctx['startup']['nome_fantasia']}\n"
            . "- CNPJ: {$ctx['startup']['cnpj']}\n"
            . "- Porte / Regime: {$ctx['startup']['porte']} / {$ctx['startup']['regime_tributario']}\n"
            . "- Nicho: {$ctx['startup']['nicho_atuacao']}\n"
            . "- Localização: {$ctx['startup']['cidade_estado']}\n"
            . "- Pitch da Startup: {$ctx['startup']['pitch']}\n\n"
            . "DADOS DO EDITAL:\n"
            . "- Título: {$ctx['edital']['titulo']}\n"
            . "- Órgão Fomentador: {$ctx['edital']['fonte']}\n"
            . "- Teto de Recursos: {$ctx['edital']['teto_orcamento']}\n"
            . "- Diretrizes / Objetivo do Edital: " . substr($ctx['edital']['objetivo'], 0, 1500) . "\n\n"
            . "PROPOSTA / IDEIA DE PROJETO SUBMETIDA PELO CONSULTOR:\n"
            . "{$ctx['ideia_projeto']}\n\n"
            . "INSTRUÇÕES E ESTRUTURA REQUERIDA:\n"
            . "Gere um JSON com o campo 'titulo_projeto' e um array associativo 'secoes' contendo exatamente as seguintes chaves:\n"
            . "{\n"
            . "  \"titulo_projeto\": \"Título comercial e técnico do projeto\",\n"
            . "  \"secoes\": {\n"
            . "    \"identificacao\": \"Texto formatado com dados da empresa proponente e representação\",\n"
            . "    \"enquadramento_ods\": \"Enquadramento nas temáticas do edital e nos Objetivos de Desenvolvimento Sustentável (ODS)\",\n"
            . "    \"resumo_executivo\": \"Pitch estruturado e síntese da proposta inovadora\",\n"
            . "    \"problema_justificativa\": \"Diagnóstico do problema de mercado, relevância setorial e justificativa do fomento público\",\n"
            . "    \"objeto_objetivos\": \"Objeto geral e 3 a 5 objetivos específicos claros\",\n"
            . "    \"grau_inovacao_trl\": \"Diferencial competitivo e maturidade tecnológica (nível de TRL inicial e estimado ao fim do projeto)\",\n"
            . "    \"metas_entregaveis\": \"Metas quantitativas e entregáveis verificáveis\",\n"
            . "    \"metodologia_plano_trabalho\": \"Metodologia de execução, fases do projeto e cronograma macro de 12 meses\",\n"
            . "    \"plano_aplicacao_recursos\": \"Estimativa orçamentária de Custeio e Investimento com contrapartida de 5%\",\n"
            . "    \"matriz_riscos\": \"Principais riscos técnicos e operacionais mapeados com planos de contingenciamento\",\n"
            . "    \"impactos_socioeconomicos\": \"Impactos socioeconômicos, geração de valor e abrangência regional\"\n"
            . "  }\n"
            . "}";
    }

    private function formatarTextoCompleto(array $secoes, Startup $startup, Edital $edital): string
    {
        $titulo = $secoes['titulo_projeto'] ?? ($startup->nome_fantasia . ' - Proposta ' . $edital->fonte);

        $txt = "======================================================================\n";
        $txt .= "PLANO DE TRABALHO E PROPOSTA TÉCNICA DE INOVAÇÃO\n";
        $txt .= "EDITAL: {$edital->title} ({$edital->fonte})\n";
        $txt .= "PROPONENTE: {$startup->razao_social} (CNPJ: {$startup->cnpj})\n";
        $txt .= "======================================================================\n\n";

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
            $txt .= "----------------------------------------------------------------------\n";
            $txt .= "{$rotulo}\n";
            $txt .= "----------------------------------------------------------------------\n";
            $txt .= ($secoes[$chave] ?? 'Conteúdo em elaboração.') . "\n\n";
        }

        return $txt;
    }

    private function gerarMinutaFallback(Startup $startup, Edital $edital, string $ideiaProjeto): array
    {
        $teto = $edital->max_budget ?: 250000.00;
        $subvencao = $teto;
        $contrapartida = $subvencao * 0.05;
        $totalProjeto = $subvencao + $contrapartida;

        $secoes = [
            'identificacao' => "Razão Social: {$startup->razao_social}\nNome Fantasia: {$startup->nome_fantasia}\nCNPJ: {$startup->cnpj}\nPorte: {$startup->porte} | Regime: {$startup->regime_tributario}\nLocalização: {$startup->cidade} - {$startup->estado}\nContato: {$startup->email} | {$startup->telefone}",
            'enquadramento_ods' => "Área Temática Principal: Cidades Inteligentes, Transformação Digital e Inovação Empresarial.\nODS Vinculados: ODS 8 (Trabalho Decente e Crescimento Econômico), ODS 9 (Indústria, Inovação e Infraestrutura) e ODS 11 (Cidades Sustentáveis).",
            'resumo_executivo' => "O projeto propõe o desenvolvimento e validação da solução baseada em: \"{$ideiaProjeto}\". A proposta alavanca o histórico e competências da empresa {$startup->nome_fantasia} no segmento de {$startup->nicho_atuacao}, endereçando as prioridades de fomento do edital {$edital->title}.",
            'problema_justificativa' => "Gargalo Identificado: O setor carece de soluções escaláveis e integradas, enfrentando custos operacionais elevados e baixa digitalização.\nJustificativa: O apoio público deste edital viabiliza a superação do vale da morte tecnológico e acelera o ciclo de P&D, garantindo que a tecnologia atinja viabilidade comercial em escala.",
            'objeto_objetivos' => "Objeto Geral: Desenvolver, prototipar e validar a plataforma tecnológica descrita no escopo proposto.\nObjetivos Específicos:\n1. Mapear e arquitetar os requisitos técnicos de conformidade;\n2. Implementar os módulos centrais da arquitetura do produto;\n3. Realizar testes controlados de homologação com clientes-piloto;\n4. Consolidar os entregáveis de governança e propriedade intelectual.",
            'grau_inovacao_trl' => "Nível de Maturidade Inicial: TRL 3 / 4 (Prova de conceito / validação em laboratório).\nNível de Maturidade Final Estimado: TRL 7 (Demonstração do protótipo do sistema em ambiente operacional).\nDiferencial: Arquitetura proprietária com integração de inteligência de dados.",
            'metas_entregaveis' => "Meta 1: Documento de Especificação Técnica e Arquitetura homologado (Mês 3).\nMeta 2: Protótipo Funcional (MVP) operacional para testes piloto (Mês 6).\nMeta 3: Validação de campo com métricas de desempenho e usabilidade consolidadas (Mês 10).\nMeta 4: Relatório técnico final de encerramento e prestação de contas (Mês 12).",
            'metodologia_plano_trabalho' => "Metodologia Ágil (Scrum/Kanban) estruturada em 4 fases trimestrais:\n- Fase 1 (Mês 1 a 3): Pesquisa aplicada, modelagem de dados e arquitetura;\n- Fase 2 (Mês 4 a 6): Desenvolvimento dos módulos de inteligência e integração;\n- Fase 3 (Mês 7 a 9): Homologação operacional em ambiente simulado;\n- Fase 4 (Mês 10 a 12): Validação final, refinamento de segurança e prestação de contas.",
            'plano_aplicacao_recursos' => "Subvenção Econômica Solicitada: R$ " . number_format($subvencao, 2, ',', '.') . "\nContrapartida da Empresa (5%): R$ " . number_format($contrapartida, 2, ',', '.') . "\nValor Global do Projeto: R$ " . number_format($totalProjeto, 2, ',', '.') . "\nDistribuição: 75% em Custeio (recursos humanos especializados, licenças, serviços técnicos de terceiros) e 25% em Investimentos/Equipamentos.",
            'matriz_riscos' => "Risco 1: Flutuação no tempo de integração de APIs terceiras -> Mitigação: Uso de arquitetura modular orientada a microsserviços desacoplados.\nRisco 2: Atraso na adesão de usuários nos testes piloto -> Mitigação: Parcerias prévias já prospectadas no ecossistema de inovação.",
            'impactos_socioeconomicos' => "Impactos Econômicos: Aumento de faturamento da empresa e geração estimada de 3 a 5 novos postos de trabalho qualificados em tecnologia.\nImpactos Sociais: Modernização e ganho de eficiência no ecossistema atendido, fomentando a cadeia de inovação regional."
        ];

        return [
            'titulo_projeto' => "Projeto Inovador: " . ($startup->nome_fantasia ?: 'Startup') . " - " . ($edital->title ?: 'Edital de Inovação'),
            'secoes' => $secoes
        ];
    }

    private function getRandomKey(string $envKey): ?string
    {
        $keys = env($envKey);
        if (!$keys) return null;
        $keyArray = array_filter(array_map('trim', explode(',', $keys)));
        return !empty($keyArray) ? $keyArray[array_rand($keyArray)] : null;
    }

    private function callGemini(string $systemPrompt, string $prompt): ?array
    {
        $apiKey = $this->getRandomKey('GEMINI_API_KEY');
        if (!$apiKey) return null;

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(70)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'temperature' => 0.2
                    ]
                ]);

            if ($response->successful()) {
                $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $clean = trim(preg_replace('/^```json|```$/im', '', $content));
                return json_decode($clean, true);
            }
        } catch (\Throwable $e) {
            Log::error('MinutaAiService Gemini Exception: ' . $e->getMessage());
        }

        return null;
    }

    private function callDeepSeek(string $systemPrompt, string $prompt): ?array
    {
        $apiKey = $this->getRandomKey('DEEPSEEK_API_KEY');
        if (!$apiKey) return null;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(70)
                ->post('https://api.deepseek.com/chat/completions', [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                $clean = trim(preg_replace('/^```json|```$/im', '', $content));
                return json_decode($clean, true);
            }
        } catch (\Throwable $e) {
            Log::error('MinutaAiService DeepSeek Exception: ' . $e->getMessage());
        }

        return null;
    }

    private function callGroq(string $systemPrompt, string $prompt): ?array
    {
        $apiKey = $this->getRandomKey('GROQ_API_KEY');
        if (!$apiKey) return null;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(70)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                $clean = trim(preg_replace('/^```json|```$/im', '', $content));
                return json_decode($clean, true);
            }
        } catch (\Throwable $e) {
            Log::error('MinutaAiService Groq Exception: ' . $e->getMessage());
        }

        return null;
    }
}
