<?php

namespace App\Http\Controllers;

use App\Models\Minuta;
use App\Models\Startup;
use App\Models\Edital;
use App\Services\MinutaAiService;
use App\Services\DocumentExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MinutaController extends Controller
{
    /**
     * RF-29 & RF-34: Gera uma nova minuta via IA com registro auditável
     */
    public function gerar(Request $request, MinutaAiService $aiService)
    {
        $request->validate([
            'startup_id' => 'required|exists:startups,id',
            'edital_id' => 'required|exists:editals,id',
            'ideia_projeto' => 'required|string|min:15',
            'kanban_card_id' => 'nullable|exists:kanban_cards,id'
        ]);

        $startup = Startup::findOrFail($request->startup_id);
        $edital = Edital::findOrFail($request->edital_id);
        $usuario = $request->user();

        Log::info("Iniciando geração de minuta via IA (RF-29)", [
            'startup_id' => $startup->id,
            'edital_id' => $edital->id,
            'user_id' => $usuario?->id
        ]);

        $resultadoIa = $aiService->gerarMinuta($startup, $edital, $request->ideia_projeto);

        // RF-33 & RF-34: Persiste histórico e autor auditável
        $minuta = Minuta::create([
            'startup_id' => $startup->id,
            'edital_id' => $edital->id,
            'user_id' => $usuario?->id,
            'kanban_card_id' => $request->kanban_card_id,
            'versao' => 1,
            'titulo_projeto' => $resultadoIa['titulo_projeto'],
            'ideia_projeto' => $request->ideia_projeto,
            'conteudo_gerado' => $resultadoIa['conteudo_formatado'],
            'secoes_estruturadas' => $resultadoIa['secoes'],
            'status' => 'concluido',
            'modelo_ia_usado' => $resultadoIa['modelo_usado'],
            'autor_nome' => $usuario?->name ?? 'Consultor Sistema'
        ]);

        Log::info("Minuta #{$minuta->id} criada e auditada com sucesso (RF-34)");

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Minuta gerada com sucesso pela Inteligência Artificial.',
            'dados' => $minuta->load(['startup', 'edital', 'user'])
        ], 201);
    }

    /**
     * RF-33: Lista o histórico de minutas salvas com filtros
     */
    public function index(Request $request)
    {
        $query = Minuta::with(['startup', 'edital', 'user'])->latest();

        if ($request->filled('startup_id')) {
            $query->where('startup_id', $request->startup_id);
        }

        if ($request->filled('edital_id')) {
            $query->where('edital_id', $request->edital_id);
        }

        if ($request->filled('kanban_card_id')) {
            $query->where('kanban_card_id', $request->kanban_card_id);
        }

        $minutas = $query->paginate(15);

        return response()->json([
            'sucesso' => true,
            'dados' => $minutas
        ]);
    }

    /**
     * Exibe os detalhes de uma minuta específica
     */
    public function show($id)
    {
        $minuta = Minuta::with(['startup', 'edital', 'user', 'versoesFilhas'])->findOrFail($id);

        return response()->json([
            'sucesso' => true,
            'dados' => $minuta
        ]);
    }

    /**
     * RF-30 & RF-33: Salva edições em tela criando uma nova versão auditável
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'conteudo_gerado' => 'required|string',
            'secoes_estruturadas' => 'nullable|array',
            'titulo_projeto' => 'nullable|string'
        ]);

        $minutaOriginal = Minuta::findOrFail($id);
        $usuario = $request->user();

        // Cria nova versão mantendo a auditoria do histórico (RF-33 & RF-34)
        $novaVersao = Minuta::create([
            'startup_id' => $minutaOriginal->startup_id,
            'edital_id' => $minutaOriginal->edital_id,
            'user_id' => $usuario?->id,
            'kanban_card_id' => $minutaOriginal->kanban_card_id,
            'versao' => $minutaOriginal->versao + 1,
            'parent_minuta_id' => $minutaOriginal->id,
            'titulo_projeto' => $request->titulo_projeto ?? $minutaOriginal->titulo_projeto,
            'ideia_projeto' => $minutaOriginal->ideia_projeto,
            'conteudo_gerado' => $request->conteudo_gerado,
            'secoes_estruturadas' => $request->secoes_estruturadas ?? $minutaOriginal->secoes_estruturadas,
            'status' => 'revisado',
            'modelo_ia_usado' => $minutaOriginal->modelo_ia_usado,
            'autor_nome' => $usuario?->name ?? 'Consultor Sistema'
        ]);

        Log::info("Nova versão v{$novaVersao->versao} da minuta #{$minutaOriginal->id} registrada (RF-30/RF-33)");

        return response()->json([
            'sucesso' => true,
            'mensagem' => "Proposta atualizada com sucesso para a versão v{$novaVersao->versao}.",
            'dados' => $novaVersao->load(['startup', 'edital', 'user'])
        ]);
    }

    /**
     * RF-32: Exportação em formato Word (.docx)
     */
    public function exportarDocx($id, DocumentExportService $exportService)
    {
        $minuta = Minuta::with(['startup', 'edital'])->findOrFail($id);
        $caminhoArquivo = $exportService->exportarDocx($minuta);

        $nomeArquivo = 'Minuta_Proposta_' . ($minuta->startup->nome_fantasia ?: 'Startup') . '_v' . $minuta->versao . '.docx';
        $nomeLimpo = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $nomeArquivo);

        return response()->download($caminhoArquivo, $nomeLimpo)->deleteFileAfterSend(true);
    }

    /**
     * RF-32.1: Exportação/Visualização em formato PDF institucional
     */
    public function exportarPdf($id, DocumentExportService $exportService)
    {
        $minuta = Minuta::with(['startup', 'edital'])->findOrFail($id);
        $html = $exportService->gerarHtmlParaPdf($minuta);

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * RF-47: Match Inteligente de Editais com justificativas de aderência
     */
    public function matchEditais(Request $request, MinutaAiService $aiService)
    {
        $request->validate([
            'startup_id' => 'required|exists:startups,id'
        ]);

        $startup = Startup::findOrFail($request->startup_id);
        $editais = Edital::whereNotNull('title')->take(20)->get();

        $ranking = $aiService->calcularMatchEditais($startup, $editais);

        return response()->json([
            'sucesso' => true,
            'startup' => [
                'id' => $startup->id,
                'nome' => $startup->nome_fantasia ?: $startup->razao_social,
                'nicho' => $startup->nicho_atuacao,
                'porte' => $startup->porte
            ],
            'ranking' => $ranking
        ]);
    }
}
