<?php

use App\Exceptions\RegraDeNegocio;
use App\Http\Middleware\DefinirIgrejaAtual;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A igreja precisa estar definida antes do route model binding, senão o
        // escopo por igreja faria todo registro parecer inexistente.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: DefinirIgrejaAtual::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Violação de regra de negócio vira 422 com a mensagem para a tela.
        $exceptions->render(fn (RegraDeNegocio $e) => response()->json(['message' => $e->getMessage()], 422));
    })->create();
