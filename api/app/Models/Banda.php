<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['ministerio_id', 'nome', 'ordem'])]
class Banda extends Model
{
    use PertenceAIgreja;

    protected $table = 'bandas';

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class);
    }

    /** Membros fixos da banda. */
    public function membros(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'banda_pessoa');
    }
}
