<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Edital extends Model
{
    protected $fillable = [
        'external_id', 'title', 'source_url', 'original_file_path',
        'min_budget', 'max_budget', 'deadline', 'published_at',
        'status', 'objetivo', 'condicao_financiamento', 
        'operacao', 'publico', 'fonte'
    ];
}

