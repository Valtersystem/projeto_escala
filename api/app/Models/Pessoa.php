<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'nome', 'email', 'telefone', 'adm', 'ativo'])]
class Pessoa extends Model
{
    use PertenceAIgreja;

    protected $table = 'pessoas';

    protected function casts(): array
    {
        return [
            'adm' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ministérios em que a pessoa serve (equipe). */
    public function ministerios(): BelongsToMany
    {
        return $this->belongsToMany(Ministerio::class, 'ministerio_pessoa');
    }

    /** Ministérios que a pessoa lidera. */
    public function ministeriosQueLidera(): HasMany
    {
        return $this->hasMany(Ministerio::class, 'lider_id');
    }

    public function postos(): BelongsToMany
    {
        return $this->belongsToMany(Posto::class, 'posto_pessoa');
    }

    /** Bandas em que a pessoa é membro fixo. */
    public function bandas(): BelongsToMany
    {
        return $this->belongsToMany(Banda::class, 'banda_pessoa');
    }

    /** Funções que a pessoa já faz. */
    public function funcoes(): BelongsToMany
    {
        return $this->belongsToMany(Funcao::class, 'funcao_pessoa');
    }
}
