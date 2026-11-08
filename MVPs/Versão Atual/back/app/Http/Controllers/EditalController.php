<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Edital;
use App\Models\EditalFavorito;

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
        
        // Formata os dados para a interface Front-end (Custo Zero de IA)
        $formattedEditais = $editais->map(function($edital) use ($favoritosIds) {
            $diagnostico = !empty($edital->ai_diagnosis) 
                ? $edital->ai_diagnosis 
                : [[ 'type' => 'info', 'text' => 'Edital Cadastrado - Dados Pré-Formatados' ]];

            return [
                'id' => $edital->id,
                'name' => $edital->title,
                'org' => $edital->fonte ?? 'FINEP',
                'budget' => $edital->max_budget ? 'R$ ' . number_format($edital->max_budget, 2, ',', '.') : 'A definir',
                'deadline' => $edital->deadline ? \Carbon\Carbon::parse($edital->deadline)->format('d/m/Y') . ' às ' . \Carbon\Carbon::parse($edital->deadline)->format('H:i') : 'Sem data',
                'status' => 'Aberto',
                'statusClass' => 'badge-aberto',
                'match' => $edital->ai_match ?? 0,
                'target' => $edital->publico ?? 'Startup / Empresa / Pesquisador',
                'region' => $edital->regiao ?? 'Nacional', 
                'objetivo' => !empty($edital->objetivo) ? $edital->objetivo : (!empty($edital->conteudo_completo) ? mb_substr(strip_tags($edital->conteudo_completo), 0, 1500) . '...' : 'Sem descrição para exibir no resumo.'),
                'documentos' => $edital->documentos ?? [],
                'trl' => $edital->ai_trl ?? 'Geral',
                'nicho' => $edital->nicho ?? 'Multisetorial',
                'faturamento' => $edital->faturamento ?? 'Não especificado',
                'openDate' => $edital->open_date ? \Carbon\Carbon::parse($edital->open_date)->format('d/m/Y') . ' às ' . \Carbon\Carbon::parse($edital->open_date)->format('H:i') : '--',
                'closeDate' => $edital->deadline ? \Carbon\Carbon::parse($edital->deadline)->format('d/m/Y') . ' às ' . \Carbon\Carbon::parse($edital->deadline)->format('H:i') : '--',
                'resultDate' => $edital->result_date ? \Carbon\Carbon::parse($edital->result_date)->format('d/m/Y') . ' às ' . \Carbon\Carbon::parse($edital->result_date)->format('H:i') : '--',
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
                'mensagem' => 'Edital removido dos favoritos com sucesso.'
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
            'mensagem' => 'Edital salvo nos favoritos com sucesso.'
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
