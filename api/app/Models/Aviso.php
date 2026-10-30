<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pessoa_id', 'texto', 'lido_em'])]
class Aviso extends Model
{
    use PertenceAIgreja;

    protected $table = 'avisos';

    protected function casts(): array
    {
        return ['lido_em' => 'datetime'];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
