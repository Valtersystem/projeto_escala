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

    /** D-02: o prazo vale até o fim do dia dele, no fuso da igreja. */
    public function prazoEncerrado(): bool
    {
        if (! $this->prazo) {
            return false;
        }

        $fuso = Igreja::find($this->igreja_id)?->fuso ?? config('app.timezone');

        return $this->prazo->toDateString() < now($fuso)->toDateString();
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
