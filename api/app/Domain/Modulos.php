<?php

namespace App\Domain;

/**
 * Módulos que o líder liga ou desliga no próprio ministério (RN-135).
 * Um módulo novo entra aqui e passa a aparecer sozinho na tela de módulos.
 */
final class Modulos
{
    public const BANDAS = 'bandas';

    /** @return array<string, array{nome: string, descricao: string}> */
    public static function catalogo(): array
    {
        return [
            self::BANDAS => [
                'nome' => 'Bandas',
                'descricao' => 'Grupos fixos de pessoas que se revezam nos cultos, com rodízio automático ao gerar a escala.',
            ],
        ];
    }

    /** @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::catalogo());
    }
}
