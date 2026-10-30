<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Domain\EstadoEscala;
use App\Domain\Prazo;
use App\Exceptions\RegraDeNegocio;
use App\Models\Escala;
use App\Models\Igreja;
use App\Models\Ministerio;
use App\Services\CargaDaPessoa;
use App\Support\Avisos;
use App\Support\Concorrencia;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** RN-16 a RN-23: escala do mês, estados e prazo. */
class EscalaController extends Controller
{
    /** RN-18: escala em rascunho só aparece para o líder. */
    public function index(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('ver-ministerio', $ministerio);

        $consulta = $ministerio->escalas()->withCount('turnos')->orderByDesc('mes');

        if (! $request->attributes->get('pessoa')->lidera($ministerio)) {
            $consulta->where('status', '!=', EstadoEscala::Rascunho->value);
        }

        return response()->json(['escalas' => $consulta->get()->map(fn (Escala $e) => $this->resumo($e))->values()]);
    }

    /** A tela inicial do voluntário: escalas abertas dos ministérios em que serve e quantos turnos já marcou em cada. */
    public function abertas(Request $request): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa');

        $escalas = Escala::whereHas('ministerio.equipe', fn ($e) => $e->whereKey($pessoa->id))
            ->where('status', EstadoEscala::Aberta->value)
            ->with('ministerio:id,nome')->withCount('turnos')
            ->orderBy('prazo')->orderBy('mes')->get();

        $marcados = DB::table('disponibilidades')
            ->join('turnos', 'turnos.id', '=', 'disponibilidades.turno_id')
            ->where('disponibilidades.pessoa_id', $pessoa->id)
            ->whereIn('turnos.escala_id', $escalas->pluck('id'))
            ->groupBy('turnos.escala_id')
            ->selectRaw('turnos.escala_id, count(*) as total')
            ->pluck('total', 'escala_id');

        return response()->json([
            'escalas' => $escalas->map(fn (Escala $e) => [
                'id' => $e->id,
                'ministerio' => ['id' => $e->ministerio->id, 'nome' => $e->ministerio->nome],
                'mes' => $e->mes,
                'prazo' => $e->prazo?->toDateString(),
                'prazo_encerrado' => $e->prazoEncerrado(),
                'total_de_turnos' => $e->turnos_count,
                'marquei' => (int) ($marcados[$e->id] ?? 0),
            ])->values(),
        ]);
    }

    /** RN-16/RN-17: uma escala por ministério e mês; o prazo sugerido vem do ajuste da igreja. */
    public function store(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $dados = $request->validate([
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'prazo' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($ministerio->escalas()->where('mes', $dados['mes'])->exists()) {
            throw new RegraDeNegocio('Esse mês já tem escala.');
        }

        $prazoPadrao = Igreja::find(app(IgrejaAtual::class)->id())?->prazo_padrao_dias ?? 5;

        $escala = $ministerio->escalas()->create([
            'mes' => $dados['mes'],
            'status' => EstadoEscala::Rascunho,
            'prazo' => $dados['prazo'] ?? Prazo::sugerido($dados['mes'], $prazoPadrao),
        ]);

        return response()->json(['escala' => $this->resumo($escala->fresh()->loadCount('turnos'))], 201);
    }

    /** O voluntário não vê rascunho e, em escala publicada, só vê os turnos em que está confirmado (RN-23). */
    public function show(Request $request, Escala $escala): JsonResponse
    {
        $ministerio = $escala->ministerio;
        Gate::authorize('ver-ministerio', $ministerio);

        $pessoa = $request->attributes->get('pessoa');
        $lider = $pessoa->lidera($ministerio);

        abort_if($escala->status === EstadoEscala::Rascunho && ! $lider, 404);

        $turnos = $escala->turnos()->with(['posto:id,nome', 'banda:id,nome']);

        if (! $lider && $escala->status === EstadoEscala::Publicada) {
            $turnos->whereHas('confirmacoes', fn ($c) => $c->where('pessoa_id', $pessoa->id));
        }

        // O líder também vê, em cada turno, quantos marcaram e quem já está confirmado (RN-39).
        $temFuncoes = $lider && $ministerio->funcoes()->exists();

        if ($lider) {
            $turnos->withCount(['confirmacoes', 'disponibilidades'])->with(['confirmacoes.pessoa:id,nome', 'confirmacoes.funcoes:id']);
        }

        return response()->json([
            'escala' => $this->resumo($escala->loadCount('turnos')),
            'turnos' => $turnos->get()->map(fn ($t) => TurnoController::resumo($t, $lider) + ($lider ? [
                'confirmados_total' => $t->confirmacoes_count,
                'marcaram_total' => $t->disponibilidades_count,
                'confirmados' => $t->confirmacoes->map(fn ($c) => [
                    'pessoa_id' => $c->pessoa_id,
                    'nome' => $c->pessoa->nome,
                    'sem_funcao' => $temFuncoes && $c->funcoes->isEmpty(),
                ])->values(),
            ] : []))->values(),
        ]);
    }

    /**
     * Antes de publicar, o líder vê o que ainda está torto (RN-21): turnos incompletos, pessoas sem
     * função e choques com outro turno confirmado (D-06). Não impede a publicação; só avisa.
     */
    public function previaDePublicacao(Escala $escala): JsonResponse
    {
        $ministerio = $escala->ministerio;
        Gate::authorize('lidera', $ministerio);

        $turnos = $escala->turnos()->with(['posto:id,nome', 'confirmacoes.pessoa:id,nome', 'confirmacoes.funcoes:id'])->get();
        $temFuncoes = $ministerio->funcoes()->exists();

        $cargas = [];
        $choques = [];
        $semFuncao = 0;

        foreach ($turnos as $turno) {
            foreach ($turno->confirmacoes as $confirmacao) {
                if ($temFuncoes && $confirmacao->funcoes->isEmpty()) {
                    $semFuncao++;
                }

                $cargas[$confirmacao->pessoa_id] ??= CargaDaPessoa::de($confirmacao->pessoa_id);

                foreach ($cargas[$confirmacao->pessoa_id]->conflitosCom($turno) as $outro) {
                    // O mesmo par aparece pelos dois lados; conta uma vez só.
                    $chave = $confirmacao->pessoa_id.'|'.min($turno->id, $outro->id).'|'.max($turno->id, $outro->id);

                    $choques[$chave] = [
                        'pessoa' => ['id' => $confirmacao->pessoa_id, 'nome' => $confirmacao->pessoa->nome],
                        'turno' => CargaDaPessoa::descricao($turno),
                        'com' => CargaDaPessoa::descricao($outro),
                    ];
                }
            }
        }

        return response()->json([
            'total_de_turnos' => $turnos->count(),
            'incompletos' => $turnos->filter(fn ($t) => $t->confirmacoes->count() < $t->vagas)->count(),
            'sem_funcao' => $semFuncao,
            'choques' => array_values($choques),
        ]);
    }

    /** RN-38: o líder pode estender o prazo em qualquer estado. */
    public function update(Request $request, Escala $escala): JsonResponse
    {
        Gate::authorize('lidera', $escala->ministerio);

        $dados = $request->validate([
            'prazo' => ['required', 'date_format:Y-m-d'],
            'versao' => ['required', 'integer'],
        ]);

        $antes = $escala->prazo?->format('Y-m-d');

        Concorrencia::atualizar($escala, $dados['versao'], ['prazo' => $dados['prazo']]);

        Registro::feito('escala.prazo', "Mudou o prazo da escala de {$escala->mes}", $escala->ministerio_id, $escala->id, ['prazo' => $antes], ['prazo' => $dados['prazo']]);

        return response()->json(['escala' => $this->resumo($escala->loadCount('turnos'))]);
    }

    /** RN-19: rascunho → aberta, só com ao menos um turno. */
    public function liberar(Escala $escala): JsonResponse
    {
        return $this->transicionar($escala, EstadoEscala::Rascunho, EstadoEscala::Aberta);
    }

    /** RN-59: ao publicar, cada pessoa confirmada recebe um aviso com data, horário e função. */
    public function publicar(Escala $escala): JsonResponse
    {
        $resposta = $this->transicionar($escala, EstadoEscala::Aberta, EstadoEscala::Publicada);

        $escala->turnos()->with(['confirmacoes.funcoes:id,nome'])->get()->each(
            fn ($turno) => $turno->confirmacoes->each(
                fn ($confirmacao) => Avisos::para($confirmacao->pessoa_id, Avisos::publicacao($turno, $confirmacao)),
            ),
        );

        return $resposta;
    }

    /** RN-22: reabre uma escala publicada. */
    public function reabrir(Escala $escala): JsonResponse
    {
        return $this->transicionar($escala, EstadoEscala::Publicada, EstadoEscala::Aberta);
    }

    private function transicionar(Escala $escala, EstadoEscala $de, EstadoEscala $novo): JsonResponse
    {
        Gate::authorize('lidera', $escala->ministerio);

        if ($escala->status !== $de) {
            throw new RegraDeNegocio("Esta ação vale para escala {$de->value}; a escala está {$escala->status->value}.");
        }

        DB::transaction(function () use ($escala, $novo) {
            $escala->status->validarTransicao($novo, $escala->turnos()->count());

            // `versao` não é preenchível em massa de propósito: só o código controla.
            $escala->forceFill(['status' => $novo, 'versao' => $escala->versao + 1])->save();
        });

        $acao = $de === EstadoEscala::Publicada ? 'reaberta' : ($novo === EstadoEscala::Publicada ? 'publicada' : 'liberada');
        $verbo = ['reaberta' => 'Reabriu', 'publicada' => 'Publicou', 'liberada' => 'Liberou'][$acao];
        Registro::feito("escala.{$acao}", "{$verbo} a escala de {$escala->mes}", $escala->ministerio_id, $escala->id, ['status' => $de->value], ['status' => $novo->value]);

        return response()->json(['escala' => $this->resumo($escala->loadCount('turnos'))]);
    }

    /** @return array<string, mixed> */
    private function resumo(Escala $e): array
    {
        return [
            'id' => $e->id,
            'ministerio_id' => $e->ministerio_id,
            'mes' => $e->mes,
            'status' => $e->status->value,
            'prazo' => $e->prazo?->toDateString(),
            'versao' => $e->versao,
            'total_de_turnos' => $e->turnos_count ?? null,
        ];
    }
}
