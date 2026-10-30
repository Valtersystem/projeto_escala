<?php

namespace App\Http\Controllers;

use App\Domain\EstadoEscala;
use App\Exceptions\RegraDeNegocio;
use App\Models\Disponibilidade;
use App\Models\Escala;
use App\Models\Turno;
use App\Services\CargaDaPessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RN-34 a RN-38: o voluntário marca em que turnos pode servir. */
class DisponibilidadeController extends Controller
{
    /** A tela "Disponibilidade": turnos da escala aberta, o que já marquei e os choques. */
    public function index(Request $request, Escala $escala): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa');
        $this->exigirEquipeEEscalaAberta($pessoa, $escala);

        $carga = CargaDaPessoa::de($pessoa->id);
        $meusPostos = $pessoa->postos()->pluck('postos.id')->all();
        $marcados = Disponibilidade::where('pessoa_id', $pessoa->id)
            ->whereIn('turno_id', $escala->turnos()->pluck('id'))->pluck('turno_id')->all();

        $turnos = $escala->turnos()->with(['posto:id,nome', 'banda:id,nome'])->get()
            ->map(fn (Turno $t) => TurnoController::resumo($t, false) + [
                'marquei' => in_array($t->id, $marcados, true),
                // RN-36: sem nenhum posto marcado, a pessoa vê todos como "do perfil".
                'do_meu_perfil' => $meusPostos === [] || in_array($t->posto_id, $meusPostos, true),
                'choques' => $carga->choquesCom($t)->map(fn ($c) => CargaDaPessoa::descricao($c))->values(),
                'mesmo_dia' => $carga->mesmoDiaCom($t)->map(fn ($c) => CargaDaPessoa::descricao($c))->values(),
            ])->values();

        return response()->json([
            'escala' => [
                'id' => $escala->id,
                'mes' => $escala->mes,
                'prazo' => $escala->prazo?->toDateString(),
                'prazo_encerrado' => $escala->prazoEncerrado(),
            ],
            'total_marcados' => count($marcados),
            'turnos' => $turnos,
        ]);
    }

    /** RN-35: marcar e desmarcar são idempotentes. Depois do prazo, nenhum dos dois (D-02). */
    public function marcar(Request $request, Turno $turno): JsonResponse
    {
        $dados = $request->validate(['marcado' => ['required', 'boolean']]);

        $pessoa = $request->attributes->get('pessoa');
        $escala = $turno->escala;

        $this->exigirEquipeEEscalaAberta($pessoa, $escala);

        if ($escala->prazoEncerrado()) {
            throw new RegraDeNegocio('O prazo para responder esta escala terminou. Fale com o líder.');
        }

        if ($dados['marcado']) {
            Disponibilidade::firstOrCreate(['turno_id' => $turno->id, 'pessoa_id' => $pessoa->id]);
        } else {
            Disponibilidade::where(['turno_id' => $turno->id, 'pessoa_id' => $pessoa->id])->delete();
        }

        // RN-49: o aviso de choque vem na hora, já contando o que acabou de ser marcado.
        $choques = CargaDaPessoa::de($pessoa->id)->choquesCom($turno);

        return response()->json([
            'marcado' => (bool) $dados['marcado'],
            'choques' => $choques->map(fn ($c) => CargaDaPessoa::descricao($c))->values(),
        ]);
    }

    /** RN-37: o envio exige ao menos um turno marcado. Não muda dados; resume o que foi marcado. */
    public function enviar(Request $request, Escala $escala): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa');
        $this->exigirEquipeEEscalaAberta($pessoa, $escala);

        $carga = CargaDaPessoa::de($pessoa->id);
        $marcados = $carga->marcados->where('escala_id', $escala->id);

        if ($marcados->isEmpty()) {
            throw new RegraDeNegocio('Marque pelo menos um turno.');
        }

        return response()->json([
            'total' => $marcados->count(),
            'com_choque' => $marcados->filter(fn (Turno $t) => $carga->choquesCom($t)->isNotEmpty())->count(),
        ]);
    }

    private function exigirEquipeEEscalaAberta($pessoa, Escala $escala): void
    {
        abort_unless($pessoa->serveEm($escala->ministerio), 403, 'Você não está na equipe deste ministério.');

        if ($escala->status !== EstadoEscala::Aberta) {
            throw new RegraDeNegocio('Esta escala não está recebendo disponibilidade.');
        }
    }
}
