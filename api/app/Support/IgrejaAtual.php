<?php

namespace App\Support;

/**
 * Igreja (tenant) da requisição em andamento. É definida pelo middleware
 * DefinirIgrejaAtual a partir da pessoa autenticada e lida pelo IgrejaScope.
 * Registrada como `scoped`: cada requisição começa sem igreja.
 */
final class IgrejaAtual
{
    private ?int $id = null;

    public function definir(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function definida(): bool
    {
        return $this->id !== null;
    }
}
