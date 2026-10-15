<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PagamentoController;
use App\Http\Controllers\EditalController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\MinutaController;
use App\Http\Controllers\CnpjController;
use App\Models\Startup;

// Rotas de Editais
Route::get('/editais', [EditalController::class, 'index']);

// Listagem de Startups para seleção
Route::get('/startups', function () {
    return response()->json([
        'sucesso' => true,
        'dados' => Startup::all()
    ]);
});

// Rotas de Autenticação / Registro SaaS
Route::post('/auth/registro', [AuthController::class, 'registrar']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rota do Webhook do AbacatePay
Route::post('/webhook/abacatepay', [WebhookController::class, 'abacatePayWebhook']);

// Rotas protegidas (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/pagamento/recuperar', [PagamentoController::class, 'recuperarLinkPagamento']);
});

// Rotas do Pipeline Kanban
Route::prefix('kanban')->group(function () {
    Route::get('/', [KanbanController::class, 'index']);
    Route::post('/cards', [KanbanController::class, 'storeCard']);
    Route::patch('/cards/{id}/mover', [KanbanController::class, 'moveCard']);
    Route::delete('/cards/{id}', [KanbanController::class, 'destroyCard']);
});

// Rotas do Módulo de IA e Minutas (RF-29 a RF-34 e RF-47)
Route::prefix('minutas')->group(function () {
    Route::post('/gerar', [MinutaController::class, 'gerar']);
    Route::get('/', [MinutaController::class, 'index']);
    Route::get('/{id}', [MinutaController::class, 'show']);
    Route::put('/{id}', [MinutaController::class, 'update']);
    Route::get('/{id}/exportar-docx', [MinutaController::class, 'exportarDocx']);
    Route::get('/{id}/exportar-pdf', [MinutaController::class, 'exportarPdf']);
});

// RF-47: Match Inteligente de Editais
Route::post('/ia/match-editais', [MinutaController::class, 'matchEditais']);

// Rotas de Consulta de Dados de CNPJ (EDTAN-134 / UC-10)
Route::prefix('cnpj')->group(function () {
    Route::get('/{cnpj}', [CnpjController::class, 'consultar']);
    Route::post('/consultar', [CnpjController::class, 'consultarPost']);
});