<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Edital;

class EditalController extends Controller
{
    public function index()
    {
        $editais = Edital::all();
        
        // Vamos formatar os dados para o JS do front-end
        $formattedEditais = $editais->map(function($edital) {
            
            // Diagnóstico base caso a IA não tenha processado
            $diagnostico = $edital->ai_analyzed 
                ? ($edital->ai_diagnosis ?? []) 
                : [[ 'type' => 'warning', 'text' => 'Analisando por IA...' ]];

            return [
                'id' => $edital->id,
                'name' => $edital->title,
                'org' => $edital->fonte ?? 'FINEP',
                'budget' => $edital->max_budget ? 'R$ ' . number_format($edital->max_budget, 2, ',', '.') : 'A definir',
                'deadline' => $edital->deadline ? \Carbon\Carbon::parse($edital->deadline)->format('d/m/Y') . ' às ' . \Carbon\Carbon::parse($edital->deadline)->format('H:i') : 'Sem data',
                'status' => 'Aberto', // TODO: Lógica de status
                'statusClass' => 'badge-aberto',
                'match' => $edital->ai_match ?? 0,
                'target' => $edital->publico ?? '--',
                'region' => $edital->regiao ?? 'Nacional', 
                'objetivo' => $edital->objetivo ?? 'Sem descrição',
                'documentos' => $edital->documentos ?? [],
                'trl' => $edital->ai_trl ?? 'A definir',
                'nicho' => $edital->ai_nicho ?? 'Analisando...',
                'faturamento' => $edital->ai_faturamento ?? 'Analisando...',
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
