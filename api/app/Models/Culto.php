<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'tipo', 'dia_semana', 'ordem', 'inicio', 'fim'])]
class Culto extends Model
{
    use PertenceAIgreja;

    protected $table = 'cultos';

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'ordem' => 'integer',
        ];
    }
}
