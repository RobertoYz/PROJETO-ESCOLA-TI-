<?php

namespace App\Http\Controllers;

use App\Models\Startup;
use Illuminate\Http\Request;

class StartupController extends Controller
{
    public function index(Request $request)
    {
        $agenciaId = $request->user()->agencia_id;
        $termoBusca = $request->query('busca');

        $query = Startup::where('agencia_id', $agenciaId);

        if (!empty($termoBusca)) {
            $query->where(function ($q) use ($termoBusca) {
                $q->where('nome_fantasia', 'LIKE', "%{$termoBusca}%")
                  ->orWhere('razao_social', 'LIKE', "%{$termoBusca}%")
                  ->orWhere('cnpj', 'LIKE', "%{$termoBusca}%");
            });
        }

        $startups = $query->orderBy('nome_fantasia', 'asc')->get();

        return response()->json($startups, 200);
    }

    public function store(Request $request)
    {
        $agenciaId = $request->user()->agencia_id;

        $dadosValidados = $request->validate([
            'cnpj' => 'required|string|max:18',
            'razao_social' => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'data_abertura' => 'nullable|date',
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'porte' => 'nullable|string|max:50',
            'regime_tributario' => 'nullable|string|max:50',
            'nicho_atuacao' => 'nullable|string|max:100',
            'pitch' => 'nullable|string',
        ]);

        $jaExiste = Startup::where('agencia_id', $agenciaId)
            ->where('cnpj', $dadosValidados['cnpj'])
            ->exists();

        if ($jaExiste) {
            return response()->json([
                'mensagem' => 'Esta startup (CNPJ) já está cadastrada na sua consultoria.'
            ], 422);
        }

        $dadosValidados['agencia_id'] = $agenciaId;

        $startup = Startup::create($dadosValidados);

        return response()->json([
            'mensagem' => 'Startup cadastrada com sucesso.',
            'startup' => $startup
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $startup = Startup::where('agencia_id', $request->user()->agencia_id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($startup, 200);
    }

    public function update(Request $request, $id)
    {
        $agenciaId = $request->user()->agencia_id;

        $startup = Startup::where('agencia_id', $agenciaId)
            ->where('id', $id)
            ->firstOrFail();

        $dadosValidados = $request->validate([
            'cnpj' => 'nullable|string|max:18',
            'razao_social' => 'required|string|max:255',
            'nome_fantasia' => 'nullable|string|max:255',
            'data_abertura' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:50',
            'cep' => 'nullable|string|max:9',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'porte' => 'nullable|string|max:50',
            'regime_tributario' => 'nullable|string|max:50',
            'nicho_atuacao' => 'nullable|string|max:100',
            'pitch' => 'nullable|string',
        ]);

        $startup->update($dadosValidados);

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Perfil da startup atualizado com sucesso.',
            'startup' => $startup
        ], 200);
    }
}
