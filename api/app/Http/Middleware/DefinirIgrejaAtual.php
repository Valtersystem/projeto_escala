<?php

namespace App\Http\Middleware;

use App\Support\IgrejaAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a igreja da requisição a partir da pessoa autenticada. Vai depois de
 * `auth:sanctum`. Pessoa sem vínculo ou inativa perde o acesso (RN-10).
 */
class DefinirIgrejaAtual
{
    public function __construct(private readonly IgrejaAtual $igrejaAtual)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $pessoa = $request->user()?->pessoa;

        if (! $pessoa || ! $pessoa->ativo) {
            return response()->json(['message' => 'Este acesso está desativado. Fale com o líder.'], 403);
        }

        $this->igrejaAtual->definir($pessoa->igreja_id);
        $request->attributes->set('pessoa', $pessoa);

        return $next($request);
    }
}
