<?php

namespace App\Http\Controllers;

use App\Models\Banda;
use App\Models\Ministerio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** RN-15 e RN-29: bandas do ministério, com membros fixos e ordem do rodízio. */
class BandaController extends Controller
{
    public function index(Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('ver-ministerio', $ministerio);

        return response()->json([
            'bandas' => $ministerio->bandas()->with('membros:id,nome')->get()->map(fn (Banda $b) => $this->resumo($b))->values(),
        ]);
    }

    public function store(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'ordem' => ['nullable', 'integer', 'min:1'],
        ]);

        // Sem ordem informada, a banda entra no fim da fila do rodízio.
        $ordem = $dados['ordem'] ?? (($ministerio->bandas()->max('ordem') ?? 0) + 1);

        $banda = $ministerio->bandas()->create(['nome' => $dados['nome'], 'ordem' => $ordem]);

        return response()->json(['banda' => $this->resumo($banda->load('membros:id,nome'))], 201);
    }

    public function update(Request $request, Banda $banda): JsonResponse
    {
        Gate::authorize('lidera', $banda->ministerio);

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:120'],
            'ordem' => ['sometimes', 'integer', 'min:1'],
        ]);

        $banda->update($dados);

        return response()->json(['banda' => $this->resumo($banda->load('membros:id,nome'))]);
    }

    /** Os turnos da banda continuam; só ficam sem banda (cascata do banco). */
    public function destroy(Banda $banda): Response
    {
        Gate::authorize('lidera', $banda->ministerio);

        $banda->delete();

        return response()->noContent();
    }

    /** RN-15: define os membros fixos. Só entra quem está na equipe do ministério. */
    public function definirMembros(Request $request, Banda $banda): JsonResponse
    {
        Gate::authorize('lidera', $banda->ministerio);

        $dados = $request->validate([
            'pessoa_ids' => ['present', 'array'],
            'pessoa_ids.*' => ['integer', 'distinct'],
        ]);

        $equipe = $banda->ministerio->equipe()->pluck('pessoas.id')->all();

        if (array_diff($dados['pessoa_ids'], $equipe) !== []) {
            throw ValidationException::withMessages(['pessoa_ids' => ['Só quem está na equipe do ministério pode ser da banda.']]);
        }

        $banda->membros()->sync($dados['pessoa_ids']);

        return response()->json(['banda' => $this->resumo($banda->load('membros:id,nome'))]);
    }

    /** @return array<string, mixed> */
    private function resumo(Banda $b): array
    {
        return [
            'id' => $b->id,
            'ministerio_id' => $b->ministerio_id,
            'nome' => $b->nome,
            'ordem' => $b->ordem,
            'membros' => $b->membros->map(fn ($p) => ['id' => $p->id, 'nome' => $p->nome])->values(),
        ];
    }
}
