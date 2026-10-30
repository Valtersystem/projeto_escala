<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['ministerio_id', 'nome', 'descricao', 'vagas'])]
class Posto extends Model
{
    use PertenceAIgreja;

    protected $table = 'postos';

    protected function casts(): array
    {
        return ['vagas' => 'integer'];
    }

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class);
    }

    public function pessoas(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'posto_pessoa');
    }
}
