<?php

namespace App\Models\Scopes;

use App\Support\IgrejaAtual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Isolamento entre igrejas. Sem igreja definida o resultado é vazio: o escopo
 * "falha fechado" para nunca expor dados de outra igreja por esquecimento.
 * Código de sistema (login, comandos) usa withoutGlobalScopes() de propósito.
 */
final class IgrejaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $igreja = app(IgrejaAtual::class)->id();

        if ($igreja === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('igreja_id'), $igreja);
    }
}
