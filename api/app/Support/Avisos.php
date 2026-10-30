<?php

namespace App\Support;

use App\Models\Aviso;
use App\Models\Confirmacao;
use App\Models\Turno;

/**
 * RN-59: avisos internos. Os textos ficam num lugar só para a API e as telas dizerem a mesma coisa.
 */
final class Avisos
{
    public static function para(int $pessoaId, string $texto): void
    {
        Aviso::create(['pessoa_id' => $pessoaId, 'texto' => $texto]);
    }

    /** "Escala publicada: Culto da Família · 08/11 às 09:00 — você fica em Voz." */
    public static function publicacao(Turno $turno, Confirmacao $confirmacao): string
    {
        $funcoes = $confirmacao->funcoes->pluck('nome')->implode(' · ');
        $culto = $turno->culto_nome ? $turno->culto_nome.' · ' : '';

        return 'Escala publicada: '.$culto.$turno->data->format('d/m').' às '.substr((string) $turno->inicio, 0, 5)
            .($funcoes !== '' ? " — você fica em {$funcoes}." : ' — função a combinar com o líder.');
    }

    /** RN-41: a pessoa foi confirmada em dois lugares no mesmo horário. */
    public static function choque(string $novo, string $existente): string
    {
        return "Atenção: você ficou escalado em dois lugares no mesmo horário — {$novo} e {$existente}.";
    }

    /** "Louvor · Som 08:00–11:30" */
    public static function resumoDoTurno(Turno $turno): string
    {
        $ministerio = $turno->escala?->ministerio?->nome;

        return trim(($ministerio ? $ministerio.' · ' : '').($turno->posto?->nome ?? 'turno')
            .' '.substr((string) $turno->inicio, 0, 5).'–'.substr((string) $turno->fim, 0, 5));
    }
}
