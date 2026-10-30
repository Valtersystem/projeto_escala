<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Domain\EstadoEscala;
use App\Exceptions\RegraDeNegocio;
use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\Troca;
use App\Models\Turno;
use App\Services\CargaDaPessoa;
use App\Services\Substituicao;
use App\Support\Avisos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RN-51 a RN-58: pedido de substituição. */
class TrocaController extends Controller
{
    /** RN-51 a RN-53: quem está confirmado em escala publicada pede; continua na escala até o líder resolver. */
    public function store(Request $request, Turno $turno): JsonResponse
    {
        $dados = $request->validate([
            'motivo' => ['required', Rule::in(Troca::MOTIVOS)],
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);

        $pessoa = $request->attributes->get('pessoa');
        $escala = $turno->escala;

        if ($escala->status !== EstadoEscala::Publicada) {
            throw new RegraDeNegocio('Só dá para pedir substituição em escala publicada.');
        }

        if (! $turno->confirmacoes()->where('pessoa_id', $pessoa->id)->exists()) {
            throw new RegraDeNegocio('Você não está confirmado neste turno.');
        }

        // RN-52: um pedido aberto por pessoa e turno.
        if ($this->abertas()->where('turno_id', $turno->id)->where('pessoa_id', $pessoa->id)->exists()) {
            throw new RegraDeNegocio('Você já tem um pedido aberto nesse turno.');
        }

        $troca = Troca::create([
            'turno_id' => $turno->id,
            'pessoa_id' => $pessoa->id,
            'motivo' => $dados['motivo'],
            'observacao' => $dados['observacao'] ?? null,
            'estado' => 'aberto',
        ]);

        $turno->load('posto:id,nome');
        $this->avisarLider($escala->ministerio, Avisos::pedidoDeTroca($pessoa->nome, $turno));

        return response()->json(['troca' => $this->resumo($troca->load(['pessoa:id,nome', 'turno.posto:id,nome', 'turno.banda:id,nome']))], 201);
    }

    /** RN-54: quem pediu cancela; segue no turno e o líder é avisado. */
    public function cancelar(Request $request, Troca $troca): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa');

        abort_unless($troca->pessoa_id === $pessoa->id, 403);

        if ($troca->estado !== 'aberto') {
            throw new RegraDeNegocio('Este pedido já foi resolvido.');
        }

        $troca->update(['estado' => 'cancelado']);

        $this->avisarLider($troca->turno->escala->ministerio, Avisos::pedidoCancelado($pessoa->nome));

        return response()->json(['troca' => $this->resumo($troca->load(['pessoa:id,nome', 'turno.posto:id,nome', 'turno.banda:id,nome']))]);
    }

    /** RN-58: os pedidos do ministério e quantos estão abertos (o número da aba). */
    public function index(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $estado = $request->validate(['estado' => ['nullable', Rule::in(['aberto', 'resolvido', 'cancelado'])]])['estado'] ?? 'aberto';

        $doMinisterio = Troca::whereHas('turno.escala', fn ($e) => $e->where('ministerio_id', $ministerio->id));

        $trocas = (clone $doMinisterio)->where('estado', $estado)
            ->with(['pessoa:id,nome', 'turno.posto:id,nome', 'turno.banda:id,nome'])->orderBy('id')->get();

        return response()->json([
            'total_abertas' => (clone $doMinisterio)->where('estado', 'aberto')->count(),
            'trocas' => $trocas->map(fn (Troca $t) => $this->resumo($t) + [
                'marcaram' => $t->turno->disponibilidades()->count(),
            ])->values(),
        ]);
    }

    /** RN-56: quem pode entrar no lugar. */
    public function candidatos(Troca $troca): JsonResponse
    {
        $turno = $this->turnoDoPedidoAberto($troca);

        return response()->json([
            'candidatos' => Substituicao::candidatos($turno, $troca->pessoa)->map(fn (array $c) => [
                'pessoa_id' => $c['pessoa']->id,
                'nome' => $c['pessoa']->nome,
                'marcou' => $c['marcou'],
                'turnos_no_total' => $c['turnos_no_total'],
                'fora_da_banda' => $c['fora_da_banda'],
                'choques' => $c['choques']->map(fn ($t) => CargaDaPessoa::descricao($t))->values(),
            ])->values(),
        ]);
    }

    /** RN-55: o líder pede que a pessoa continue. */
    public function manter(Troca $troca): JsonResponse
    {
        $turno = $this->turnoDoPedidoAberto($troca);
        $this->exigirConfirmado($turno, $troca);

        $troca->update(['estado' => 'resolvido', 'resolucao' => 'mantido']);
        Avisos::para($troca->pessoa_id, Avisos::trocaMantida($turno));
        Registro::feito('troca.mantida', "Pediu que {$troca->pessoa->nome} continue em ".Registro::turno($turno), $turno->escala->ministerio_id, $troca->id);

        return $this->resposta($troca);
    }

    /** RN-55 e RN-57: outra pessoa assume; herda as funções; os dois são avisados; choque não impede. */
    public function substituir(Request $request, Troca $troca): JsonResponse
    {
        $turno = $this->turnoDoPedidoAberto($troca);

        $dados = $request->validate(['pessoa_id' => ['required', 'integer']]);

        $escolhido = Substituicao::candidatos($turno, $troca->pessoa)->first(fn (array $c) => $c['pessoa']->id === $dados['pessoa_id']);

        if (! $escolhido) {
            throw new RegraDeNegocio('Esta pessoa não pode assumir este turno.');
        }

        $substituto = $escolhido['pessoa'];
        $funcoes = '';

        DB::transaction(function () use ($turno, $troca, $substituto, &$funcoes) {
            $confirmacao = $this->exigirConfirmado($turno, $troca)->load('funcoes:id,nome');
            $herdadas = $confirmacao->funcoes;
            $funcoes = $herdadas->pluck('nome')->implode(' · ');

            $confirmacao->delete();
            $turno->confirmacoes()->create(['pessoa_id' => $substituto->id])->funcoes()->sync($herdadas->pluck('id')->all());

            $troca->update(['estado' => 'resolvido', 'resolucao' => 'substituido', 'substituto_id' => $substituto->id]);
        });

        $nomeQueSaiu = $troca->pessoa->nome;
        Registro::feito('troca.substituida', "{$substituto->nome} entrou no lugar de {$nomeQueSaiu} em ".Registro::turno($turno), $turno->escala->ministerio_id, $troca->id, ['pessoa_id' => $troca->pessoa_id], ['pessoa_id' => $substituto->id]);
        Avisos::para($troca->pessoa_id, Avisos::trocaSubstituida($substituto->nome, $turno));
        Avisos::para($substituto->id, Avisos::entrouNoLugar($nomeQueSaiu, $turno, $funcoes));

        return $this->resposta($troca);
    }

    /** RN-55: a pessoa é liberada e a vaga volta a ficar aberta. */
    public function vagaAberta(Troca $troca): JsonResponse
    {
        $turno = $this->turnoDoPedidoAberto($troca);

        DB::transaction(function () use ($turno, $troca) {
            $this->exigirConfirmado($turno, $troca)->delete();
            $troca->update(['estado' => 'resolvido', 'resolucao' => 'vaga_aberta']);
        });

        Avisos::para($troca->pessoa_id, Avisos::vagaAberta($turno));
        Registro::feito('troca.vaga_aberta', "Liberou {$troca->pessoa->nome} e abriu a vaga em ".Registro::turno($turno), $turno->escala->ministerio_id, $troca->id);

        return $this->resposta($troca);
    }

    /** Só o líder do ministério resolve, e só pedido aberto. */
    private function turnoDoPedidoAberto(Troca $troca): Turno
    {
        $turno = $troca->turno()->with(['escala.ministerio', 'posto:id,nome', 'banda'])->first();

        Gate::authorize('lidera', $turno->escala->ministerio);

        if ($troca->estado !== 'aberto') {
            throw new RegraDeNegocio('Este pedido já foi resolvido.');
        }

        return $turno;
    }

    private function exigirConfirmado(Turno $turno, Troca $troca)
    {
        $confirmacao = $turno->confirmacoes()->where('pessoa_id', $troca->pessoa_id)->first();

        if (! $confirmacao) {
            throw new RegraDeNegocio('A pessoa não está mais confirmada neste turno.');
        }

        return $confirmacao;
    }

    private function avisarLider(Ministerio $ministerio, string $texto): void
    {
        if ($ministerio->lider_id) {
            Avisos::para($ministerio->lider_id, $texto);
        }
    }

    private function abertas()
    {
        return Troca::where('estado', 'aberto');
    }

    private function resposta(Troca $troca): JsonResponse
    {
        return response()->json(['troca' => $this->resumo($troca->fresh(['pessoa:id,nome', 'turno.posto:id,nome', 'turno.banda:id,nome']))]);
    }

    /** @return array<string, mixed> */
    private function resumo(Troca $t): array
    {
        return [
            'id' => $t->id,
            'estado' => $t->estado,
            'resolucao' => $t->resolucao,
            'motivo' => $t->motivo,
            'observacao' => $t->observacao,
            'substituto_id' => $t->substituto_id,
            'pessoa' => ['id' => $t->pessoa->id, 'nome' => $t->pessoa->nome],
            'turno' => TurnoController::resumo($t->turno, true),
        ];
    }
}
