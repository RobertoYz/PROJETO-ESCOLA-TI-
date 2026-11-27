<?php

namespace App\Services;

use App\Models\Agencia;
use App\Models\KanbanAnexo;
use App\Models\KanbanCard;
use App\Models\Startup;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ArmazenamentoKanbanService
{
    private const MB = 1048576;

    private function anexos(int $agenciaId)
    {
        return DB::table('kanban_anexos as a')
            ->join('kanban_cards as c', 'c.id', '=', 'a.card_id')
            ->join('kanban_boards as q', 'q.id', '=', 'c.board_id')
            ->where('q.agencia_id', $agenciaId);
    }

    private function limites(Agencia $agencia, int $bytes): array
    {
        $limite = $agencia->plano?->limite_armazenamento_mb ?? 1024;
        $max = $limite * self::MB;
        $livre = max(0, $max - $bytes);
        return [
            'usado_bytes' => $bytes, 'usado_mb' => round($bytes / self::MB, 2),
            'limite_mb' => $limite, 'limite_bytes' => $max,
            'limite_arquivo_mb' => $agencia->plano?->limite_arquivo_mb ?? 50,
            'disponivel_bytes' => $livre, 'disponivel_mb' => round($livre / self::MB, 2),
            'percentual_uso' => $max ? round(100 * $bytes / $max, 2) : ($bytes ? 100 : 0),
            'acima_do_limite' => $bytes > $max,
        ];
    }

    public function consumo(Agencia $agencia): array
    {
        $grupos = $this->anexos($agencia->id)->selectRaw('c.startup_id, SUM(a.tamanho_bytes) as bytes, COUNT(*) as quantidade')
            ->groupBy('c.startup_id')->get()->keyBy('startup_id');
        $total = (int) $grupos->sum('bytes');
        $startups = $agencia->startups()->get()->map(function ($startup) use ($grupos) {
            $grupo = $grupos->pull($startup->id);
            $bytes = (int) ($grupo->bytes ?? 0);
            return ['startup_id' => $startup->id, 'nome' => $startup->nome_fantasia ?: $startup->razao_social,
                'total_arquivos' => (int) ($grupo->quantidade ?? 0), 'usado_bytes' => $bytes,
                'usado_mb' => round($bytes / self::MB, 2)];
        })->sortBy([['usado_bytes', 'desc'], ['nome', 'asc']])->values();
        $semStartup = (int) $grupos->sum('bytes');
        return $this->limites($agencia, $total) + ['por_startup' => $startups,
            'sem_startup' => ['bytes' => $semStartup, 'mb' => round($semStartup / self::MB, 2),
                'total_arquivos' => (int) $grupos->sum('quantidade')]];
    }

    public function arquivos(int $agenciaId, int $startupId): array
    {
        Startup::where('agencia_id', $agenciaId)->findOrFail($startupId);
        $arquivos = $this->anexos($agenciaId)->where('c.startup_id', $startupId)
            ->join('kanban_columns as e', 'e.id', '=', 'c.column_id')
            ->select('a.id', 'a.nome_arquivo', 'a.tamanho_bytes', 'a.mime_type', 'a.created_at',
                'a.card_id', 'c.title as card_titulo', 'e.title as etapa_nome')
            ->orderByDesc('a.tamanho_bytes')->orderBy('a.id')->get();
        foreach ($arquivos as $arquivo) {
            $arquivo->tamanho_mb = round($arquivo->tamanho_bytes / self::MB, 2);
            $arquivo->rota_anexo = '/kanban/anexos/'.$arquivo->id;
        }
        $bytes = (int) $arquivos->sum('tamanho_bytes');
        return ['arquivos' => $arquivos, 'total_arquivos' => $arquivos->count(),
            'usado_bytes' => $bytes, 'usado_mb' => round($bytes / self::MB, 2)];
    }

    public function atualizarCard(int $agenciaId, int $id, array $dados, ?UploadedFile $arquivo): KanbanCard
    {
        $disco = config('filesystems.kanban');
        $caminho = null;
        try {
            return DB::transaction(function () use ($agenciaId, $id, $dados, $arquivo, $disco, &$caminho) {
                $agencia = Agencia::lockForUpdate()->findOrFail($agenciaId);
                $card = KanbanCard::whereHas('board', fn ($q) => $q->where('agencia_id', $agenciaId))->lockForUpdate()->findOrFail($id);
                if ($arquivo) {
                    $bytes = $arquivo->getSize();
                    $cota = $this->limites($agencia, (int) $this->anexos($agenciaId)->sum('a.tamanho_bytes'));
                    if ($bytes > $cota['limite_arquivo_mb'] * self::MB) {
                        $this->recusar('LIMITE_TAMANHO_ARQUIVO_EXCEDIDO', 'O arquivo excede o limite individual do plano.', $cota);
                    }
                    \Illuminate\Support\Facades\Validator::validate(['arquivo' => $arquivo], [
                        'arquivo' => 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg',
                    ], ['arquivo.mimes' => 'Formato de arquivo não permitido.']);
                    if ($cota['usado_bytes'] + $bytes > $cota['limite_bytes']) {
                        $this->recusar('LIMITE_ARMAZENAMENTO_ATINGIDO', 'Espaço insuficiente no plano da agência.', $cota);
                    }
                    $caminho = 'kanban_anexos/'.$arquivo->hashName();
                    if (!Storage::disk($disco)->putFileAs('kanban_anexos', $arquivo, basename($caminho), 'private')) {
                        throw new RuntimeException('Falha ao armazenar o arquivo.');
                    }
                    $card->anexos()->create(['nome_arquivo' => $arquivo->getClientOriginalName(),
                        'tamanho_bytes' => $bytes, 'mime_type' => $arquivo->getMimeType(),
                        'disco' => $disco, 'caminho' => $caminho]);
                }
                $card->fill(array_filter(['title' => $dados['title'] ?? null, 'description' => $dados['description'] ?? null],
                    fn ($valor) => $valor !== null));
                $card->metadata = array_merge($card->metadata ?? [], $dados['metadata'] ?? []);
                $card->save();
                return $card->load('edital', 'anexos');
            });
        } catch (Throwable $erro) {
            if ($caminho) {
                Storage::disk($disco)->delete($caminho);
            }
            throw $erro;
        }
    }

    private function recusar(string $codigo, string $mensagem, array $cota): never
    {
        throw new HttpResponseException(response()->json(['status' => 'erro', 'codigo_erro' => $codigo, 'message' => $mensagem] + $cota, 422));
    }

    public function baixar(int $agenciaId, int $id)
    {
        $arquivo = $this->anexos($agenciaId)->where('a.id', $id)->firstOrFail(['a.*']);
        $disco = Storage::disk($arquivo->disco);
        abort_unless($disco->exists($arquivo->caminho), 404);
        return $disco->download($arquivo->caminho, $arquivo->nome_arquivo);
    }

    public function excluir(int $agenciaId, int $id): array
    {
        return DB::transaction(function () use ($agenciaId, $id) {
            $agencia = Agencia::lockForUpdate()->findOrFail($agenciaId);
            $arquivo = $this->anexos($agenciaId)->where('a.id', $id)->lockForUpdate()->firstOrFail(['a.*']);
            $this->agendarExclusao([$arquivo]);
            KanbanAnexo::whereKey($id)->delete();
            return $this->consumo($agencia);
        });
    }

    public function agendarExclusao(iterable $arquivos): void
    {
        foreach ($arquivos as $arquivo) {
            $id = DB::table('kanban_exclusoes_pendentes')->insertGetId([
                'disco' => $arquivo->disco, 'caminho' => $arquivo->caminho,
            ]);
            DB::afterCommit(fn () => $this->limparPendencias($id));
        }
    }

    public function limparPendencias(?int $id = null): void
    {
        DB::table('kanban_exclusoes_pendentes')->when($id, fn ($q) => $q->where('id', $id))
            ->eachById(function ($item) {
                $compartilhado = KanbanAnexo::where('disco', $item->disco)->where('caminho', $item->caminho)->exists();
                if ($compartilhado || Storage::disk($item->disco)->delete($item->caminho)) {
                    DB::table('kanban_exclusoes_pendentes')->where('id', $item->id)->delete();
                }
            });
    }
}
