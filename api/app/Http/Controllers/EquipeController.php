<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipeController extends Controller
{
    /** Inclui na equipe uma pessoa que já existe na igreja. */
    public function store(Request $request, Ministerio $ministerio): Response
    {
        Gate::authorize('gerir-equipe', $ministerio);

        $dados = $request->validate([
            'pessoa_id' => ['required', 'integer', Rule::exists('pessoas', 'id')->where('igreja_id', app(IgrejaAtual::class)->id())],
        ]);

        $ministerio->equipe()->syncWithoutDetaching([$dados['pessoa_id']]);

        Registro::feito('equipe.incluida', 'Incluiu '.Pessoa::find($dados['pessoa_id'])?->nome." na equipe de {$ministerio->nome}", $ministerio->id, $dados['pessoa_id']);

        return response()->noContent(201);
    }

    /**
     * Quem na igreja ainda não está na equipe (e está ativo), para o líder incluir (RN-09). Mostra só o
     * nome e onde a pessoa já serve, como o protótipo fazia.
     */
    public function deFora(Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('gerir-equipe', $ministerio);

        $pessoas = Pessoa::where('ativo', true)
            ->whereDoesntHave('ministerios', fn ($m) => $m->whereKey($ministerio->id))
            ->with('ministerios:id,nome')->orderBy('nome')->get();

        return response()->json([
            'pessoas' => $pessoas->map(fn (Pessoa $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'ministerios' => $p->ministerios->pluck('nome')->values(),
            ])->values(),
        ]);
    }

    /** Os postos que a pessoa atende neste ministério (os de outros ministérios não mudam). */
    public function definirPostos(Request $request, Ministerio $ministerio, Pessoa $pessoa): JsonResponse
    {
        Gate::authorize('gerir-equipe', $ministerio);

        $dados = $request->validate([
            'posto_ids' => ['present', 'array'],
            'posto_ids.*' => ['integer', 'distinct'],
        ]);

        $doMinisterio = $ministerio->postos()->pluck('id')->all();

        if (array_diff($dados['posto_ids'], $doMinisterio) !== []) {
            throw ValidationException::withMessages(['posto_ids' => ['Use só postos deste ministério.']]);
        }

        if (! $pessoa->serveEm($ministerio)) {
            throw ValidationException::withMessages(['pessoa' => ['Esta pessoa não está na equipe do ministério.']]);
        }

        $pessoa->postos()->detach($doMinisterio);
        $pessoa->postos()->attach($dados['posto_ids']);

        return response()->json(['posto_ids' => $pessoa->postos()->whereIn('postos.id', $doMinisterio)->pluck('postos.id')->values()]);
    }

    /** RN-11: sair da equipe tira também os postos, as funções e as bandas daquele ministério. */
    public function destroy(Ministerio $ministerio, Pessoa $pessoa): Response
    {
        Gate::authorize('gerir-equipe', $ministerio);

        $pessoa->ministerios()->detach($ministerio->id);
        $pessoa->postos()->detach($ministerio->postos()->pluck('id'));
        $pessoa->bandas()->detach($ministerio->bandas()->pluck('id'));
        $pessoa->funcoes()->detach($ministerio->funcoes()->pluck('id'));

        Registro::feito('equipe.removida', "Tirou {$pessoa->nome} da equipe de {$ministerio->nome}", $ministerio->id, $pessoa->id);

        return response()->noContent();
    }
}
