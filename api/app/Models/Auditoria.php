<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pessoa_id', 'acao', 'entidade', 'entidade_id', 'antes', 'depois'])]
class Auditoria extends Model
{
    use PertenceAIgreja;

    protected $table = 'auditorias';

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'depois' => 'array',
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
