<?php

namespace Tests\Concerns;

use App\Models\Ministerio;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Igreja A com Adm, líder do Louvor, líder da Mídia e uma voluntária que serve nos dois.
 *
 * @property array<string, mixed> $c
 */
trait CenarioDaIgreja
{
    use MontaIgreja;

    /** @var array<string, mixed> */
    protected array $c = [];

    protected function montarCenario(): void
    {
        $igreja = $this->igreja('Igreja A');

        [$admU, $adm] = $this->pessoaComLogin($igreja, 'adm@teste.com', ['nome' => 'Valter', 'adm' => true]);
        [$marcosU, $marcos] = $this->pessoaComLogin($igreja, 'marcos@teste.com', ['nome' => 'Marcos']);
        [$anaU, $ana] = $this->pessoaComLogin($igreja, 'ana@teste.com', ['nome' => 'Ana']);
        [$helenaU, $helena] = $this->pessoaComLogin($igreja, 'helena@teste.com', ['nome' => 'Helena']);

        [$louvor, $midia] = $this->naIgreja($igreja, function () use ($marcos, $ana, $helena) {
            $louvor = Ministerio::create(['nome' => 'Louvor', 'lider_id' => $marcos->id, 'modulos' => ['bandas']]);
            $midia = Ministerio::create(['nome' => 'Mídia', 'lider_id' => $ana->id]);
            $louvor->equipe()->attach([$marcos->id, $helena->id]);
            $midia->equipe()->attach([$ana->id, $helena->id]);

            return [$louvor, $midia];
        });

        $this->c = compact(
            'igreja', 'admU', 'adm', 'marcosU', 'marcos', 'anaU', 'ana', 'helenaU', 'helena', 'louvor', 'midia',
        );
    }

    /** Passa a fazer as requisições seguintes como este usuário. */
    protected function como(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }
}
