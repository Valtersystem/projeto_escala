<?php

namespace App\Http\Controllers;

use App\Models\Ministerio;
use App\Models\Posto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PostoController extends Controller
{
    public function index(Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('ver-ministerio', $ministerio);

        return response()->json([
            'postos' => $ministerio->postos()->orderBy('nome')->get()->map(fn (Posto $p) => $this->resumo($p))->values(),
        ]);
    }

    /** RN-13. Só o líder do ministério cria postos. */
    public function store(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $dados = $this->validar($request);

        $posto = $ministerio->postos()->create($dados);

        return response()->json(['posto' => $this->resumo($posto)], 201);
    }

    public function update(Request $request, Posto $posto): JsonResponse
    {
        Gate::authorize('lidera', $posto->ministerio);

        $posto->update($this->validar($request));

        return response()->json(['posto' => $this->resumo($posto)]);
    }

    /** RN-13: apaga os turnos do posto (cascata) e tira o posto das pessoas. */
    public function destroy(Posto $posto): Response
    {
        Gate::authorize('lidera', $posto->ministerio);

        $posto->delete();

        return response()->noContent();
    }

    /**
     * Vagas abaixo de 1 viram 1 (T-12).
     *
     * @return array{nome: string, descricao: ?string, vagas: int}
     */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'vagas' => ['nullable', 'integer'],
        ]);

        return [
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'vagas' => max(1, (int) ($dados['vagas'] ?? 1)),
        ];
    }

    /** @return array<string, mixed> */
    private function resumo(Posto $p): array
    {
        return ['id' => $p->id, 'ministerio_id' => $p->ministerio_id, 'nome' => $p->nome, 'descricao' => $p->descricao, 'vagas' => $p->vagas];
    }
}
