<?php

namespace App\Providers;

use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\User;
use App\Support\IgrejaAtual;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        // Convites: limite por IP, para ninguém ficar adivinhando tokens.
        RateLimiter::for('convite', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('login-google', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Recuperação de senha: por IP, para ninguém usar o formulário para encher caixas de e-mail.
        RateLimiter::for('senha', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        $this->definirPermissoes();
    }

    /**
     * Permissões por papel (seção 2 do documento de regras). O Adm gere a igreja
     * (ministérios, líderes, pessoas, cultos); o líder gere o próprio ministério.
     */
    private function definirPermissoes(): void
    {
        Gate::define('adm', fn (User $user) => (bool) $user->pessoa?->adm);

        Gate::define('lidera', fn (User $user, Ministerio $ministerio) => $user->pessoa?->lidera($ministerio) ?? false);

        // Ver um ministério: Adm, líder ou quem serve nele.
        Gate::define('ver-ministerio', function (User $user, Ministerio $ministerio) {
            $pessoa = $user->pessoa;

            return $pessoa && ($pessoa->adm || $pessoa->lidera($ministerio) || $pessoa->serveEm($ministerio));
        });

        // Gerir a equipe: Adm ou o líder do ministério.
        Gate::define('gerir-equipe', function (User $user, Ministerio $ministerio) {
            $pessoa = $user->pessoa;

            return $pessoa && ($pessoa->adm || $pessoa->lidera($ministerio));
        });

        // Convidar ou mexer numa pessoa: Adm, ou líder de algum ministério em que ela serve.
        Gate::define('gerir-pessoa', function (User $user, Pessoa $alvo) {
            $pessoa = $user->pessoa;

            if (! $pessoa) {
                return false;
            }

            if ($pessoa->adm) {
                return true;
            }

            return $alvo->ministerios()->where('lider_id', $pessoa->id)->exists();
        });
    }
}
