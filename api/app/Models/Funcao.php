<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ministerio_id', 'nome'])]
class Funcao extends Model
{
    use PertenceAIgreja;

    protected $table = 'funcoes';

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class);
    }
}
