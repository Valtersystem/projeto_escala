<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/**
 * Disponibilidade do voluntário (RN-34 a RN-38, RN-45 a RN-50, D-02).
 */
class DisponibilidadeTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function marcar(int $turno, bool $marcado = true)
    {
        return $this->como($this->c['helenaU'])->putJson("/api/turnos/{$turno}/disponibilidade", ['marcado' => $marcado]);
    }

    public function test_t125_marcar_e_desmarcar_sao_idempotentes(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));

        $this->marcar($turno->id)->assertOk()->assertJsonPath('marcado', true);
        $this->marcar($turno->id)->assertOk();
        $this->assertSame(1, DB::table('disponibilidades')->count());

        $this->marcar($turno->id, false)->assertOk()->assertJsonPath('marcado', false);
        $this->marcar($turno->id, false)->assertOk();
        $this->assertSame(0, DB::table('disponibilidades')->count());

        $this->como($this->c['helenaU'])->putJson("/api/turnos/{$turno->id}/disponibilidade", [])->assertStatus(422);
    }

    public function test_t126_so_escala_aberta_recebe_disponibilidade(): void
    {
        $posto = $this->posto($this->c['louvor']);

        foreach (['rascunho', 'publicada'] as $status) {
            $escala = $this->escala($this->c['louvor'], $status, $status === 'rascunho' ? '2026-11' : '2026-12');
            $turno = $this->turno($escala, $posto);

            $this->marcar($turno->id)->assertStatus(422)
                ->assertJsonPath('message', 'Esta escala não está recebendo disponibilidade.');
            $this->getJson("/api/escalas/{$escala->id}/disponibilidade")->assertStatus(422);
        }

        $this->assertSame(0, DB::table('disponibilidades')->count());
    }

    public function test_t127_quem_nao_esta_na_equipe_nao_marca(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));

        $this->como($this->c['anaU'])->putJson("/api/turnos/{$turno->id}/disponibilidade", ['marcado' => true])->assertForbidden();
        $this->getJson("/api/escalas/{$turno->escala_id}/disponibilidade")->assertForbidden();
    }

    public function test_t128_depois_do_prazo_nao_marca_nem_desmarca_e_o_lider_pode_estender(): void
    {
        $escala = $this->escala($this->c['louvor'], 'aberta', '2026-11', '2026-11-09');
        $turno = $this->turno($escala, $this->posto($this->c['louvor']));
        $this->marcarDisponivel($turno, $this->c['helena']->id);

        // No último dia do prazo ainda dá.
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(23, 0));
        $this->marcar($turno->id, false)->assertOk();
        $this->marcar($turno->id, true)->assertOk();

        // No dia seguinte, não.
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(8, 0));
        $mensagem = 'O prazo para responder esta escala terminou. Fale com o líder.';
        $this->marcar($turno->id, false)->assertStatus(422)->assertJsonPath('message', $mensagem);
        $this->marcar($turno->id, true)->assertStatus(422)->assertJsonPath('message', $mensagem);
        $this->getJson("/api/escalas/{$escala->id}/disponibilidade")->assertOk()->assertJsonPath('escala.prazo_encerrado', true);

        // T-58: o líder estende o prazo e a marcação volta.
        $this->como($this->c['marcosU'])->patchJson("/api/escalas/{$escala->id}", ['prazo' => '2026-11-20', 'versao' => $escala->versao])->assertOk();
        $this->marcar($turno->id, false)->assertOk();
    }

    public function test_t129_enviar_exige_pelo_menos_um_turno_marcado(): void
    {
        $escala = $this->escala($this->c['louvor']);
        $turno = $this->turno($escala, $this->posto($this->c['louvor']));

        $this->como($this->c['helenaU'])->postJson("/api/escalas/{$escala->id}/disponibilidade/enviar")
            ->assertStatus(422)->assertJsonPath('message', 'Marque pelo menos um turno.');

        $this->marcar($turno->id);
        $this->postJson("/api/escalas/{$escala->id}/disponibilidade/enviar")
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('com_choque', 0);
    }

    public function test_t130_marcar_dois_turnos_que_se_sobrepoem_em_ministerios_diferentes_avisa_o_choque(): void
    {
        $noLouvor = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']), '2026-11-08', '08:00', '11:30');
        $naMidia = $this->turno($this->escala($this->c['midia']), $this->posto($this->c['midia'], 'Câmera'), '2026-11-08', '10:00', '12:00');

        $primeiro = $this->marcar($noLouvor->id)->assertOk();
        $this->assertSame([], $primeiro->json('choques'));

        $segundo = $this->marcar($naMidia->id)->assertOk();
        $this->assertSame(['Louvor'], collect($segundo->json('choques'))->pluck('ministerio')->all());

        // O aviso também aparece na tela, para o turno que ainda não foi marcado.
        $this->marcar($naMidia->id, false);
        $tela = $this->getJson("/api/escalas/{$naMidia->escala_id}/disponibilidade")->assertOk();
        $this->assertSame(['Louvor'], collect($tela->json('turnos.0.choques'))->pluck('ministerio')->all());

        // O envio conta os turnos marcados com choque.
        $this->marcar($naMidia->id);
        $this->postJson("/api/escalas/{$noLouvor->escala_id}/disponibilidade/enviar")->assertOk()->assertJsonPath('com_choque', 1);
    }

    public function test_t131_confirmado_em_escala_publicada_conta_mas_em_rascunho_nao(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $alvo = $this->turno($this->escala($this->c['louvor']), $posto, '2026-11-08', '08:00', '11:30');
        $postoMidia = $this->posto($this->c['midia'], 'Câmera');

        $publicada = $this->turno($this->escala($this->c['midia'], 'publicada'), $postoMidia, '2026-11-08', '09:00', '10:00');
        $this->confirmar($publicada, $this->c['helena']->id);

        $this->marcar($alvo->id)->assertOk()->assertJsonPath('choques.0.turno_id', $publicada->id);

        // Mesma situação, mas a escala da Mídia ainda é rascunho: não conta (RN-47).
        DB::table('escalas')->where('id', $publicada->escala_id)->update(['status' => 'rascunho']);
        $this->marcar($alvo->id)->assertOk()->assertJsonPath('choques', []);
    }

    public function test_t132_mesmo_dia_sem_sobreposicao_e_so_um_aviso_leve(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $manha = $this->turno($this->escala($this->c['louvor']), $posto, '2026-11-08', '08:00', '11:30');
        $noite = $this->turno($this->escala($this->c['louvor'], 'publicada', '2026-12'), $posto, '2026-11-08', '19:00', '21:00');
        $this->confirmar($noite, $this->c['helena']->id);

        $turno = $this->como($this->c['helenaU'])->getJson("/api/escalas/{$manha->escala_id}/disponibilidade")->assertOk()->json('turnos.0');

        $this->assertSame([], $turno['choques']);
        $this->assertSame([$noite->id], collect($turno['mesmo_dia'])->pluck('turno_id')->all());
    }

    public function test_t133_a_tela_nao_mostra_vagas_e_marca_o_que_e_do_meu_perfil(): void
    {
        $escala = $this->escala($this->c['louvor']);
        $som = $this->posto($this->c['louvor'], 'Som');
        $palco = $this->posto($this->c['louvor'], 'Palco');
        $this->turno($escala, $som, '2026-11-01');
        $this->turno($escala, $palco, '2026-11-08');
        $this->marcarDisponivel($this->turno($escala, $som, '2026-11-15'), $this->c['helena']->id);

        // Sem nenhum posto marcado, tudo é do perfil (RN-36).
        $semPostos = $this->como($this->c['helenaU'])->getJson("/api/escalas/{$escala->id}/disponibilidade")->assertOk();
        $this->assertSame([true, true, true], collect($semPostos->json('turnos'))->pluck('do_meu_perfil')->all());
        $this->assertSame(1, $semPostos->json('total_marcados'));
        $this->assertSame([false, false, true], collect($semPostos->json('turnos'))->pluck('marquei')->all());

        // Com o posto Som marcado, o Palco fica "fora do seu perfil".
        $this->c['helena']->postos()->attach($som->id);
        $comPosto = $this->getJson("/api/escalas/{$escala->id}/disponibilidade")->assertOk();
        $this->assertSame([true, false, true], collect($comPosto->json('turnos'))->pluck('do_meu_perfil')->all());

        foreach ($comPosto->json('turnos') as $turno) {
            $this->assertArrayNotHasKey('vagas', $turno);
        }
    }
}
