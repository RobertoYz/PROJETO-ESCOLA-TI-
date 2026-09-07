<?php

use Illuminate\Support\Facades\Route;

Route::get('/editais', [App\Http\Controllers\EditalController::class, 'index']);
