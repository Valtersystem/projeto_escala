<?php

namespace App\Http\Controllers;

use App\Models\Troca;
use App\Models\Turno;
use App\Services\CargaDaPessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RN-23: "Minha escala" mostra só turnos confirmados em escala publicada. */
class MinhaEscalaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa');

        $dados = $request->validate(['mes' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $carga = CargaDaPessoa::de($pessoa->id);
        $pedidosAbertos = Troca::where('pessoa_id', $pessoa->id)->where('estado', 'aberto')->pluck('id', 'turno_id');

        $turnos = Turno::whereHas('confirmacoes', fn ($c) => $c->where('pessoa_id', $pessoa->id))
            ->whereHas('escala', fn ($e) => $e->where('status', 'publicada'))
            ->when($dados['mes'] ?? null, fn ($q, $mes) => $q->whereBetween('data', ["{$mes}-01", "{$mes}-31"]))
            ->with(['escala.ministerio:id,nome', 'posto:id,nome', 'banda:id,nome', 'confirmacoes' => fn ($c) => $c->where('pessoa_id', $pessoa->id)->with('funcoes:id,nome')])
            ->orderBy('data')->orderBy('inicio')->get();

        return response()->json([
            'turnos' => $turnos->map(fn (Turno $t) => TurnoController::resumo($t, false) + [
                'ministerio' => $t->escala->ministerio->nome,
                'funcoes' => $t->confirmacoes->first()->funcoes->map(fn ($f) => ['id' => $f->id, 'nome' => $f->nome])->values(),
                // RN-48: choque com outro turno confirmado, até entre bandas do mesmo ministério.
                'choque' => $carga->conflitosCom($t)->map(fn ($c) => CargaDaPessoa::descricao($c))->values(),
                // RN-53: quem pediu substituição continua na escala, com o pedido aberto à mostra.
                'troca_aberta_id' => $pedidosAbertos[$t->id] ?? null,
            ])->values(),
        ]);
    }
}
