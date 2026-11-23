<?php

namespace App\Events;

use App\Models\KanbanCard;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardMovedToProposal
{
    use Dispatchable, SerializesModels;

    public function __construct(public KanbanCard $card)
    {
    }
}
