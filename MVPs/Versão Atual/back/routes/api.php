<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/editais', [App\Http\Controllers\EditalController::class, 'index']);

Route::post('/auth/registro', [AuthController::class, 'registrar']);