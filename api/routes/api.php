<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EuController;
use App\Http\Middleware\DefinirIgrejaAtual;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', DefinirIgrejaAtual::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/eu', EuController::class);
});
