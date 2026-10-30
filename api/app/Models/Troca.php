<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turno_id', 'pessoa_id', 'motivo', 'observacao', 'estado', 'resolucao', 'substituto_id'])]
class Troca extends Model
{
    use PertenceAIgreja;

    protected $table = 'trocas';

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function substituto(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'substituto_id');
    }
}
