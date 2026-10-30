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

    /** "Som · 08/11" */
    public static function turnoCurto(Turno $turno): string
    {
        return ($turno->posto?->nome ?? 'turno').' · '.$turno->data->format('d/m');
    }

    public static function primeiroNome(string $nome): string
    {
        return explode(' ', trim($nome))[0];
    }

    /** D-15: um voluntário pediu para entrar no ministério. */
    public static function pedidoDeEntrada(string $nome, string $ministerio): string
    {
        return self::primeiroNome($nome).' pediu para entrar no '.$ministerio.'. Veja em Equipe.';
    }

    // ---- pedido de troca (RN-51 a RN-57) ----

    public static function pedidoDeTroca(string $nome, Turno $turno): string
    {
        return self::primeiroNome($nome).' pediu substituição no '.self::turnoCurto($turno).'.';
    }

    public static function pedidoCancelado(string $nome): string
    {
        return self::primeiroNome($nome).' cancelou o pedido de substituição.';
    }

    public static function trocaMantida(Turno $turno): string
    {
        return 'O líder pediu que você continue no '.self::turnoCurto($turno).'. Fale com ele se não der mesmo.';
    }

    public static function trocaSubstituida(string $substituto, Turno $turno): string
    {
        return self::primeiroNome($substituto).' assumiu o seu turno no '.self::turnoCurto($turno).'. Você está liberado.';
    }

    public static function entrouNoLugar(string $quemSaiu, Turno $turno, string $funcoes): string
    {
        return 'Você entrou no lugar de '.self::primeiroNome($quemSaiu).' no '.self::turnoCurto($turno)
            .' ('.substr((string) $turno->inicio, 0, 5).' às '.substr((string) $turno->fim, 0, 5).')'
            .($funcoes !== '' ? " — função: {$funcoes}" : '').'.';
    }

    public static function vagaAberta(Turno $turno): string
    {
        return 'Sua substituição no '.self::turnoCurto($turno).' foi aceita. A vaga ficou em aberto.';
    }

    /** "Louvor · Som 08:00–11:30" */
    public static function resumoDoTurno(Turno $turno): string
    {
        $ministerio = $turno->escala?->ministerio?->nome;

        return trim(($ministerio ? $ministerio.' · ' : '').($turno->posto?->nome ?? 'turno')
            .' '.substr((string) $turno->inicio, 0, 5).'–'.substr((string) $turno->fim, 0, 5));
    }
}
