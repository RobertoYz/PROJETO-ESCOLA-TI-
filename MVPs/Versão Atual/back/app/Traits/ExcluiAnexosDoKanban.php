<?php

namespace App\Traits;

use App\Models\KanbanAnexo;
use App\Models\KanbanCard;
use App\Services\ArmazenamentoKanbanService;
use Illuminate\Support\Facades\DB;

trait ExcluiAnexosDoKanban
{
    protected function performDeleteOnModel()
    {
        DB::transaction(function () {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $cards = KanbanCard::where(static::CHAVE_CARD, $this->getKey())->lockForUpdate()->pluck('id');
            $anexos = KanbanAnexo::whereIn('card_id', $cards)->lockForUpdate()->get();
            app(ArmazenamentoKanbanService::class)->agendarExclusao($anexos);
            parent::performDeleteOnModel();
        });
    }
}
