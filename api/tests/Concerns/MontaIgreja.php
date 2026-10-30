<?php

namespace Tests\Concerns;

use App\Models\Igreja;
use App\Models\Pessoa;
use App\Models\User;
use App\Support\IgrejaAtual;

/**
 * Monta igrejas, logins e pessoas para os testes, sem deixar a igreja atual definida.
 */
trait MontaIgreja
{
    protected const SENHA = 'senha-forte-1';

    protected function igreja(string $nome = 'Igreja A'): Igreja
    {
        return Igreja::create(['nome' => $nome]);
    }

    /**
     * Roda um trecho com a igreja definida e volta ao estado "sem igreja".
     *
     * @template T
     *
     * @param  callable(): T  $fazer
     * @return T
     */
    protected function naIgreja(Igreja $igreja, callable $fazer): mixed
    {
        $atual = app(IgrejaAtual::class);
        $anterior = $atual->id();
        $atual->definir($igreja->id);

        try {
            return $fazer();
        } finally {
            $atual->definir($anterior);
        }
    }

    /**
     * Cria o login e a pessoa vinculada.
     *
     * @param  array<string, mixed>  $pessoa
     * @return array{0: User, 1: Pessoa}
     */
    protected function pessoaComLogin(Igreja $igreja, string $email, array $pessoa = [], ?string $senha = self::SENHA): array
    {
        $user = User::create(['name' => $pessoa['nome'] ?? 'Pessoa', 'email' => $email, 'password' => $senha]);

        $criada = $this->naIgreja($igreja, fn () => Pessoa::create(array_merge([
            'user_id' => $user->id,
            'nome' => 'Pessoa',
            'email' => $email,
        ], $pessoa)));

        return [$user, $criada];
    }
}
