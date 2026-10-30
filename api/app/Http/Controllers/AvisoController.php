<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RN-59 e RN-60: avisos internos de cada pessoa. */
class AvisoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consulta = Aviso::where('pessoa_id', $request->attributes->get('pessoa')->id);

        return response()->json([
            'nao_lidos' => (clone $consulta)->whereNull('lido_em')->count(),
            'avisos' => $consulta->orderByDesc('id')->limit(50)->get()->map(fn (Aviso $a) => [
                'id' => $a->id,
                'texto' => $a->texto,
                'lido' => $a->lido_em !== null,
                'criado_em' => $a->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    /** RN-60: marca todos como lidos. */
    public function marcarLidos(Request $request): JsonResponse
    {
        Aviso::where('pessoa_id', $request->attributes->get('pessoa')->id)
            ->whereNull('lido_em')->update(['lido_em' => now()]);

        return response()->json(['nao_lidos' => 0]);
    }
}
