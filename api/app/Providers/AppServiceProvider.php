<?php

namespace App\Providers;

use App\Support\IgrejaAtual;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uma igreja por requisição: cada requisição começa sem igreja definida.
        $this->app->scoped(IgrejaAtual::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 5 tentativas de login por minuto para cada e-mail e IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
