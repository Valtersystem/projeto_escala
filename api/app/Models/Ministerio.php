<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'lider_id', 'orientacoes', 'modulos'])]
class Ministerio extends Model
{
    use PertenceAIgreja;

    protected $table = 'ministerios';

    protected $casts = ['modulos' => 'array'];

    /** RN-135: o módulo está ligado neste ministério? */
    public function usa(string $modulo): bool
    {
        return in_array($modulo, $this->modulos ?? [], true);
    }

    public function lider(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'lider_id');
    }

    /** Pessoas que servem no ministério. */
    public function equipe(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'ministerio_pessoa');
    }

    public function postos(): HasMany
    {
        return $this->hasMany(Posto::class);
    }

    public function bandas(): HasMany
    {
        return $this->hasMany(Banda::class)->orderBy('ordem');
    }

    public function funcoes(): HasMany
    {
        return $this->hasMany(Funcao::class);
    }

    public function escalas(): HasMany
    {
        return $this->hasMany(Escala::class);
    }
}
