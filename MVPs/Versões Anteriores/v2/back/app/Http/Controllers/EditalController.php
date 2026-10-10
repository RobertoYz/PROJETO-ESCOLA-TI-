<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Edital;

class EditalController extends Controller
{
    public function index()
    {
        $query = Edital::query();

        if (request()->filled('fonte')) {
            $query->where('fonte', request('fonte'));
        }

        $editais = $query->get();
        
        // Formata os dados para a interface Front-end (Custo Zero de IA)
        $formattedEditais = $editais->map(function($edital) {
            
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
                'favorite' => false,
                'diagnosis' => $diagnostico
            ];
        });

        return response()->json($formattedEditais);
    }
}
