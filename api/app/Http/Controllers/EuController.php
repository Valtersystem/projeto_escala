<?php

namespace App\Http\Controllers;

use App\Models\Igreja;
use App\Models\Pessoa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EuController extends Controller
{
    /** Quem sou eu: a pessoa logada, a igreja e os papéis (seção 2 do documento de regras). */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['pessoa' => self::resumo($request->attributes->get('pessoa'))]);
    }

    /** @return array<string, mixed> */
    public static function resumo(Pessoa $pessoa): array
    {
        $igreja = Igreja::find($pessoa->igreja_id);

        return [
            'id' => $pessoa->id,
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'adm' => $pessoa->adm,
            'igreja' => ['id' => $igreja?->id, 'nome' => $igreja?->nome],
            // Consultas por relação já passam pelo escopo da igreja; no login a
            // igreja ainda não foi definida, então filtramos pelo ministério da própria pessoa.
            'ministerios' => $pessoa->ministerios()->withoutGlobalScopes()->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'nome' => $m->nome,
                    'lider' => $m->lider_id === $pessoa->id,
                ])->values(),
        ];
    }
}
