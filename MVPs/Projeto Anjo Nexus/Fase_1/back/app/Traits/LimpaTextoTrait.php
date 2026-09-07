<?php

namespace App\Traits;

trait LimpaTextoTrait
{
    public function limpaTexto($text): string
    {
        // Se for um array, precisamos converter os itens internos para string primeiro
        if (is_array($text)) {
            $text = collect($text)->map(function ($item) {
                // Se o item interno for array, vira string; se for objeto/null, trata também
                return is_array($item) ? json_encode($item) : (string)$item;
            })->implode(', ');
        }

        if (!$text) {
            return '';
        }

        // Limpeza de HTML e espaços
        $textoLimpo = strip_tags((string)$text);
        $textoLimpo = html_entity_decode($textoLimpo);
        
        return trim(preg_replace('/\s+/', ' ', $textoLimpo));
    }
}