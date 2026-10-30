<?php

namespace App\Domain;

use Carbon\CarbonImmutable;

/**
 * RN-17 e D-03: prazo sugerido para a escala.
 */
final class Prazo
{
    /** Início do mês menos N dias. Ex.: 2026-11 com 5 dias dá 2026-10-27. */
    public static function sugerido(string $mes, int $diasAntes): string
    {
        return CarbonImmutable::parse($mes.'-01')->subDays($diasAntes)->toDateString();
    }
}
