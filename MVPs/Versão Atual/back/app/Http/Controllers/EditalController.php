<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Edital;
use App\Models\EditalFavorito;
use App\Models\Startup;

class EditalController extends Controller
{
    /**
     * RF-10 / RF-11: Listagem de editais com identificação dinâmica de favoritos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Edital::query();

        if ($request->filled('fonte') && strtolower($request->fonte) !== 'todos' && strtolower($request->fonte) !== 'favoritos') {
            $query->where('fonte', $request->fonte);
        }

        // Identifica usuário autenticado via Sanctum se houver token
        $usuario = $request->user('sanctum') ?? auth('sanctum')->user();
        $favoritosIds = [];

        if ($usuario) {
            $favQuery = EditalFavorito::where('user_id', $usuario->id);
            if ($request->filled('startup_id')) {
                $favQuery->where(function($q) use ($request) {
                    $q->where('startup_id', $request->startup_id)
                      ->orWhereNull('startup_id');
                });
            }
            $favoritosIds = $favQuery->pluck('edital_id')->toArray();

            // Filtro por apenas favoritados
            if ($request->boolean('apenas_favoritos') || $request->input('favoritos') === '1' || strtolower((string)$request->input('fonte')) === 'favoritos') {
                $query->whereIn('id', $favoritosIds);
            }
        } elseif ($request->boolean('apenas_favoritos') || $request->input('favoritos') === '1' || strtolower((string)$request->input('fonte')) === 'favoritos') {
            // Se pediu apenas favoritos mas não está autenticado
            return response()->json([]);
        }

        $editais = $query->get();

        $startup = null;
        if ($usuario) {
            if ($request->filled('startup_id')) {
                $startup = Startup::where('id', (int)$request->startup_id)
                    ->where('agencia_id', $usuario->agencia_id)
                    ->first();
            }
            if (!$startup && $usuario->agencia_id) {
                $startup = Startup::where('agencia_id', $usuario->agencia_id)->first();
            }
        }
        
        // Formata os dados para a interface Front-end (Custo Zero de IA)
        $formattedEditais = $editais->map(function($edital) use ($favoritosIds, $startup) {
            $diagnostico = !empty($edital->ai_diagnosis) 
                ? $edital->ai_diagnosis 
                : [[ 'type' => 'info', 'text' => 'Edital Cadastrado - Dados Pré-Formatados' ]];

            $matchScore = $this->calcularScoreMatch($edital, $startup);

            $formatarData = function($dataRaw) {
                if (empty($dataRaw)) return '--';
                try {
                    $dt = \Carbon\Carbon::parse($dataRaw);
                    // Se for o epoch padrão 1970 ou ano inválido (< 2000), descarta
                    if ($dt->year < 2000) {
                        return 'Fluxo contínuo';
                    }
                    return $dt->format('d/m/Y') . ' às ' . $dt->format('H:i');
                } catch (\Throwable $e) {
                    return '--';
                }
            };

            $deadlineFormatada = $formatarData($edital->deadline);
            if ($deadlineFormatada === '--' || $deadlineFormatada === 'Fluxo contínuo') {
                $deadlineFormatada = 'Fluxo contínuo';
            }

            return [
                'id' => $edital->id,
                'name' => $edital->title,
                'org' => $edital->fonte ?? 'FINEP',
                'budget' => $edital->max_budget ? 'R$ ' . number_format($edital->max_budget, 2, ',', '.') : 'A definir',
                'deadline' => $deadlineFormatada,
                'status' => 'Aberto',
                'statusClass' => 'badge-aberto',
                'match' => $matchScore,
                'target' => $edital->publico ?? 'Startup / Empresa / Pesquisador',
                'region' => $edital->regiao ?? 'Nacional', 
                'objetivo' => !empty($edital->objetivo) ? $edital->objetivo : (!empty($edital->conteudo_completo) ? mb_substr(strip_tags($edital->conteudo_completo), 0, 1500) . '...' : 'Sem descrição para exibir no resumo.'),
                'documentos' => $edital->documentos ?? [],
                'trl' => $edital->ai_trl ?? 'Geral',
                'nicho' => $edital->nicho ?? 'Multisetorial',
                'faturamento' => $edital->faturamento ?? 'Não especificado',
                'openDate' => $formatarData($edital->open_date),
                'closeDate' => $deadlineFormatada,
                'resultDate' => $formatarData($edital->result_date),
                'url' => $edital->source_url ?? '#',
                'favorite' => in_array($edital->id, $favoritosIds),
                'diagnosis' => $diagnostico
            ];
        });

        return response()->json($formattedEditais);
    }

    /**
     * RF-11: Alterna o estado de favorito de um edital
     * POST /api/editais/{id}/favoritar
     */
    public function toggleFavorito(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        $edital = Edital::findOrFail($id);
        $startupId = $request->input('startup_id') ? (int)$request->input('startup_id') : null;

        $favoritoQuery = EditalFavorito::where('user_id', $usuario->id)
            ->where('edital_id', $edital->id);

        if ($startupId) {
            $favoritoQuery->where(function($q) use ($startupId) {
                $q->where('startup_id', $startupId)->orWhereNull('startup_id');
            });
        }

        $existente = $favoritoQuery->first();

        if ($existente) {
            $existente->delete();
            return response()->json([
                'sucesso' => true,
                'favorito' => false,
                'message' => 'Edital removido dos favoritos com sucesso.'
            ], 200);
        }

        EditalFavorito::create([
            'user_id' => $usuario->id,
            'edital_id' => $edital->id,
            'startup_id' => $startupId
        ]);

        return response()->json([
            'sucesso' => true,
            'favorito' => true,
            'message' => 'Edital salvo nos favoritos com sucesso.'
        ], 200);
    }

    /**
     * RF-11: Lista apenas os editais favoritados do usuário
     * GET /api/editais/favoritos
     */
    public function listarFavoritos(Request $request): JsonResponse
    {
        $request->merge(['apenas_favoritos' => 1]);
        return $this->index($request);
    }

    /**
     * Calcula o percentual de compatibilidade (Match) em PHP com custo zero de IA.
     */
    private function calcularScoreMatch(Edital $edital, ?Startup $startup): int
    {
        if (!empty($edital->ai_match) && (int) $edital->ai_match > 0) {
            return min(99, max(1, (int) $edital->ai_match));
        }

        if (!$startup) {
            return 0;
        }

        $score = 42;

        // 1. Aderência por palavras-chave de nicho e pitch da startup vs título/objetivo do edital
        $textoEdital = mb_strtolower(trim(
            ($edital->title ?? '') . ' ' .
            ($edital->objetivo ?? '') . ' ' .
            ($edital->ai_nicho ?? '') . ' ' .
            ($edital->nicho ?? '') . ' ' .
            ($edital->conteudo_completo ?? '')
        ));

        $textoStartup = mb_strtolower(trim(($startup->nicho_atuacao ?? '') . ' ' . ($startup->pitch ?? '')));
        $palavras = preg_split('/[\s,;.\-\/()]+/u', $textoStartup, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['para', 'com', 'como', 'uma', 'uns', 'das', 'dos', 'nos', 'nas', 'por', 'que', 'mais', 'sobre', 'entre', 'empresa', 'startup'];

        $acertos = 0;
        $verificadas = [];
        foreach ($palavras as $palavra) {
            if (mb_strlen($palavra) < 4 || in_array($palavra, $stopWords, true) || isset($verificadas[$palavra])) {
                continue;
            }
            $verificadas[$palavra] = true;
            if (str_contains($textoEdital, $palavra)) {
                $acertos++;
                if ($acertos >= 6) {
                    break;
                }
            }
        }
        $score += min(32, $acertos * 6);

        // 2. Porte empresarial vs público-alvo do edital
        $publico = mb_strtolower($edital->publico ?? '');
        $porte = mb_strtolower($startup->porte ?? '');
        if (
            str_contains($publico, 'startup') ||
            str_contains($publico, 'empresa') ||
            (!empty($porte) && str_contains($publico, $porte))
        ) {
            $score += 12;
        } elseif (str_contains($publico, 'micro') || str_contains($publico, 'pequena') || str_contains($publico, 'mei')) {
            $score += 10;
        }

        // 3. Região / Estado
        $regiao = mb_strtolower($edital->regiao ?? '');
        $estado = mb_strtolower($startup->estado ?? '');
        if (str_contains($regiao, 'nacional') || (!empty($estado) && str_contains($regiao, $estado))) {
            $score += 8;
        }

        // 4. Teto orçamentário definido
        if (!empty($edital->max_budget) && $edital->max_budget > 0) {
            $score += 6;
        }

        return min(96, max(35, $score));
    }
}
