<?php

namespace App\Http\Controllers;

use App\Models\Confirmacao;
use App\Models\Igreja;
use App\Models\Ministerio;
use App\Models\Turno;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** RN-61: relatório do ministério, só para o líder. */
class RelatorioController extends Controller
{
    public function __invoke(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $mes = $request->validate(['mes' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']])['mes'] ?? null;

        // Escala em rascunho ainda não foi aberta para ninguém responder, então não entra na conta.
        $turnos = Turno::whereHas('escala', fn ($e) => $e
            ->where('ministerio_id', $ministerio->id)
            ->where('status', '!=', 'rascunho')
            ->when($mes, fn ($q) => $q->where('mes', $mes)))
            ->with(['posto:id,nome', 'banda:id,nome'])
            ->withCount(['confirmacoes', 'disponibilidades'])
            ->orderBy('data')->orderBy('inicio')->get();

        $vagas = $turnos->sum('vagas');
        // Confirmados acima das vagas não contam mais de uma vez.
        $preenchidas = $turnos->sum(fn (Turno $t) => min($t->confirmacoes_count, $t->vagas));

        $semNinguem = $turnos->where('disponibilidades_count', 0)->values();

        $equipe = $ministerio->equipe()->orderBy('pessoas.nome')->get(['pessoas.id', 'pessoas.nome', 'pessoas.ativo']);

        $porPessoa = Confirmacao::whereIn('turno_id', $turnos->pluck('id'))
            ->selectRaw('pessoa_id, count(*) as total')->groupBy('pessoa_id')->pluck('total', 'pessoa_id');

        // O limite é por mês; só dá para comparar quando o relatório é de um mês.
        $limite = $mes ? (Igreja::find(app(IgrejaAtual::class)->id())?->limite_turnos_mes ?? 3) : null;

        return response()->json([
            'mes' => $mes,
            'total_de_turnos' => $turnos->count(),
            'total_de_vagas' => $vagas,
            'vagas_preenchidas_pct' => $vagas > 0 ? (int) round($preenchidas / $vagas * 100) : 0,
            'turnos_sem_ninguem' => $semNinguem->count(),
            'voluntarios_ativos' => $equipe->where('ativo', true)->count(),
            'turnos_por_pessoa' => $equipe->map(fn ($p) => [
                'pessoa_id' => $p->id,
                'nome' => $p->nome,
                'turnos' => (int) ($porPessoa[$p->id] ?? 0),
                'acima_do_limite' => $limite === null ? null : ($porPessoa[$p->id] ?? 0) > $limite,
            ])->sortByDesc('turnos')->values(),
            'onde_aperta' => $semNinguem->map(fn (Turno $t) => TurnoController::resumo($t, true))->values(),
        ]);
    }
}
