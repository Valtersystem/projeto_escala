<?php

namespace App\Http\Controllers;

use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Support\IgrejaAtual;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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

        return response()->noContent(201);
    }

    /** RN-11: sair da equipe tira também os postos, as funções e as bandas daquele ministério. */
    public function destroy(Ministerio $ministerio, Pessoa $pessoa): Response
    {
        Gate::authorize('gerir-equipe', $ministerio);

        $pessoa->ministerios()->detach($ministerio->id);
        $pessoa->postos()->detach($ministerio->postos()->pluck('id'));
        $pessoa->bandas()->detach($ministerio->bandas()->pluck('id'));
        $pessoa->funcoes()->detach($ministerio->funcoes()->pluck('id'));

        return response()->noContent();
    }
}
