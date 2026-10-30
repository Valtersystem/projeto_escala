<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvisoController;
use App\Http\Controllers\BandaController;
use App\Http\Controllers\ConfirmacaoController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\CultoController;
use App\Http\Controllers\DisponibilidadeController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\EscalaController;
use App\Http\Controllers\EuController;
use App\Http\Controllers\FuncaoController;
use App\Http\Controllers\MinhaEscalaController;
use App\Http\Controllers\MinisterioController;
use App\Http\Controllers\PessoaController;
use App\Http\Controllers\PostoController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\TrocaController;
use App\Http\Controllers\TurnoController;
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

    Route::get('/ministerios/{ministerio}/bandas', [BandaController::class, 'index']);
    Route::post('/ministerios/{ministerio}/bandas', [BandaController::class, 'store']);
    Route::patch('/bandas/{banda}', [BandaController::class, 'update']);
    Route::delete('/bandas/{banda}', [BandaController::class, 'destroy']);
    Route::put('/bandas/{banda}/membros', [BandaController::class, 'definirMembros']);

    Route::get('/ministerios/{ministerio}/funcoes', [FuncaoController::class, 'index']);
    Route::post('/ministerios/{ministerio}/funcoes', [FuncaoController::class, 'store']);
    Route::patch('/funcoes/{funcao}', [FuncaoController::class, 'update']);
    Route::delete('/funcoes/{funcao}', [FuncaoController::class, 'destroy']);
    Route::put('/ministerios/{ministerio}/equipe/{pessoa}/funcoes', [FuncaoController::class, 'definirDaPessoa']);

    Route::get('/cultos', [CultoController::class, 'index']);
    Route::post('/cultos', [CultoController::class, 'store']);
    Route::patch('/cultos/{culto}', [CultoController::class, 'update']);
    Route::delete('/cultos/{culto}', [CultoController::class, 'destroy']);
    Route::get('/cultos/{culto}/ocorrencias', [CultoController::class, 'ocorrencias']);

    Route::get('/ministerios/{ministerio}/escalas', [EscalaController::class, 'index']);
    Route::post('/ministerios/{ministerio}/escalas', [EscalaController::class, 'store']);
    Route::get('/escalas/{escala}', [EscalaController::class, 'show']);
    Route::patch('/escalas/{escala}', [EscalaController::class, 'update']);
    Route::post('/escalas/{escala}/liberar', [EscalaController::class, 'liberar']);
    Route::post('/escalas/{escala}/publicar', [EscalaController::class, 'publicar']);
    Route::post('/escalas/{escala}/reabrir', [EscalaController::class, 'reabrir']);

    Route::post('/escalas/{escala}/turnos', [TurnoController::class, 'store']);
    Route::post('/escalas/{escala}/turnos/gerar', [TurnoController::class, 'gerar']);
    Route::patch('/turnos/{turno}', [TurnoController::class, 'update']);
    Route::delete('/turnos/{turno}', [TurnoController::class, 'destroy']);

    Route::get('/escalas/{escala}/disponibilidade', [DisponibilidadeController::class, 'index']);
    Route::post('/escalas/{escala}/disponibilidade/enviar', [DisponibilidadeController::class, 'enviar']);
    Route::put('/turnos/{turno}/disponibilidade', [DisponibilidadeController::class, 'marcar']);

    Route::get('/turnos/{turno}/pessoas', [ConfirmacaoController::class, 'pessoas']);
    Route::post('/turnos/{turno}/confirmacoes', [ConfirmacaoController::class, 'confirmar']);
    Route::put('/turnos/{turno}/confirmacoes/{pessoa}/funcoes', [ConfirmacaoController::class, 'definirFuncoes']);
    Route::delete('/turnos/{turno}/confirmacoes/{pessoa}', [ConfirmacaoController::class, 'remover']);

    Route::post('/turnos/{turno}/trocas', [TrocaController::class, 'store']);
    Route::delete('/trocas/{troca}', [TrocaController::class, 'cancelar']);
    Route::get('/ministerios/{ministerio}/trocas', [TrocaController::class, 'index']);
    Route::get('/trocas/{troca}/candidatos', [TrocaController::class, 'candidatos']);
    Route::post('/trocas/{troca}/manter', [TrocaController::class, 'manter']);
    Route::post('/trocas/{troca}/substituir', [TrocaController::class, 'substituir']);
    Route::post('/trocas/{troca}/vaga-aberta', [TrocaController::class, 'vagaAberta']);

    Route::get('/ministerios/{ministerio}/relatorio', RelatorioController::class);

    Route::get('/minha-escala', MinhaEscalaController::class);
    Route::get('/avisos', [AvisoController::class, 'index']);
    Route::post('/avisos/lidos', [AvisoController::class, 'marcarLidos']);

    Route::get('/pessoas', [PessoaController::class, 'index']);
    Route::post('/pessoas', [PessoaController::class, 'store']);
    Route::patch('/pessoas/{pessoa}', [PessoaController::class, 'update']);
    Route::delete('/pessoas/{pessoa}', [PessoaController::class, 'destroy']);
    Route::post('/pessoas/{pessoa}/convite', [ConviteController::class, 'gerar']);
});
