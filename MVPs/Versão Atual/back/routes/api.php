<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PagamentoController;

Route::get('/editais', [App\Http\Controllers\EditalController::class, 'index']);

Route::post('/auth/registro', [AuthController::class, 'registrar']);

Route::post('/webhook/abacatepay', [WebhookController::class, 'abacatePayWebhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/pagamento/recuperar', [PagamentoController::class, 'recuperarLinkPagamento']);
});