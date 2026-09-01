<?php

use Illuminate\Support\Facades\Route;
use App\Spiders\FinepSpider;
use RoachPHP\Roach;

Route::get('/teste-finep', function () {
    // Aumenta o tempo limite do PHP para 5 minutos (já que o Puppeteer é pesado)
    ini_set('max_execution_time', 300);

    // Roda o spider de forma síncrona e captura os itens na memória
    $items = Roach::collectSpider(FinepSpider::class);
    
    // Extrai o array de dados de dentro dos objetos "Item" do Roach
    $dados = array_map(function ($item) {
        return $item->all();
    }, $items);
    
    // Devolve para o Chrome em formato JSON puro
    return response()->json($dados);
});
