<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EditalController;
use App\Http\Controllers\KanbanController;

// Rotas de Editais
Route::get('/editais', [EditalController::class, 'index']);

// Rotas de Autenticação / Registro SaaS
Route::post('/auth/registro', [AuthController::class, 'registrar']);

// Rotas do Pipeline Kanban
Route::prefix('kanban')->group(function () {
    Route::get('/', [KanbanController::class, 'index']);
    Route::post('/cards', [KanbanController::class, 'storeCard']);
    Route::patch('/cards/{id}/mover', [KanbanController::class, 'moveCard']);
    Route::delete('/cards/{id}', [KanbanController::class, 'destroyCard']);
});