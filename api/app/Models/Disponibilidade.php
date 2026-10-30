<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sem `igreja_id` próprio: o isolamento vem do turno (consulte sempre por turnos da igreja).
 */
#[Fillable(['turno_id', 'pessoa_id'])]
class Disponibilidade extends Model
{
    protected $table = 'disponibilidades';

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
