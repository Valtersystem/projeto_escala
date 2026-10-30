<?php

namespace Tests\Feature;

use App\Models\PedidoEntrada;
use App\Models\Pessoa;
use App\Models\Troca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/** RN-124: relatório geral do Adm. */
class RelatorioGeralTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function geral(string $query = '')
    {
        return $this->como($this->c['admU'])->getJson("/api/relatorio-geral{$query}");
    }

    public function test_t193_so_o_adm_ve_o_relatorio_geral(): void
    {
        $this->como($this->c['marcosU'])->getJson('/api/relatorio-geral')->assertForbidden();
        $this->como($this->c['helenaU'])->getJson('/api/relatorio-geral')->assertForbidden();
        $this->geral()->assertOk();
    }

    public function test_t194_soma_a_igreja_e_separa_por_ministerio_sem_contar_rascunho(): void
    {
        $som = $this->posto($this->c['louvor'], 'Som');
        $camera = $this->posto($this->c['midia'], 'Câmera');
        $helena = $this->c['helena']->id;
        $marcos = $this->c['marcos']->id;

        $louvor = $this->escala($this->c['louvor'], 'publicada');
        $t1 = $this->turno($louvor, $som, '2026-11-01', vagas: 2);
        $t2 = $this->turno($louvor, $som, '2026-11-08', vagas: 2);
        $this->confirmar($t1, $helena);
        $this->confirmar($t1, $marcos);
        $this->marcarDisponivel($t1, $helena);

        $midia = $this->escala($this->c['midia'], 'aberta');
        $t3 = $this->turno($midia, $camera, '2026-11-01', vagas: 1);
        $this->marcarDisponivel($t3, $helena);

        // Rascunho de outro mês: fora da conta.
        $rascunho = $this->escala($this->c['midia'], 'rascunho', '2026-12');
        $this->turno($rascunho, $camera, '2026-12-06', vagas: 5);

        $r = $this->geral()->assertOk();

        $this->assertSame(3, $r->json('totais.turnos'));
        $this->assertSame(5, $r->json('totais.vagas'));
        $this->assertSame(40, $r->json('totais.vagas_preenchidas_pct')); // 2 de 5
        $this->assertSame(1, $r->json('totais.turnos_sem_ninguem'));     // t2

        $porNome = collect($r->json('ministerios'))->keyBy('nome');
        $this->assertSame(2, $porNome['Louvor']['turnos']);
        $this->assertSame(50, $porNome['Louvor']['vagas_preenchidas_pct']);
        $this->assertSame(1, $porNome['Louvor']['turnos_sem_ninguem']);
        $this->assertSame('Marcos', $porNome['Louvor']['lider']['nome']);
        $this->assertSame(1, $porNome['Mídia']['turnos']);
        $this->assertSame(0, $porNome['Mídia']['vagas_preenchidas_pct']);
        $this->assertNull($r->json('acima_do_limite'));
    }

    public function test_t194b_confirmados_acima_das_vagas_nao_contam_mais_de_uma_vez(): void
    {
        $som = $this->posto($this->c['louvor']);
        $turno = $this->turno($this->escala($this->c['louvor'], 'publicada'), $som, vagas: 2);
        $extra = $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => 'Extra']));

        foreach ([$this->c['helena']->id, $this->c['marcos']->id, $extra->id] as $pessoa) {
            $this->confirmar($turno, $pessoa);
        }

        $this->assertSame(100, $this->geral()->json('totais.vagas_preenchidas_pct'));
    }

    public function test_t195_o_filtro_por_mes_limita_os_numeros(): void
    {
        $som = $this->posto($this->c['louvor']);
        $nov = $this->escala($this->c['louvor'], 'publicada', '2026-11');
        $dez = $this->escala($this->c['louvor'], 'publicada', '2026-12');
        $this->turno($nov, $som, '2026-11-01');
        $this->turno($dez, $som, '2026-12-06');
        $this->turno($dez, $som, '2026-12-13');

        $this->assertSame(1, $this->geral('?mes=2026-11')->json('totais.turnos'));
        $this->assertSame(2, $this->geral('?mes=2026-12')->json('totais.turnos'));
        $this->assertSame(3, $this->geral()->json('totais.turnos'));
        $this->geral('?mes=2026-13')->assertStatus(422);
    }

    public function test_t196_quem_passa_do_limite_no_mes_conta_os_turnos_de_todos_os_ministerios(): void
    {
        $som = $this->posto($this->c['louvor'], 'Som');
        $camera = $this->posto($this->c['midia'], 'Câmera');
        $louvor = $this->escala($this->c['louvor'], 'publicada');
        $midia = $this->escala($this->c['midia'], 'publicada');
        $helena = $this->c['helena']->id;

        // Limite padrão: 3. Helena serve 2 no Louvor e 2 na Mídia = 4. Marcos serve 3 (no limite).
        foreach (['2026-11-01', '2026-11-08'] as $data) {
            $this->confirmar($this->turno($louvor, $som, $data), $helena);
        }
        foreach (['2026-11-15', '2026-11-22'] as $data) {
            $this->confirmar($this->turno($midia, $camera, $data), $helena);
        }
        foreach (['2026-11-02', '2026-11-09', '2026-11-16'] as $data) {
            $this->confirmar($this->turno($louvor, $som, $data), $this->c['marcos']->id);
        }

        $acima = $this->geral('?mes=2026-11')->json('acima_do_limite');

        $this->assertCount(1, $acima);
        $this->assertSame('Helena', $acima[0]['nome']);
        $this->assertSame(4, $acima[0]['turnos']);
    }

    public function test_t197_traz_o_que_pede_atencao_do_adm(): void
    {
        $this->naIgreja($this->c['igreja'], function () {
            \App\Models\Ministerio::create(['nome' => 'Recepção']); // sem líder
            PedidoEntrada::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Carla', 'email' => 'c@x.com', 'senha_hash' => 'x']);
            PedidoEntrada::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Rita', 'email' => 'r@x.com', 'senha_hash' => 'x', 'estado' => 'recusado']);
            Pessoa::create(['nome' => 'Inativa', 'ativo' => false]);
        });

        $som = $this->posto($this->c['louvor']);
        $turno = $this->turno($this->escala($this->c['louvor'], 'publicada'), $som);
        $this->naIgreja($this->c['igreja'], fn () => Troca::create(['turno_id' => $turno->id, 'pessoa_id' => $this->c['helena']->id, 'motivo' => 'Viagem']));
        $this->naIgreja($this->c['igreja'], fn () => Troca::create(['turno_id' => $turno->id, 'pessoa_id' => $this->c['marcos']->id, 'motivo' => 'Viagem', 'estado' => 'resolvido']));

        $r = $this->geral()->assertOk();

        $this->assertSame(1, $r->json('totais.ministerios_sem_lider'));
        $this->assertSame(1, $r->json('totais.pedidos_de_entrada'));
        $this->assertSame(1, $r->json('totais.trocas_abertas'));
        $this->assertSame(4, $r->json('totais.pessoas_ativas')); // Valter, Marcos, Ana e Helena
    }

    public function test_t198_nao_mistura_dados_de_outra_igreja(): void
    {
        $outra = $this->igreja('Igreja B');
        $this->naIgreja($outra, function () {
            $m = \App\Models\Ministerio::create(['nome' => 'Intercessão']);
            $posto = $m->postos()->create(['nome' => 'Sala', 'vagas' => 3]);
            $escala = $m->escalas()->create(['mes' => '2026-11', 'status' => 'publicada', 'prazo' => '2999-12-31']);
            $escala->turnos()->create(['posto_id' => $posto->id, 'data' => '2026-11-01', 'inicio' => '08:00', 'fim' => '09:00', 'vagas' => 3]);
        });

        $r = $this->geral()->assertOk();

        $this->assertSame(0, $r->json('totais.turnos'));
        $this->assertNotContains('Intercessão', collect($r->json('ministerios'))->pluck('nome')->all());
    }
}
