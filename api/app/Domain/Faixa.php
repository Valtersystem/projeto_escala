<?php

namespace App\Domain;

/**
 * Dia e intervalo de horário de um turno, usado na detecção de choque.
 */
final readonly class Faixa
{
    public function __construct(
        public string $data,
        public ?string $inicio,
        public ?string $fim,
    ) {
    }

    /**
     * RN-46: se o fim não existe ou é anterior ou igual ao início, a duração é de 60 minutos.
     *
     * @return array{0: int, 1: int}|null null quando o início não pode ser lido
     */
    public function intervalo(): ?array
    {
        $inicio = Horario::minutos($this->inicio);

        if ($inicio === null) {
            return null;
        }

        $fim = Horario::minutos($this->fim);

        if ($fim === null || $fim <= $inicio) {
            $fim = $inicio + 60;
        }

        return [$inicio, $fim];
    }
}
