<?php

namespace App\Http\Controllers;

use App\Models\Startup;
use App\Services\PlanoQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StartupController extends Controller
{
    protected PlanoQuotaService $quotaService;

    public function __construct(PlanoQuotaService $quotaService)
    {
        $this->quotaService = $quotaService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $agencia = $user->agencia;
        $agenciaId = $user->agencia_id;
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

        $cota = $agencia ? $this->quotaService->getUsageSummary($agencia) : [
            'startups_atual' => $startups->count(),
            'startups_limite' => null,
            'pode_criar_startup' => true,
            'plano_nome' => 'Standard',
        ];

        $podeCriarEditarStartup = $user->temPermissao(\App\Enums\Permissao::STARTUPS_CRIAR_EDITAR);
        $podeExcluirStartup = $user->temPermissao(\App\Enums\Permissao::STARTUPS_EXCLUIR);

        $startupsTransformadas = $startups->map(function (Startup $startup) use ($cota, $podeExcluirStartup, $podeCriarEditarStartup) {
            $item = $startup->toArray();
            $item['cota'] = $cota;
            $item['pode_excluir_startup'] = $podeExcluirStartup;
            $item['pode_editar_startup'] = $podeCriarEditarStartup;
            return $item;
        });

        if ($request->boolean('incluir_cota') || $request->header('X-Include-Quota') === '1') {
            return response()->json([
                'data' => $startupsTransformadas,
                'startups' => $startupsTransformadas,
                'cota' => $cota,
                'pode_excluir_startup' => $podeExcluirStartup,
                'pode_editar_startup' => $podeCriarEditarStartup,
            ], 200);
        }

        return response()->json($startupsTransformadas, 200);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Startup::class);

        $user = $request->user();
        $agencia = $user->agencia;
        $agenciaId = $user->agencia_id;

        if ($agencia) {
            $this->quotaService->assertCanCreateStartup($agencia);
        }

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

        $existenteAtiva = Startup::where('agencia_id', $agenciaId)
            ->where('cnpj', $dadosValidados['cnpj'])
            ->exists();

        if ($existenteAtiva) {
            return response()->json([
                'message' => 'Esta startup (CNPJ) já está cadastrada na sua consultoria.'
            ], 422);
        }

        $startupExcluidaAnteriormente = Startup::onlyTrashed()
            ->where('agencia_id', $agenciaId)
            ->where('cnpj', $dadosValidados['cnpj'])
            ->first();

        $dadosValidados['agencia_id'] = $agenciaId;

        if ($startupExcluidaAnteriormente) {
            $startupExcluidaAnteriormente->restore();
            $startupExcluidaAnteriormente->update($dadosValidados);
            $startup = $startupExcluidaAnteriormente->fresh();
        } else {
            $startup = Startup::create($dadosValidados);
        }

        $cota = $agencia ? $this->quotaService->getUsageSummary($agencia) : null;

        return response()->json([
            'message' => 'Startup cadastrada com sucesso.',
            'startup' => $startup,
            'cota' => $cota,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $startup = Startup::findOrFail($id);
        Gate::authorize('view', $startup);

        $user = $request->user();
        $podeExcluirStartup = $user->temPermissao(\App\Enums\Permissao::STARTUPS_EXCLUIR);
        $podeCriarEditarStartup = $user->temPermissao(\App\Enums\Permissao::STARTUPS_CRIAR_EDITAR);

        $payload = $startup->toArray();
        $payload['pode_excluir_startup'] = $podeExcluirStartup;
        $payload['pode_editar_startup'] = $podeCriarEditarStartup;

        return response()->json($payload, 200);
    }

    public function update(Request $request, $id)
    {
        $startup = Startup::findOrFail($id);
        Gate::authorize('update', $startup);

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
            'message' => 'Perfil da startup atualizado com sucesso.',
            'startup' => $startup
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $startup = Startup::findOrFail($id);
        Gate::authorize('delete', $startup);

        $startup->delete();

        $agencia = $request->user()->agencia;
        $cota = $agencia ? $this->quotaService->getUsageSummary($agencia) : null;

        return response()->json([
            'sucesso' => true,
            'message' => 'Startup excluída com sucesso. Uma vaga foi liberada no seu plano.',
            'cota' => $cota,
        ], 200);
    }
}
