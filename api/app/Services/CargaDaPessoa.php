<?php

namespace App\Services;

use App\Domain\Choque;
use App\Domain\Faixa;
use App\Models\Turno;
use Illuminate\Support\Collection;

/**
 * Agenda de uma pessoa para calcular choque de horário (RN-45 a RN-50) e carga
 * do mês (RN-44). Carrega uma vez e responde várias perguntas em memória.
 */
final class CargaDaPessoa
{
    /**
     * @param  Collection<int, Turno>  $firmes  turnos confirmados em escala que não está em rascunho (RN-47)
     * @param  Collection<int, Turno>  $marcados  turnos em que marcou disponibilidade, em escalas abertas (RN-49)
     */
    private function __construct(
        public readonly Collection $firmes,
        public readonly Collection $marcados,
    ) {
    }

    public static function de(int $pessoaId): self
    {
        $com = ['escala.ministerio:id,nome', 'posto:id,nome'];

        $firmes = Turno::whereHas('confirmacoes', fn ($c) => $c->where('pessoa_id', $pessoaId))
            ->whereHas('escala', fn ($e) => $e->where('status', '!=', 'rascunho'))
            ->with($com)->get();

        $marcados = Turno::whereHas('disponibilidades', fn ($d) => $d->where('pessoa_id', $pessoaId))
            ->whereHas('escala', fn ($e) => $e->where('status', 'aberta'))
            ->with($com)->get();

        return new self($firmes, $marcados);
    }

    /** RN-45/RN-47/RN-48: turnos já confirmados que se sobrepõem a este. */
    public function conflitosCom(Turno $turno): Collection
    {
        return $this->firmes
            ->filter(fn (Turno $t) => $t->id !== $turno->id && Choque::chocam(self::faixa($t), self::faixa($turno)))
            ->values();
    }

    /** RN-49: confirmados mais os que a pessoa já marcou, para o aviso na hora de marcar. */
    public function choquesCom(Turno $turno): Collection
    {
        $marcados = $this->marcados
            ->filter(fn (Turno $t) => $t->id !== $turno->id && Choque::chocam(self::faixa($t), self::faixa($turno)));

        return $this->conflitosCom($turno)->concat($marcados)->unique('id')->values();
    }

    /** RN-50: outro turno confirmado no mesmo dia, sem sobreposição. */
    public function mesmoDiaCom(Turno $turno): Collection
    {
        return $this->firmes
            ->filter(fn (Turno $t) => $t->id !== $turno->id && Choque::mesmoDia(self::faixa($t), self::faixa($turno)))
            ->values();
    }

    /** RN-44: quantos turnos confirmados a pessoa tem no mês (AAAA-MM). */
    public function turnosNoMes(string $mes): int
    {
        return $this->firmes->filter(fn (Turno $t) => str_starts_with($t->data->toDateString(), $mes))->count();
    }

    /**
     * Como um turno aparece numa lista de choques.
     *
     * @return array<string, mixed>
     */
    public static function descricao(Turno $t): array
    {
        return [
            'turno_id' => $t->id,
            'ministerio' => $t->escala?->ministerio?->nome,
            'posto' => $t->posto?->nome,
            'data' => $t->data->toDateString(),
            'inicio' => substr((string) $t->inicio, 0, 5),
            'fim' => substr((string) $t->fim, 0, 5),
        ];
    }

    public static function faixa(Turno $t): Faixa
    {
        return new Faixa($t->data->toDateString(), (string) $t->inicio, (string) $t->fim);
    }
}
