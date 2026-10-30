<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/**
 * Relatório do ministério (RN-61).
 */
class RelatorioTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function relatorio(string $query = '')
    {
        return $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/relatorio{$query}");
    }

    public function test_t162_calcula_vagas_preenchidas_turnos_sem_ninguem_e_turnos_por_pessoa(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $escala = $this->escala($this->c['louvor'], 'publicada');
        $helena = $this->c['helena']->id;
        $marcos = $this->c['marcos']->id;

        $cheio = $this->turno($escala, $posto, '2026-11-01', vagas: 2);
        $metade = $this->turno($escala, $posto, '2026-11-08', vagas: 2);
        $acima = $this->turno($escala, $posto, '2026-11-15', vagas: 2);

        $this->confirmar($cheio, $helena);
        $this->confirmar($cheio, $marcos);
        $this->confirmar($metade, $helena);
        // Três confirmados em duas vagas: conta só 2 no preenchimento.
        $extra = $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => 'Extra']));
        foreach ([$helena, $marcos, $extra->id] as $pessoa) {
            $this->confirmar($acima, $pessoa);
        }

        $this->marcarDisponivel($cheio, $helena);

        $r = $this->relatorio()->assertOk();

        $this->assertSame(3, $r->json('total_de_turnos'));
        $this->assertSame(6, $r->json('total_de_vagas'));
        $this->assertSame(83, $r->json('vagas_preenchidas_pct')); // (2 + 1 + 2) / 6
        $this->assertSame(2, $r->json('turnos_sem_ninguem'));       // só o "cheio" tem alguém que marcou
        $this->assertSame(2, $r->json('voluntarios_ativos'));       // Marcos e Helena

        $porPessoa = collect($r->json('turnos_por_pessoa'))->keyBy('nome');
        $this->assertSame(3, $porPessoa['Helena']['turnos']);
        $this->assertSame(2, $porPessoa['Marcos']['turnos']);
        $this->assertSame(['Helena', 'Marcos'], collect($r->json('turnos_por_pessoa'))->pluck('nome')->all());

        $this->assertSame([$metade->id, $acima->id], collect($r->json('onde_aperta'))->pluck('id')->all());
    }

    public function test_t163_escala_em_rascunho_nao_entra_na_conta(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $this->turno($this->escala($this->c['louvor'], 'rascunho'), $posto, '2026-11-01');

        $this->relatorio()->assertOk()
            ->assertJsonPath('total_de_turnos', 0)->assertJsonPath('vagas_preenchidas_pct', 0)->assertJsonPath('turnos_sem_ninguem', 0);
    }

    public function test_t164_com_mes_compara_com_o_limite_e_sem_mes_nao(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $novembro = $this->escala($this->c['louvor'], 'publicada', '2026-11');
        $dezembro = $this->escala($this->c['louvor'], 'publicada', '2026-12');

        foreach (['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22'] as $data) {
            $this->confirmar($this->turno($novembro, $posto, $data), $this->c['helena']->id);
        }
        $this->confirmar($this->turno($dezembro, $posto, '2026-12-06'), $this->c['marcos']->id);

        $semMes = $this->relatorio()->assertOk();
        $this->assertNull($semMes->json('mes'));
        $this->assertNull($semMes->json('turnos_por_pessoa.0.acima_do_limite'));

        $novembroR = $this->relatorio('?mes=2026-11')->assertOk();
        $this->assertSame(4, $novembroR->json('total_de_turnos'));
        $porPessoa = collect($novembroR->json('turnos_por_pessoa'))->keyBy('nome');
        $this->assertTrue($porPessoa['Helena']['acima_do_limite']);   // 4 > limite 3
        $this->assertFalse($porPessoa['Marcos']['acima_do_limite']);
        $this->assertSame(0, $porPessoa['Marcos']['turnos']);
    }

    public function test_t165_so_o_lider_do_ministerio_ve_o_relatorio(): void
    {
        $url = "/api/ministerios/{$this->c['louvor']->id}/relatorio";

        $this->como($this->c['helenaU'])->getJson($url)->assertForbidden();
        $this->como($this->c['anaU'])->getJson($url)->assertForbidden();
        $this->como($this->c['admU'])->getJson($url)->assertForbidden();

        $this->relatorio('?mes=2026-13')->assertStatus(422);
    }

    public function test_ministerio_sem_turnos_devolve_zeros(): void
    {
        $r = $this->relatorio()->assertOk();

        $this->assertSame(0, $r->json('total_de_turnos'));
        $this->assertSame(0, $r->json('vagas_preenchidas_pct'));
        $this->assertSame([], $r->json('onde_aperta'));
        $this->assertCount(2, $r->json('turnos_por_pessoa'));
    }
}
