<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public $withinTransaction = false;

    private function transferir(string $origem, string $destino, string $caminho): int
    {
        $de = Storage::disk($origem);
        $para = Storage::disk($destino);
        if ($de->exists($caminho)) {
            $stream = $de->readStream($caminho);
            if (!$stream || !$para->writeStream($caminho, $stream) || $para->size($caminho) !== $de->size($caminho)) {
                throw new RuntimeException('Falha ao copiar anexo do Kanban.');
            }
            if (!$de->delete($caminho)) {
                throw new RuntimeException('Falha ao remover a cópia de origem.');
            }
        }
        return $para->exists($caminho) ? $para->size($caminho) : 0;
    }

    public function up(): void
    {
        DB::table('kanban_cards')->whereNotNull('metadata')->chunkById(100, function ($cards) {
            foreach ($cards as $card) {
                $meta = json_decode($card->metadata, true, flags: JSON_THROW_ON_ERROR);
                if (empty($meta['anexos'])) {
                    continue;
                }
                DB::transaction(function () use ($card, $meta) {
                    foreach ($meta['anexos'] as $item) {
                        $caminho = preg_replace('#^/storage/#', '', $item['caminho'] ?? '');
                        $valido = preg_match('#^kanban_anexos/[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9]+)?$#D', $caminho);
                        $bytes = $valido ? $this->transferir('public', 'local', $caminho) : 0;
                        DB::table('kanban_anexos')->insert([
                            'card_id' => $card->id, 'nome_arquivo' => $item['nome'] ?? 'Anexo',
                            'disco' => 'local', 'caminho' => $valido ? $caminho : 'ausentes/'.\Illuminate\Support\Str::uuid(),
                            'tamanho_bytes' => $bytes, 'created_at' => isset($item['enviado_em'])
                                ? \Illuminate\Support\Carbon::parse($item['enviado_em'])->toDateTimeString() : $card->created_at,
                            'updated_at' => $card->updated_at,
                        ]);
                    }
                    unset($meta['anexos']);
                    DB::table('kanban_cards')->where('id', $card->id)->update(['metadata' => json_encode($meta, JSON_THROW_ON_ERROR)]);
                });
            }
        });
    }

    public function down(): void
    {
        DB::table('kanban_cards')->chunkById(100, function ($cards) {
            foreach ($cards as $card) {
                DB::transaction(function () use ($card) {
                    $meta = json_decode($card->metadata ?? '{}', true);
                    foreach (DB::table('kanban_anexos')->where('card_id', $card->id)->get() as $anexo) {
                        $this->transferir($anexo->disco, 'public', $anexo->caminho);
                        $meta['anexos'][] = ['id' => $anexo->id, 'nome' => $anexo->nome_arquivo,
                            'caminho' => '/storage/'.$anexo->caminho, 'tamanho' => $anexo->tamanho_bytes,
                            'enviado_em' => $anexo->created_at];
                        DB::table('kanban_anexos')->where('id', $anexo->id)->delete();
                    }
                    DB::table('kanban_cards')->where('id', $card->id)->update(['metadata' => json_encode($meta)]);
                });
            }
        });
    }
};
