<?php

namespace App\Domain;

/**
 * RN-29 a RN-31: rodízio de bandas.
 */
final class Rodizio
{
    /**
     * Cada horário (data + início) recebe a próxima banda, em sequência contínua.
     * Turnos do mesmo horário recebem a mesma banda (D-05). A sequência segue a
     * banda do último turno anterior, ou começa pela primeira se não houver.
     *
     * @param  list<int|string>  $bandaIds  na ordem do rodízio
     * @param  list<array{data: string, inicio: string}>  $horarios
     * @param  int|string|null  $ultimaBandaId  banda do último turno já existente antes destes
     * @return array<string, int|string> banda de cada horário, com chave "data|inicio"
     */
    public static function atribuir(array $bandaIds, array $horarios, int|string|null $ultimaBandaId = null): array
    {
        $bandaIds = array_values($bandaIds);
        $total = count($bandaIds);

        if ($total === 0) {
            return [];
        }

        $unicos = [];

        foreach ($horarios as $h) {
            $unicos[$h['data'].'|'.$h['inicio']] = [$h['data'], Horario::minutos($h['inicio']) ?? 0];
        }

        uasort($unicos, fn (array $a, array $b) => $a <=> $b);

        $posicao = $ultimaBandaId === null ? false : array_search($ultimaBandaId, $bandaIds);
        $primeira = $posicao === false ? 0 : ($posicao + 1) % $total;

        $resultado = [];
        $passo = 0;

        foreach (array_keys($unicos) as $chave) {
            $resultado[$chave] = $bandaIds[($primeira + $passo) % $total];
            $passo++;
        }

        return $resultado;
    }
}
