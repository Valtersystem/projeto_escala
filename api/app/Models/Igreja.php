<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['nome', 'codigo', 'fuso', 'limite_turnos_mes', 'prazo_padrao_dias'])]
class Igreja extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Igreja $igreja): void {
            $igreja->codigo ??= self::codigoPara($igreja->nome);
        });
    }

    /** Código curto e único para o link público: "Igreja Exemplo" vira "igreja-exemplo". */
    public static function codigoPara(string $nome): string
    {
        $base = Str::slug($nome) ?: 'igreja';
        $codigo = $base;

        for ($i = 2; self::where('codigo', $codigo)->exists(); $i++) {
            $codigo = "{$base}-{$i}";
        }

        return $codigo;
    }

    protected function casts(): array
    {
        return [
            'limite_turnos_mes' => 'integer',
            'prazo_padrao_dias' => 'integer',
        ];
    }
}
