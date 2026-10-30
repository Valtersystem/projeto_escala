<?php

namespace App\Http\Controllers;

use App\Domain\EstadoEscala;
use App\Exceptions\RegraDeNegocio;
use App\Models\Confirmacao;
use App\Models\Igreja;
use App\Models\Pessoa;
use App\Models\Troca;
use App\Models\Turno;
use App\Services\CargaDaPessoa;
use App\Support\Avisos;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** RN-39 a RN-44: o líder confirma quem fica em cada vaga. */
class ConfirmacaoController extends Controller
{
    /** A folha do turno: quem está confirmado e quem marcou disponibilidade (RN-39, RN-65). */
    public function pessoas(Turno $turno): JsonResponse
    {
        Gate::authorize('lidera', $turno->escala->ministerio);

        $turno->load(['posto:id,nome', 'banda.membros:id']);
        $membrosDaBanda = $turno->banda?->membros->pluck('id')->all() ?? [];
        $limite = Igreja::find(app(IgrejaAtual::class)->id())?->limite_turnos_mes ?? 3;
        $mes = $turno->data->format('Y-m');

        $confirmacoes = $turno->confirmacoes()->with(['pessoa:id,nome', 'funcoes:id,nome'])->get();
        $confirmados = $confirmacoes->pluck('pessoa_id')->all();

        $confirmadosSaida = $confirmacoes->map(fn (Confirmacao $c) => [
            'pessoa_id' => $c->pessoa_id,
            'nome' => $c->pessoa->nome,
            'funcoes' => $c->funcoes->map(fn ($f) => ['id' => $f->id, 'nome' => $f->nome])->values(),
            'fora_da_banda' => $this->foraDaBanda($turno, $membrosDaBanda, $c->pessoa_id),
        ])->values();

        // RN-39 e RN-65: quem marcou e ainda não foi confirmado; inativos ficam de fora.
        $disponiveis = $turno->pessoasDisponiveis()->where('pessoas.ativo', true)
            ->whereNotIn('pessoas.id', $confirmados)->orderBy('pessoas.nome')->get(['pessoas.id', 'pessoas.nome'])
            ->map(function (Pessoa $p) use ($turno, $limite, $mes, $membrosDaBanda) {
                $carga = CargaDaPessoa::de($p->id);
                $noMes = $carga->turnosNoMes($mes);

                return [
                    'pessoa_id' => $p->id,
                    'nome' => $p->nome,
                    'turnos_no_mes' => $noMes,
                    'carga' => $noMes > $limite, // RN-44: só alerta
                    'choques' => $carga->conflitosCom($turno)->map(fn ($c) => CargaDaPessoa::descricao($c))->values(),
                    'fora_da_banda' => $this->foraDaBanda($turno, $membrosDaBanda, $p->id),
                ];
            })->values();

        return response()->json([
            'turno' => TurnoController::resumo($turno, true),
            'lotado' => $confirmados !== [] && count($confirmados) >= $turno->vagas,
            'confirmados' => $confirmadosSaida,
            'disponiveis' => $disponiveis,
        ]);
    }

    /**
     * RN-40: bloqueia quando o turno está lotado; choque não bloqueia, mas avisa a
     * pessoa (RN-41). Vale em escala aberta ou publicada.
     */
    public function confirmar(Request $request, Turno $turno): JsonResponse
    {
        $escala = $turno->escala;
        $ministerio = $escala->ministerio;
        Gate::authorize('lidera', $ministerio);

        if ($escala->status === EstadoEscala::Rascunho) {
            throw new RegraDeNegocio('Libere a escala antes de confirmar pessoas.');
        }

        $dados = $request->validate([
            'pessoa_id' => ['required', 'integer', Rule::exists('pessoas', 'id')->where('igreja_id', $turno->igreja_id)],
            'funcao_ids' => ['sometimes', 'array'],
            'funcao_ids.*' => ['integer', 'distinct'],
        ]);

        $alvo = Pessoa::findOrFail($dados['pessoa_id']);
        $funcoes = $dados['funcao_ids'] ?? [];

        $this->validarFuncoes($turno, $funcoes);

        if (! $alvo->ativo) {
            throw new RegraDeNegocio('Esta pessoa está inativa.');
        }

        if (! $alvo->serveEm($ministerio)) {
            throw new RegraDeNegocio('Esta pessoa não está na equipe do ministério.');
        }

        $conflitos = CargaDaPessoa::de($alvo->id)->conflitosCom($turno);

        $confirmacao = DB::transaction(function () use ($turno, $alvo, $funcoes) {
            // Trava o turno para duas confirmações simultâneas não passarem das vagas.
            $travado = Turno::whereKey($turno->id)->lockForUpdate()->first();

            if ($travado->confirmacoes()->where('pessoa_id', $alvo->id)->exists()) {
                throw new RegraDeNegocio('Esta pessoa já está confirmada neste turno.');
            }

            if ($travado->confirmacoes()->count() >= $travado->vagas) {
                throw new RegraDeNegocio('Turno lotado. Aumente as vagas para confirmar mais gente.');
            }

            $confirmacao = $travado->confirmacoes()->create(['pessoa_id' => $alvo->id]);
            $confirmacao->funcoes()->sync($funcoes);

            return $confirmacao->load('funcoes:id,nome');
        });

        $turno->load(['escala.ministerio:id,nome', 'posto:id,nome']);

        if ($conflitos->isNotEmpty()) {
            Avisos::para($alvo->id, Avisos::choque(Avisos::resumoDoTurno($turno), Avisos::resumoDoTurno($conflitos->first())));
        }

        // Quem entra depois da publicação precisa saber, senão ninguém avisa.
        if ($escala->status === EstadoEscala::Publicada) {
            Avisos::para($alvo->id, Avisos::publicacao($turno, $confirmacao));
        }

        return response()->json([
            'confirmacao' => $this->resumo($confirmacao),
            'choques' => $conflitos->map(fn ($c) => CargaDaPessoa::descricao($c))->values(),
        ], 201);
    }

    /** RN-43: trocar as funções de quem já está confirmado. */
    public function definirFuncoes(Request $request, Turno $turno, Pessoa $pessoa): JsonResponse
    {
        Gate::authorize('lidera', $turno->escala->ministerio);

        $dados = $request->validate([
            'funcao_ids' => ['present', 'array'],
            'funcao_ids.*' => ['integer', 'distinct'],
        ]);

        $this->validarFuncoes($turno, $dados['funcao_ids']);

        $confirmacao = $turno->confirmacoes()->where('pessoa_id', $pessoa->id)->first();

        if (! $confirmacao) {
            throw new RegraDeNegocio('Esta pessoa não está confirmada neste turno.');
        }

        $confirmacao->funcoes()->sync($dados['funcao_ids']);

        return response()->json(['confirmacao' => $this->resumo($confirmacao->load('funcoes:id,nome'))]);
    }

    /** RN-42: tira a pessoa do turno e leva as funções dela junto (cascata do banco). */
    public function remover(Turno $turno, Pessoa $pessoa): Response
    {
        Gate::authorize('lidera', $turno->escala->ministerio);

        $turno->confirmacoes()->where('pessoa_id', $pessoa->id)->delete();

        // Se a pessoa tinha pedido substituição, o líder já resolveu tirando-a: o pedido se encerra.
        Troca::where('turno_id', $turno->id)->where('pessoa_id', $pessoa->id)->where('estado', 'aberto')
            ->update(['estado' => 'resolvido', 'resolucao' => 'vaga_aberta']);

        return response()->noContent();
    }

    /** @param  list<int>  $funcaoIds */
    private function validarFuncoes(Turno $turno, array $funcaoIds): void
    {
        $doMinisterio = $turno->escala->ministerio->funcoes()->pluck('id')->all();

        if (array_diff($funcaoIds, $doMinisterio) !== []) {
            throw ValidationException::withMessages(['funcao_ids' => ['Use só funções deste ministério.']]);
        }
    }

    /** RN-33: banda é fixa; quem não é membro entra mesmo assim, mas o líder vê o aviso. */
    private function foraDaBanda(Turno $turno, array $membrosDaBanda, int $pessoaId): bool
    {
        return $turno->banda_id !== null && ! in_array($pessoaId, $membrosDaBanda, true);
    }

    /** @return array<string, mixed> */
    private function resumo(Confirmacao $c): array
    {
        return [
            'turno_id' => $c->turno_id,
            'pessoa_id' => $c->pessoa_id,
            'funcoes' => $c->funcoes->map(fn ($f) => ['id' => $f->id, 'nome' => $f->nome])->values(),
        ];
    }
}
