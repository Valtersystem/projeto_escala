<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Ministerio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** RN-125: histórico de alterações. O Adm vê a igreja toda; o líder, só o ministério dele. */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 30;

    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ministerio_id' => ['nullable', 'integer'],
            'entidade' => ['nullable', 'string', 'max:64'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $ator = $request->attributes->get('pessoa');
        $consulta = Auditoria::query();

        if (isset($dados['ministerio_id'])) {
            $ministerio = Ministerio::findOrFail($dados['ministerio_id']);
            Gate::authorize('lidera', $ministerio);
            $consulta->where('ministerio_id', $ministerio->id);
        } else {
            abort_unless($ator->adm, 403);
        }

        if (isset($dados['entidade'])) {
            $consulta->where('entidade', $dados['entidade']);
        }

        $pagina = $consulta->with('pessoa:id,nome')->orderByDesc('id')->paginate(self::POR_PAGINA, ['*'], 'pagina', $dados['pagina'] ?? 1);

        return response()->json([
            'registros' => collect($pagina->items())->map(fn (Auditoria $a) => [
                'id' => $a->id,
                'quando' => $a->created_at->toIso8601String(),
                'quem' => $a->pessoa ? ['id' => $a->pessoa->id, 'nome' => $a->pessoa->nome] : null,
                'acao' => $a->acao,
                'descricao' => $a->descricao,
                'antes' => $a->antes,
                'depois' => $a->depois,
            ])->values(),
            'pagina' => $pagina->currentPage(),
            'tem_mais' => $pagina->hasMorePages(),
        ]);
    }
}
