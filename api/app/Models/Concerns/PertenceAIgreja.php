<?php

namespace App\Models\Concerns;

use App\Models\Igreja;
use App\Models\Scopes\IgrejaScope;
use App\Support\IgrejaAtual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para modelos com `igreja_id`: filtra pela igreja atual e preenche o campo ao criar.
 */
trait PertenceAIgreja
{
    public static function bootPertenceAIgreja(): void
    {
        static::addGlobalScope(new IgrejaScope());

        static::creating(function (Model $modelo): void {
            if ($modelo->getAttribute('igreja_id') === null) {
                $modelo->setAttribute('igreja_id', app(IgrejaAtual::class)->id());
            }
        });
    }

    public function igreja(): BelongsTo
    {
        return $this->belongsTo(Igreja::class);
    }
}
