<?php

namespace App\Http\Controllers;

use App\Domain\GradeDeCultos;
use App\Domain\Horario;
use App\Models\Culto;
use App\Rules\HorarioValido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RN-03 a RN-06: grade de cultos da igreja. Todos veem; só o Adm altera. */
class CultoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'cultos' => Culto::orderBy('dia_semana')->orderBy('inicio')->get()->map(fn (Culto $c) => $this->resumo($c))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('adm');

        $culto = Culto::create($this->validar($request));

        return response()->json(['culto' => $this->resumo($culto)], 201);
    }

    public function update(Request $request, Culto $culto): JsonResponse
    {
        Gate::authorize('adm');

        $culto->update($this->validar($request));

        return response()->json(['culto' => $this->resumo($culto)]);
    }

    /** RN-06: os turnos já gerados continuam, com o nome do culto guardado. */
    public function destroy(Culto $culto): Response
    {
        Gate::authorize('adm');

        // O banco só solta o culto dos turnos; o nome fica guardado no turno ao gerar.
        $culto->delete();

        return response()->noContent();
    }

    /** RN-04/05: datas em que o culto acontece no mês (AAAA-MM). */
    public function ocorrencias(Request $request, Culto $culto): JsonResponse
    {
        $dados = $request->validate(['mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        return response()->json([
            'mes' => $dados['mes'],
            'datas' => GradeDeCultos::ocorrencias($culto->tipo, $culto->dia_semana, $culto->ordem, $dados['mes']),
        ]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['semanal', 'mensal'])],
            'dia_semana' => ['required', 'integer', 'between:0,6'],
            'ordem' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5, -1])],
            'inicio' => ['required', new HorarioValido()],
            'fim' => ['required', new HorarioValido()],
        ]);

        return [
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'dia_semana' => $dados['dia_semana'],
            'ordem' => $dados['tipo'] === 'mensal' ? ($dados['ordem'] ?? 1) : 1,
            'inicio' => Horario::formatar($dados['inicio']),
            'fim' => Horario::formatar($dados['fim']),
        ];
    }

    /** @return array<string, mixed> */
    private function resumo(Culto $c): array
    {
        return [
            'id' => $c->id,
            'nome' => $c->nome,
            'tipo' => $c->tipo,
            'dia_semana' => $c->dia_semana,
            'ordem' => $c->ordem,
            'inicio' => substr((string) $c->inicio, 0, 5),
            'fim' => substr((string) $c->fim, 0, 5),
        ];
    }
}
