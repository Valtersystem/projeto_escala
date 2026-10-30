<?php

namespace App\Support;

use App\Exceptions\ConflitoDeVersao;
use Illuminate\Database\Eloquent\Model;

/**
 * D-14: controle otimista por versão. Quem edita manda a versão que viu; se o
 * registro já mudou, a atualização não acontece.
 */
final class Concorrencia
{
    /**
     * Atualiza só se a versão ainda for a informada, e já incrementa a versão.
     *
     * @param  array<string, mixed>  $dados  valores já prontos para o banco
     *
     * @throws ConflitoDeVersao
     */
    public static function atualizar(Model $modelo, int $versao, array $dados): void
    {
        $afetados = $modelo->newQuery()
            ->whereKey($modelo->getKey())
            ->where('versao', $versao)
            ->update($dados + ['versao' => $versao + 1, 'updated_at' => now()]);

        if ($afetados === 0) {
            throw new ConflitoDeVersao();
        }

        $modelo->refresh();
    }
}
