<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\EuController;
use App\Http\Controllers\MinisterioController;
use App\Http\Controllers\PessoaController;
use App\Http\Controllers\PostoController;
use App\Http\Middleware\DefinirIgrejaAtual;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/convites/aceitar', [ConviteController::class, 'aceitar'])->middleware('throttle:convite');

Route::middleware(['auth:sanctum', DefinirIgrejaAtual::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/eu', EuController::class);

    Route::get('/ministerios', [MinisterioController::class, 'index']);
    Route::post('/ministerios', [MinisterioController::class, 'store']);
    Route::patch('/ministerios/{ministerio}', [MinisterioController::class, 'update']);
    Route::delete('/ministerios/{ministerio}', [MinisterioController::class, 'destroy']);
    Route::put('/ministerios/{ministerio}/lider', [MinisterioController::class, 'definirLider']);

    Route::post('/ministerios/{ministerio}/equipe', [EquipeController::class, 'store']);
    Route::delete('/ministerios/{ministerio}/equipe/{pessoa}', [EquipeController::class, 'destroy']);

    Route::get('/ministerios/{ministerio}/postos', [PostoController::class, 'index']);
    Route::post('/ministerios/{ministerio}/postos', [PostoController::class, 'store']);
    Route::patch('/postos/{posto}', [PostoController::class, 'update']);
    Route::delete('/postos/{posto}', [PostoController::class, 'destroy']);

    Route::get('/pessoas', [PessoaController::class, 'index']);
    Route::post('/pessoas', [PessoaController::class, 'store']);
    Route::patch('/pessoas/{pessoa}', [PessoaController::class, 'update']);
    Route::delete('/pessoas/{pessoa}', [PessoaController::class, 'destroy']);
    Route::post('/pessoas/{pessoa}/convite', [ConviteController::class, 'gerar']);
});
