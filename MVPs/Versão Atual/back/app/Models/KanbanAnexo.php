<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanAnexo extends Model
{
    protected $table = 'kanban_anexos';
    protected $fillable = ['nome_arquivo', 'tamanho_bytes', 'mime_type', 'disco', 'caminho'];
    protected $hidden = ['disco', 'caminho'];
    protected $casts = ['tamanho_bytes' => 'integer'];
}
