<?php

namespace App\Http\Controllers;

use App\Models\TermoAceite;
use App\Services\TermoAceiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermoAceiteController extends Controller
{
    public function vigentes(TermoAceiteService $servico): JsonResponse
    {
        $documentos = $servico->vigentes();
        return response()->json($servico->metadados($documentos) + $documentos->all())->header('Cache-Control', 'no-store');
    }

    public function meuAceite(Request $requisicao): JsonResponse
    {
        return response()->json(TermoAceite::where('user_id', $requisicao->user()->id)
            ->with(['termosUso', 'politicaPrivacidade'])->orderByDesc('id')->get())->header('Cache-Control', 'private, no-store');
    }
}
