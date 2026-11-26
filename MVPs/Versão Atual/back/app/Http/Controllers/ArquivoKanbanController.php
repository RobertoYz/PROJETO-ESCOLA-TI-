<?php

namespace App\Http\Controllers;

use App\Enums\Permissao;
use App\Services\ArmazenamentoKanbanService;
use App\Services\MinutaLimitService;
use Illuminate\Http\Request;

class ArquivoKanbanController extends Controller
{
    public function __construct(private ArmazenamentoKanbanService $armazenamento) {}

    public function consumo(Request $request, MinutaLimitService $minutas)
    {
        $usuario = $request->user();
        abort_unless($usuario->agencia, 403);
        return response()->json(['creditos_minutas' => $minutas->obterConsumoMinutas($usuario),
            'armazenamento' => $this->armazenamento->consumo($usuario->agencia)]);
    }

    public function arquivos(Request $request, int $startup)
    {
        $usuario = $request->user();
        return response()->json($this->armazenamento->arquivos((int) $usuario->agencia_id, $startup) +
            ['pode_excluir' => $usuario->temPermissao(Permissao::KANBAN_GERENCIAR)]);
    }

    public function baixar(Request $request, int $id)
    {
        return $this->armazenamento->baixar((int) $request->user()->agencia_id, $id);
    }

    public function excluir(Request $request, int $id)
    {
        $usuario = $request->user();
        abort_unless($usuario->temPermissao(Permissao::KANBAN_GERENCIAR), 403);
        return response()->json(['armazenamento' => $this->armazenamento->excluir((int) $usuario->agencia_id, $id)]);
    }
}
