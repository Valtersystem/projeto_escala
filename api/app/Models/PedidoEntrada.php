<?php

namespace App\Models;

use App\Models\Concerns\PertenceAIgreja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D-15: pedido de entrada de um voluntário, esperando o líder do ministério. */
#[Fillable(['ministerio_id', 'nome', 'email', 'telefone', 'senha_hash', 'estado', 'resolvido_por', 'resolvido_em'])]
class PedidoEntrada extends Model
{
    use PertenceAIgreja;

    protected $table = 'pedidos_entrada';

    protected $hidden = ['senha_hash'];

    protected function casts(): array
    {
        return ['resolvido_em' => 'datetime'];
    }

    public function ministerio(): BelongsTo
    {
        return $this->belongsTo(Ministerio::class);
    }
}
