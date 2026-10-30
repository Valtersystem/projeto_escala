<?php

namespace App\Models;

use App\Domain\EstadoEscala;
use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ministerio_id', 'mes', 'status', 'prazo'])]
class Escala extends Model
{
    use PertenceAIgreja;

    protected $table = 'escalas';

    protected function casts(): array
    {
        return [
            'status' => EstadoEscala::class,
            'prazo' => 'date:Y-m-d',
            'versao' => 'integer',
        ];
    }

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class);
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class)->orderBy('data')->orderBy('inicio');
    }
}
