<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['escala_id', 'posto_id', 'banda_id', 'culto_id', 'culto_nome', 'data', 'inicio', 'fim', 'vagas'])]
class Turno extends Model
{
    use PertenceAIgreja;

    protected $table = 'turnos';

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'vagas' => 'integer',
            'versao' => 'integer',
        ];
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class);
    }

    public function posto(): BelongsTo
    {
        return $this->belongsTo(Posto::class);
    }

    public function banda(): BelongsTo
    {
        return $this->belongsTo(Banda::class);
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }

    public function disponibilidades(): HasMany
    {
        return $this->hasMany(Disponibilidade::class);
    }

    public function confirmacoes(): HasMany
    {
        return $this->hasMany(Confirmacao::class);
    }

    /** Quem marcou disponibilidade neste turno. */
    public function pessoasDisponiveis(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'disponibilidades');
    }
}
