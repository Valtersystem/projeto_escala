<?php

namespace App\Http\Controllers;

use App\Domain\Modulos;
use App\Models\Ministerio;
use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RN-135: o líder escolhe quais módulos o ministério usa. */
class ModuloController extends Controller
{
    public function index(Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        return $this->resposta($ministerio);
    }

    public function update(Request $request, Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('lidera', $ministerio);

        $dados = $request->validate([
            'ativos' => ['present', 'array'],
            'ativos.*' => ['string', 'distinct', Rule::in(Modulos::chaves())],
        ]);

        $antes = $ministerio->modulos ?? [];
        // Na ordem do catálogo, para o banco não guardar a mesma escolha de duas formas.
        $depois = array_values(array_filter(Modulos::chaves(), fn ($chave) => in_array($chave, $dados['ativos'], true)));

        $ministerio->update(['modulos' => $depois]);

        foreach (array_diff($depois, $antes) as $chave) {
            Registro::feito('ministerio.modulo', 'Ativou o módulo '.Modulos::catalogo()[$chave]['nome']." em {$ministerio->nome}", $ministerio->id, $ministerio->id, null, ['modulo' => $chave, 'ativo' => true]);
        }
        foreach (array_diff($antes, $depois) as $chave) {
            Registro::feito('ministerio.modulo', 'Desativou o módulo '.(Modulos::catalogo()[$chave]['nome'] ?? $chave)." em {$ministerio->nome}", $ministerio->id, $ministerio->id, ['modulo' => $chave, 'ativo' => true], null);
        }

        return $this->resposta($ministerio);
    }

    private function resposta(Ministerio $ministerio): JsonResponse
    {
        return response()->json([
            'modulos' => collect(Modulos::catalogo())->map(fn (array $m, string $chave) => [
                'chave' => $chave,
                'nome' => $m['nome'],
                'descricao' => $m['descricao'],
                'ativo' => $ministerio->usa($chave),
            ])->values(),
        ]);
    }
}
