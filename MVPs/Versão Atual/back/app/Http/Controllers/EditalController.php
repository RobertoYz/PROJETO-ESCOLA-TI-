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
     * RF-10 / RF-11: Listagem de editais com identificação dinâmica de favoritos e cálculo de match contextualizado por startup
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
        $startup = null;

        if ($request->filled('startup_id')) {
            $startup = Startup::find($request->startup_id);
        }

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
        
        // Formata os dados para a interface Front-end com cálculo dinâmico de Match por Startup
        $formattedEditais = $editais->map(function($edital) use ($favoritosIds, $startup) {
            $diagnostico = !empty($edital->ai_diagnosis) 
                ? $edital->ai_diagnosis 
                : [[ 'type' => 'info', 'text' => 'Edital Cadastrado - Dados Pré-Formatados' ]];

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

            $matchCalculado = $this->calcularScoreMatch($edital, $startup);

            return [
                'id' => $edital->id,
                'name' => $edital->title,
                'org' => $edital->fonte ?? 'FINEP',
                'budget' => $edital->max_budget ? 'R$ ' . number_format($edital->max_budget, 2, ',', '.') : 'A definir',
                'deadline' => $deadlineFormatada,
                'status' => 'Aberto',
                'statusClass' => 'badge-aberto',
                'match' => $matchCalculado,
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
     * Calcula dinamicamente o percentual de compatibilidade (Match) entre o edital e a startup selecionada.
     */
    private function calcularScoreMatch(Edital $edital, ?Startup $startup): int
    {
        $base = (int) ($edital->ai_match ?? 0);

        if (!$startup) {
            if ($base > 0) {
                return $base;
            }
            // Score base determinístico do edital quando não há startup filtrada
            $scoreEdital = 50 + ($edital->id % 15);
            if ($edital->max_budget > 0) $scoreEdital += 10;
            if (!empty($edital->deadline)) $scoreEdital += 8;
            return min(92, $scoreEdital);
        }

        // Quando há uma startup selecionada, calcula aderência semântica (Nicho + Pitch + Porte + Estado + Orçamento)
        $score = 45 + (($edital->id * 7 + $startup->id * 3) % 12);

        $textoEdital = mb_strtolower(
            ($edital->title ?? '') . ' ' .
            ($edital->objetivo ?? '') . ' ' .
            ($edital->ai_nicho ?? '') . ' ' .
            ($edital->nicho ?? '') . ' ' .
            ($edital->publico ?? '')
        );

        $textoStartup = mb_strtolower(
            ($startup->nicho_atuacao ?? '') . ' ' .
            ($startup->pitch ?? '') . ' ' .
            ($startup->nome_fantasia ?? '') . ' ' .
            ($startup->razao_social ?? '')
        );

        // Extrai palavras-chave relevantes (>= 4 letras) da startup
        $palavrasStartup = array_unique(array_filter(
            preg_split('/[\s,;\-\/\.\(\)]+/u', $textoStartup),
            fn($p) => mb_strlen($p) >= 4 && !in_array($p, ['para', 'como', 'mais', 'pela', 'pelo', 'seus', 'suas', 'ltda', 'eireli', 'centro'])
        ));

        $matchesPalavras = 0;
        foreach ($palavrasStartup as $palavra) {
            if (str_contains($textoEdital, $palavra)) {
                $matchesPalavras++;
            }
        }

        // Bônus por afinidade semântica de palavras-chave (até +28 pontos)
        $score += min(28, $matchesPalavras * 9);

        // Bônus por elegibilidade de porte/público (+10 pontos)
        $publico = mb_strtolower($edital->publico ?? '');
        $porte = mb_strtolower($startup->porte ?? '');
        if (
            str_contains($publico, 'startup') ||
            str_contains($publico, 'empresa') ||
            str_contains($publico, 'ict') ||
            str_contains($publico, 'pesquisa') ||
            (!empty($porte) && str_contains($publico, $porte))
        ) {
            $score += 10;
        }

        // Bônus por abrangência regional ou agência do mesmo estado (+8 pontos)
        $uf = strtoupper(trim($startup->estado ?? ''));
        $fonte = strtoupper($edital->fonte ?? '');
        if ($fonte === 'FINEP' || ($uf === 'SC' && $fonte === 'FAPESC') || ($uf === 'SP' && $fonte === 'FAPESP') || ($uf === 'MG' && $fonte === 'FAPEMIG') || ($uf === 'RJ' && $fonte === 'FAPERJ') || ($uf === 'RS' && $fonte === 'FAPERGS') || ($uf === 'GO' && $fonte === 'FAPEG') || ($uf === 'DF' && $fonte === 'FAPDF') || ($uf === 'MT' && $fonte === 'FAPEMAT')) {
            $score += 8;
        }

        // Bônus por orçamento explícito (+5 pontos)
        if ($edital->max_budget > 0) {
            $score += 5;
        }

        // Se o edital já estava encerrado no passado, reduz levemente a prioridade frente aos abertos
        if (!empty($edital->deadline)) {
            try {
                if (\Carbon\Carbon::parse($edital->deadline)->isPast()) {
                    $score = max(35, $score - 15);
                }
            } catch (\Throwable $e) {}
        }

        return min(98, max(35, $score));
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
}
