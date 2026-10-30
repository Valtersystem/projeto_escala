<?php

namespace App\Domain;

/**
 * RN-46: horários aceitam texto como "08h00", "8:30", "8.30" e "8".
 */
final class Horario
{
    /** Minutos desde a meia-noite, ou null se o texto não puder ser lido. */
    public static function minutos(?string $texto): ?int
    {
        $limpo = str_replace(',', '.', (string) $texto);

        if (! preg_match('/(\d{1,2})\s*[h:.]?\s*(\d{2})?/', $limpo, $m)) {
            return null;
        }

        $horas = (int) $m[1];
        $minutos = (isset($m[2]) && $m[2] !== '') ? (int) $m[2] : 0;

        if ($horas > 23 || $minutos > 59) {
            return null;
        }

        return $horas * 60 + $minutos;
    }
}
