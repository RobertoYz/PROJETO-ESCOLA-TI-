<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    public function analyzeEdital(string $titulo, string $objetivo, string $publico): ?array
    {
        $prompt = "Você é um especialista em editais de inovação da FINEP. Analise o seguinte edital:\n\n";
        $prompt .= "TÍTULO: $titulo\n";
        $prompt .= "PÚBLICO-ALVO: $publico\n";
        $prompt .= "OBJETIVO/DESCRIÇÃO: $objetivo\n\n";
        $prompt .= "Extraia e retorne EXATAMENTE um JSON válido com a seguinte estrutura (não retorne nenhum texto além do JSON, sem formatação markdown, apenas o JSON puro):\n";
        $prompt .= "{\n";
        $prompt .= '  "trl": "Nível de TRL exigido (ex: TRL 3 a 5). Se não encontrar expressamente no texto, retorne \'A definir\'",'."\n";
        $prompt .= '  "nicho": "A área de atuação ou nicho tecnológico (ex: Agronegócio, TI, Saúde). Se não for claro, retorne \'Inovação\'",'."\n";
        $prompt .= '  "faturamento": "Faturamento ou porte exigido (ex: Até R$ 16 milhões). Se a informação NÃO ESTIVER no texto, retorne EXATAMENTE \'Não especificado\'",'."\n";
        $prompt .= '  "match": 85, (um número inteiro de 0 a 100 indicando a compatibilidade provável com startups de tecnologia)'."\n";
        $prompt .= '  "diagnostico": ['."\n";
        $prompt .= '    { "type": "success", "text": "Ponto forte do edital" },'."\n";
        $prompt .= '    { "type": "warning", "text": "Ponto de atenção ou gargalo" },'."\n";
        $prompt .= '    { "type": "danger", "text": "Risco ou restrição crítica" }'."\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        $systemPrompt = 'Você é um analisador técnico de editais rigoroso. SUA REGRA PRINCIPAL: NÃO INVENTE NENHUMA INFORMAÇÃO (Zero Alucinação). Se uma informação não estiver EXPLICITAMENTE escrita no texto fornecido, você DEVE retornar "Não especificado" ou "A definir". Baseie-se APENAS no texto fornecido. Responda EXCLUSIVAMENTE em formato JSON puro.';

        // Tentativa 1: DeepSeek
        $result = $this->callDeepSeek($systemPrompt, $prompt);

        // Tentativa 2 (Fallback): Gemini
        if (!$result) {
            Log::warning('DeepSeek falhou. Acionando Fallback para Google Gemini...');
            $result = $this->callGemini($systemPrompt, $prompt);
        }

        return $result;
    }

    private function callDeepSeek(string $systemPrompt, string $prompt): ?array
    {
        $apiKey = env('DEEPSEEK_API_KEY');
        if (!$apiKey) {
            Log::warning('DeepSeekService: DEEPSEEK_API_KEY não configurada.');
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post('https://api.deepseek.com/chat/completions', [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                
                $content = trim(preg_replace('/^```json|```$/im', '', $content));
                return json_decode($content, true);
            }

            Log::error('DeepSeek: Erro na API.', ['status' => $response->status(), 'body' => $response->body()]);
            return null;

        } catch (\Exception $e) {
            Log::error('DeepSeek: Exceção.', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function callGemini(string $systemPrompt, string $prompt): ?array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            Log::error('Gemini: GEMINI_API_KEY não configurada.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemPrompt]
                    ]
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.1
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                $content = trim(preg_replace('/^```json|```$/im', '', $content));
                return json_decode($content, true);
            }

            Log::error('Gemini: Erro na API.', ['status' => $response->status(), 'body' => $response->body()]);
            return null;

        } catch (\Exception $e) {
            Log::error('Gemini: Exceção.', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
