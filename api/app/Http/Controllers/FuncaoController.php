<?php

namespace App\Http\Controllers;

use App\Models\Funcao;
use App\Models\Ministerio;
use App\Models\Pessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** RN-14: funções do ministério (voz, guitarra, bateria...). */
class FuncaoController extends Controller
{
    public function index(Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('ver-ministerio', $ministerio);

        return response()->json([
            'funcoes' => $ministerio->funcoes()->orderBy('nome')->get(['id', 'ministerio_id', 'nome'])->values(),
        ]);
    }

    public function store(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $dados = $request->validate(['nome' => ['required', 'string', 'max:120']]);

        $funcao = $ministerio->funcoes()->create($dados);

        return response()->json(['funcao' => $funcao->only(['id', 'ministerio_id', 'nome'])], 201);
    }

    public function update(Request $request, Funcao $funcao): JsonResponse
    {
        Gate::authorize('lidera', $funcao->ministerio);

        $funcao->update($request->validate(['nome' => ['required', 'string', 'max:120']]));

        return response()->json(['funcao' => $funcao->only(['id', 'ministerio_id', 'nome'])]);
    }

    /** RN-14: some das pessoas e dos turnos (cascata do banco). */
    public function destroy(Funcao $funcao): Response
    {
        Gate::authorize('lidera', $funcao->ministerio);

        $funcao->delete();

        return response()->noContent();
    }

    /** Define o que a pessoa já faz neste ministério (as funções de outros ministérios não mudam). */
    public function definirDaPessoa(Request $request, Ministerio $ministerio, Pessoa $pessoa): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $dados = $request->validate([
            'funcao_ids' => ['present', 'array'],
            'funcao_ids.*' => ['integer', 'distinct'],
        ]);

        $doMinisterio = $ministerio->funcoes()->pluck('id')->all();

        if (array_diff($dados['funcao_ids'], $doMinisterio) !== []) {
            throw ValidationException::withMessages(['funcao_ids' => ['Use só funções deste ministério.']]);
        }

        if (! $pessoa->serveEm($ministerio)) {
            throw ValidationException::withMessages(['pessoa' => ['Esta pessoa não está na equipe do ministério.']]);
        }

        $pessoa->funcoes()->detach($doMinisterio);
        $pessoa->funcoes()->attach($dados['funcao_ids']);

        return response()->json(['funcao_ids' => $pessoa->funcoes()->whereIn('funcoes.id', $doMinisterio)->pluck('funcoes.id')->values()]);
    }
}
