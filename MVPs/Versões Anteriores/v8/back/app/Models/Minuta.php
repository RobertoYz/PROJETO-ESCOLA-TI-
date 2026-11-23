<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Minuta extends Model
{
    use HasFactory;

    protected $fillable = [
        'startup_id',
        'edital_id',
        'user_id',
        'kanban_card_id',
        'versao',
        'parent_minuta_id',
        'titulo_projeto',
        'ideia_projeto',
        'conteudo_gerado',
        'secoes_estruturadas',
        'status',
        'modelo_ia_usado',
        'autor_nome'
    ];

    protected $casts = [
        'secoes_estruturadas' => 'array',
        'versao' => 'integer'
    ];

    public function startup()
    {
        return $this->belongsTo(Startup::class);
    }

    public function edital()
    {
        return $this->belongsTo(Edital::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kanbanCard()
    {
        return $this->belongsTo(KanbanCard::class);
    }

    public function parentMinuta()
    {
        return $this->belongsTo(Minuta::class, 'parent_minuta_id');
    }

    public function versoesFilhas()
    {
        return $this->hasMany(Minuta::class, 'parent_minuta_id');
    }
}
