<?php

namespace App\Domain;

use App\Exceptions\RegraDeNegocio;

/**
 * RN-17 a RN-22: estados da escala.
 */
enum EstadoEscala: string
{
    case Rascunho = 'rascunho';
    case Aberta = 'aberta';
    case Publicada = 'publicada';

    /** @return list<self> */
    public function transicoesPermitidas(): array
    {
        return match ($this) {
            self::Rascunho => [self::Aberta],
            self::Aberta => [self::Publicada],
            self::Publicada => [self::Aberta], // RN-22: reabrir
        };
    }

    public function podePara(self $novo): bool
    {
        return in_array($novo, $this->transicoesPermitidas(), true);
    }

    /**
     * @throws RegraDeNegocio
     */
    public function validarTransicao(self $novo, int $totalDeTurnos): void
    {
        if (! $this->podePara($novo)) {
            throw new RegraDeNegocio("Não dá para mudar a escala de {$this->value} para {$novo->value}.");
        }

        // RN-19: só se libera a escala com ao menos um turno.
        if ($this === self::Rascunho && $novo === self::Aberta && $totalDeTurnos < 1) {
            throw new RegraDeNegocio('Adicione pelo menos um turno antes de liberar a escala.');
        }
    }
}
