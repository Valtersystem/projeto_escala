<?php

namespace App\Http\Controllers;

use App\Models\Confirmacao;
use App\Models\Igreja;
use App\Models\Ministerio;
use App\Models\PedidoEntrada;
use App\Models\Pessoa;
use App\Models\Troca;
use App\Models\Turno;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/** RN-124: visão da igreja inteira, só para o Adm. */
class RelatorioGeralController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('adm');

        $mes = $request->validate(['mes' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']])['mes'] ?? null;

        // Escala em rascunho ainda não foi aberta para ninguém responder, então não entra na conta.
        $turnos = Turno::whereHas('escala', fn ($e) => $e
            ->where('status', '!=', 'rascunho')
            ->when($mes, fn ($q) => $q->where('mes', $mes)))
            ->with('escala:id,ministerio_id')
            ->withCount(['confirmacoes', 'disponibilidades'])
            ->get();

        $ministerios = Ministerio::with('lider:id,nome')->withCount(['equipe as voluntarios_ativos' => fn ($q) => $q->where('pessoas.ativo', true)])
            ->orderBy('nome')->get();

        $porMinisterio = $turnos->groupBy(fn (Turno $t) => $t->escala->ministerio_id);

        $linhas = $ministerios->map(function (Ministerio $m) use ($porMinisterio) {
            $resumo = self::resumir($porMinisterio->get($m->id, collect()));

            return [
                'id' => $m->id,
                'nome' => $m->nome,
                'lider' => $m->lider ? ['id' => $m->lider->id, 'nome' => $m->lider->nome] : null,
                'voluntarios_ativos' => (int) $m->voluntarios_ativos,
            ] + $resumo;
        })->values();

        $geral = self::resumir($turnos);

        return response()->json([
            'mes' => $mes,
            'totais' => $geral + [
                'pessoas_ativas' => Pessoa::where('ativo', true)->count(),
                'ministerios_sem_lider' => $ministerios->whereNull('lider_id')->count(),
                'pedidos_de_entrada' => PedidoEntrada::where('estado', 'pendente')->count(),
                'trocas_abertas' => Troca::where('estado', 'aberto')->count(),
            ],
            'ministerios' => $linhas,
            'acima_do_limite' => $mes ? $this->acimaDoLimite($turnos) : null,
        ]);
    }

    /**
     * @param  Collection<int, Turno>  $turnos
     * @return array{turnos: int, vagas: int, vagas_preenchidas_pct: int, turnos_sem_ninguem: int}
     */
    private static function resumir(Collection $turnos): array
    {
        $vagas = $turnos->sum('vagas');
        // Confirmados acima das vagas não contam mais de uma vez (RN-61).
        $preenchidas = $turnos->sum(fn (Turno $t) => min($t->confirmacoes_count, $t->vagas));

        return [
            'turnos' => $turnos->count(),
            'vagas' => (int) $vagas,
            'vagas_preenchidas_pct' => $vagas > 0 ? (int) round($preenchidas / $vagas * 100) : 0,
            'turnos_sem_ninguem' => $turnos->where('disponibilidades_count', 0)->count(),
        ];
    }

    /**
     * RN-44: quem passa do limite de turnos no mês, somando todos os ministérios.
     *
     * @param  Collection<int, Turno>  $turnos
     * @return list<array{pessoa_id: int, nome: string, turnos: int}>
     */
    private function acimaDoLimite(Collection $turnos): array
    {
        $limite = Igreja::find(app(IgrejaAtual::class)->id())?->limite_turnos_mes ?? 3;

        $contagem = Confirmacao::whereIn('turno_id', $turnos->pluck('id'))
            ->selectRaw('pessoa_id, count(*) as total')->groupBy('pessoa_id')->havingRaw('count(*) > ?', [$limite])
            ->pluck('total', 'pessoa_id');

        return Pessoa::whereIn('id', $contagem->keys())->orderBy('nome')->get(['id', 'nome'])
            ->map(fn (Pessoa $p) => ['pessoa_id' => $p->id, 'nome' => $p->nome, 'turnos' => (int) $contagem[$p->id]])
            ->sortByDesc('turnos')->values()->all();
    }
}
