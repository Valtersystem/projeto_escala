<?php

namespace App\Domain;

use Carbon\CarbonImmutable;

/**
 * RN-03 a RN-05: datas em que um culto acontece num mês.
 */
final class GradeDeCultos
{
    /**
     * @param  string  $tipo  "semanal" ou "mensal"
     * @param  int  $diaSemana  0 = domingo ... 6 = sábado
     * @param  int  $ordem  só para o tipo mensal: 1 a 5, ou -1 para a última ocorrência
     * @param  string  $mes  AAAA-MM
     * @return list<string> datas no formato AAAA-MM-DD
     */
    public static function ocorrencias(string $tipo, int $diaSemana, int $ordem, string $mes): array
    {
        $inicio = CarbonImmutable::parse($mes.'-01')->startOfDay();
        $datas = [];

        for ($dia = $inicio; $dia->month === $inicio->month; $dia = $dia->addDay()) {
            if ($dia->dayOfWeek === $diaSemana) {
                $datas[] = $dia->toDateString();
            }
        }

        if ($tipo !== 'mensal') {
            return $datas;
        }

        if ($ordem === -1) {
            return $datas === [] ? [] : [end($datas)];
        }

        return isset($datas[$ordem - 1]) ? [$datas[$ordem - 1]] : [];
    }
}
