<?php

namespace App\Domain;

/**
 * RN-45 a RN-50: choque de horário entre dois turnos.
 */
final class Choque
{
    /**
     * Mesmo dia e intervalos que se sobrepõem. Se o horário de um dos turnos
     * não pode ser lido, os dois do mesmo dia são tratados como choque (RN-46).
     */
    public static function chocam(Faixa $x, Faixa $y): bool
    {
        if ($x->data !== $y->data) {
            return false;
        }

        $a = $x->intervalo();
        $b = $y->intervalo();

        if ($a === null || $b === null) {
            return true;
        }

        return $a[0] < $b[1] && $b[0] < $a[1];
    }

    /** RN-50: mesmo dia, sem sobreposição. */
    public static function mesmoDia(Faixa $x, Faixa $y): bool
    {
        return $x->data === $y->data && ! self::chocam($x, $y);
    }
}
