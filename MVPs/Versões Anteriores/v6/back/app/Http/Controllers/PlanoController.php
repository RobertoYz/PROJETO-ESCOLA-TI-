<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Services\AbacatePayService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;

class PlanoController extends Controller
{
    public function listarPlanos()
    {
        $planos = Plano::select(
            'id',
            'nome',
            'preco',
            'preco as preco_mensal',
            'limite_startups',
            'limite_membros_equipe',
            'limite_membros_equipe as limite_usuarios'
        )->get();
        return response()->json($planos, 200);
    }

    public function selecionarPlano(Request $requisicao, AbacatePayService $servicoAbacatePay)
    {
        $requisicao->validate([
            'plano_id' => 'required|exists:planos,id',
        ]);

        $usuario = $requisicao->user();
        $agencia = $usuario ? $usuario->agencia : null;

        if (!$agencia) {
            return response()->json(['mensagem' => 'Agência não encontrada para este usuário.'], 404);
        }

        $plano = Plano::findOrFail($requisicao->plano_id);

        $agencia->plano_id = $plano->id;
        $agencia->save();

        try {
            $cobranca = $servicoAbacatePay->gerarCobranca($agencia, $usuario, $plano);
            $urlCheckout = is_array($cobranca) ? $cobranca['url'] : $cobranca;
            $idCobranca = is_array($cobranca) ? ($cobranca['id'] ?? null) : null;

            $agencia->checkout_url = $urlCheckout;
            $agencia->url_checkout = $urlCheckout;
            $agencia->billing_id = $idCobranca;
            $agencia->id_cobranca = $idCobranca;
            $agencia->id_plataforma_pagamento = $idCobranca;
            $agencia->pagamento_expira_em = Carbon::now()->addMinutes(60);
            $agencia->save();

            return response()->json([
                'mensagem' => 'Plano selecionado com sucesso.',
                'plano' => $plano->nome,
                'checkout_url' => $urlCheckout,
                'url_checkout' => $urlCheckout,
            ], 200);
        } catch (Exception $excecao) {
            return response()->json([
                'mensagem' => 'Erro ao gerar link de pagamento para o plano selecionado.',
                'detalhe' => $excecao->getMessage()
            ], 500);
        }
    }
}
