<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PagamentoController;
use App\Http\Controllers\EditalController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\MinutaController;
use App\Http\Controllers\CnpjController;
use App\Http\Controllers\PlanoController;
use App\Http\Controllers\StartupController;

// Rotas de Editais
Route::get('/editais', [EditalController::class, 'index']);

// Listagem pública dos planos disponíveis
Route::get('/planos', [PlanoController::class, 'listarPlanos']);

// Rotas públicas de Autenticação / Registro SaaS
Route::post('/auth/verificar-email', [AuthController::class, 'verificarEmail']);
Route::post('/auth/registrar-com-plano', [AuthController::class, 'registrarComPlano']);
Route::post('/auth/registro', [AuthController::class, 'registrar']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/esqueci-senha', [AuthController::class, 'solicitarRecuperacaoSenha']);
Route::post('/auth/redefinir-senha', [AuthController::class, 'redefinirSenha']);
Route::get('/auth/login', function () {
    return response()->json(['mensagem' => 'Não autenticado. Por favor, faça login.'], 401);
})->name('login');

// Rota do Webhook do AbacatePay
Route::post('/webhook/abacatepay', [WebhookController::class, 'abacatePayWebhook']);

// Rotas protegidas (Sanctum) - sem o middleware que bloqueia pagamento pendente
Route::middleware('auth:sanctum')->group(function () {
    // Gestão de cobrança e pendência de pagamento
    Route::get('/pagamentos/consultar-anterior', [AuthController::class, 'consultarPagamentoAnterior']);
    Route::post('/planos/gerar-nova-cobranca', [AuthController::class, 'gerarNovaCobranca']);
    Route::delete('/auth/cancelar-cadastro-pendente', [AuthController::class, 'cancelarCadastroPendente']);

    // Rotas legadas de plano/pagamento mantidas por compatibilidade
    Route::get('/pagamento/recuperar', [PagamentoController::class, 'recuperarLinkPagamento']);
    Route::post('/planos/selecionar', [PlanoController::class, 'selecionarPlano']);

    // Rotas do painel que exigem pagamento ativo
    Route::middleware('check.pagamento')->group(function () {
        Route::get('/startups', [StartupController::class, 'index']);
        Route::post('/startups', [StartupController::class, 'store']);
        Route::get('/startups/{id}', [StartupController::class, 'show']);
        Route::put('/startups/{id}', [StartupController::class, 'update']);
    });
});

// Rotas do Pipeline Kanban
Route::prefix('kanban')->group(function () {
    Route::get('/', [KanbanController::class, 'index']);
    Route::post('/cards', [KanbanController::class, 'storeCard']);
    Route::patch('/cards/{id}/mover', [KanbanController::class, 'moveCard']);
    Route::post('/cards/{id}/dados-anexos', [KanbanController::class, 'updateCard']);
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