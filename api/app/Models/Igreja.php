<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'fuso', 'limite_turnos_mes', 'prazo_padrao_dias'])]
class Igreja extends Model
{
    protected function casts(): array
    {
        return [
            'limite_turnos_mes' => 'integer',
            'prazo_padrao_dias' => 'integer',
        ];
    }
}
