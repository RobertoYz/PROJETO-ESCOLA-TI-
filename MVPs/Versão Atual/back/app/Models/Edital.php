<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Edital extends Model
{
    protected $fillable = [
        'external_id', 'title', 'source_url', 'original_file_path',
        'min_budget', 'max_budget', 'deadline', 'published_at',
        'open_date', 'result_date',
        'status', 'objetivo', 'condicao_financiamento', 
        'operacao', 'publico', 'fonte',
        'documentos', 'regiao', 'nicho_tema',
        'ai_analyzed', 'ai_match', 'ai_trl', 'ai_diagnosis',
        'ai_faturamento', 'ai_nicho', 'conteudo_completo'
    ];

    protected $casts = [
        'documentos' => 'array',
        'ai_analyzed' => 'boolean',
        'ai_diagnosis' => 'array',
    ];
}

