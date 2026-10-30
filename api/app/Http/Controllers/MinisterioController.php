<?php

namespace App\Http\Controllers;

use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MinisterioController extends Controller
{
    /** Adm vê todos; os demais veem só os ministérios em que lideram ou servem. */
    public function index(Request $request): JsonResponse
    {
        $pessoa = $request->attributes->get('pessoa');

        $consulta = Ministerio::query()->with('lider:id,nome')->orderBy('nome');

        if (! $pessoa->adm) {
            $consulta->where(fn ($q) => $q
                ->where('lider_id', $pessoa->id)
                ->orWhereHas('equipe', fn ($e) => $e->whereKey($pessoa->id)));
        }

        return response()->json([
            'ministerios' => $consulta->get()->map(fn (Ministerio $m) => $this->resumo($m))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('adm');

        $dados = $request->validate(['nome' => $this->regraNome()]);

        $ministerio = Ministerio::create($dados);

        return response()->json(['ministerio' => $this->resumo($ministerio)], 201);
    }

    public function update(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('adm');

        $dados = $request->validate(['nome' => $this->regraNome($ministerio)]);

        $ministerio->update($dados);

        return response()->json(['ministerio' => $this->resumo($ministerio)]);
    }

    /** RN-08: apaga postos, escalas e turnos (cascata do banco); as pessoas continuam. */
    public function destroy(Ministerio $ministerio): Response
    {
        Gate::authorize('adm');

        $ministerio->delete();

        return response()->noContent();
    }

    /** RN-07: vincula (ou troca) o líder do ministério. */
    public function definirLider(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('adm');

        $dados = $request->validate([
            'pessoa_id' => ['required', 'integer', Rule::exists('pessoas', 'id')->where('igreja_id', app(IgrejaAtual::class)->id())],
        ]);

        $ministerio->update(['lider_id' => $dados['pessoa_id']]);

        return response()->json(['ministerio' => $this->resumo($ministerio->load('lider:id,nome'))]);
    }

    /** @return list<mixed> */
    private function regraNome(?Ministerio $ministerio = null): array
    {
        return [
            'required', 'string', 'max:120',
            Rule::unique('ministerios', 'nome')
                ->where('igreja_id', app(IgrejaAtual::class)->id())
                ->ignore($ministerio?->id),
        ];
    }

    /** @return array<string, mixed> */
    private function resumo(Ministerio $m): array
    {
        $lider = $m->relationLoaded('lider') ? $m->lider : $m->lider()->first(['id', 'nome']);

        return [
            'id' => $m->id,
            'nome' => $m->nome,
            'lider' => $lider ? ['id' => $lider->id, 'nome' => $lider->nome] : null,
        ];
    }
}
