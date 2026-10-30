<?php

namespace Tests\Feature;

use App\Models\Funcao;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/**
 * O que o líder vê na escala e antes de publicar (RN-39, RN-21, D-06).
 */
class EscalaDoLiderTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function funcao(string $nome): Funcao
    {
        return $this->naIgreja($this->c['igreja'], fn () => Funcao::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => $nome]));
    }

    public function test_t171_o_lider_ve_quantos_marcaram_quem_esta_confirmado_e_quem_esta_sem_funcao(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $escala = $this->escala($this->c['louvor']);
        $turno = $this->turno($escala, $posto, '2026-11-08');
        $voz = $this->funcao('Voz');

        $extra = $this->naIgreja($this->c['igreja'], function () {
            $p = Pessoa::create(['nome' => 'Bruno']);
            $p->ministerios()->attach($this->c['louvor']->id);

            return $p;
        });

        $this->marcarDisponivel($turno, $this->c['helena']->id);
        $this->marcarDisponivel($turno, $extra->id);
        $this->marcarDisponivel($turno, $this->c['marcos']->id);

        $this->como($this->c['marcosU'])->postJson("/api/turnos/{$turno->id}/confirmacoes", ['pessoa_id' => $this->c['helena']->id, 'funcao_ids' => [$voz->id]])->assertCreated();
        $this->postJson("/api/turnos/{$turno->id}/confirmacoes", ['pessoa_id' => $extra->id])->assertCreated();

        $resposta = $this->getJson("/api/escalas/{$escala->id}")->assertOk();

        $this->assertSame(3, $resposta->json('turnos.0.marcaram_total'));
        $this->assertSame(2, $resposta->json('turnos.0.confirmados_total'));

        $porNome = collect($resposta->json('turnos.0.confirmados'))->keyBy('nome');
        $this->assertFalse($porNome['Helena']['sem_funcao']);
        $this->assertTrue($porNome['Bruno']['sem_funcao']);
    }

    public function test_sem_funcoes_cadastradas_ninguem_aparece_sem_funcao(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));
        $this->confirmar($turno, $this->c['helena']->id);

        $this->como($this->c['marcosU'])->getJson("/api/escalas/{$turno->escala_id}")
            ->assertJsonPath('turnos.0.confirmados.0.sem_funcao', false);
    }

    public function test_o_voluntario_nao_recebe_os_dados_do_lider(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));
        $this->confirmar($turno, $this->c['helena']->id);
        $this->marcarDisponivel($turno, $this->c['helena']->id);

        $voluntario = $this->como($this->c['helenaU'])->getJson("/api/escalas/{$turno->escala_id}")->assertOk()->json('turnos.0');

        foreach (['vagas', 'versao', 'confirmados_total', 'marcaram_total', 'confirmados'] as $campo) {
            $this->assertArrayNotHasKey($campo, $voluntario);
        }
    }

    public function test_t172_a_previa_de_publicacao_conta_incompletos_sem_funcao_e_choques(): void
    {
        $posto = $this->posto($this->c['louvor'], 'Som', 2);
        $escala = $this->escala($this->c['louvor']);
        $cheio = $this->turno($escala, $posto, '2026-11-01', '08:00', '11:30', 1);
        $incompleto = $this->turno($escala, $posto, '2026-11-08', '08:00', '11:30', 2);
        $this->turno($escala, $posto, '2026-11-15', '08:00', '11:30', 2); // ninguém confirmado

        $voz = $this->funcao('Voz');
        $naMidia = $this->turno($this->escala($this->c['midia'], 'publicada'), $this->posto($this->c['midia'], 'Câmera'), '2026-11-08', '09:00', '10:00');

        $this->confirmar($cheio, $this->c['helena']->id);
        $this->confirmar($incompleto, $this->c['helena']->id);
        $this->confirmar($naMidia, $this->c['helena']->id); // choca com o turno de 08/11 do Louvor
        $this->como($this->c['marcosU'])->putJson("/api/turnos/{$cheio->id}/confirmacoes/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$voz->id]])->assertOk();

        $previa = $this->getJson("/api/escalas/{$escala->id}/publicacao")->assertOk();

        $this->assertSame(3, $previa->json('total_de_turnos'));
        $this->assertSame(2, $previa->json('incompletos'));  // o turno de 08/11 (1 de 2) e o de 15/11 (0 de 2)
        $this->assertSame(1, $previa->json('sem_funcao'));   // a Helena em 08/11 não tem função
        $this->assertCount(1, $previa->json('choques'));
        $this->assertSame('Helena', $previa->json('choques.0.pessoa.nome'));
        $this->assertSame('Louvor', $previa->json('choques.0.turno.ministerio'));
        $this->assertSame('Mídia', $previa->json('choques.0.com.ministerio'));
    }

    public function test_choque_entre_dois_turnos_confirmados_conta_uma_vez_so(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $escala = $this->escala($this->c['louvor']);
        $a = $this->turno($escala, $posto, '2026-11-08', '08:00', '11:30');
        $b = $this->turno($escala, $posto, '2026-11-08', '10:00', '12:00');
        $this->confirmar($a, $this->c['helena']->id);
        $this->confirmar($b, $this->c['helena']->id);

        $this->como($this->c['marcosU'])->getJson("/api/escalas/{$escala->id}/publicacao")
            ->assertOk()->assertJsonCount(1, 'choques');
    }

    public function test_so_o_lider_do_ministerio_ve_a_previa(): void
    {
        $escala = $this->escala($this->c['louvor']);

        $this->como($this->c['helenaU'])->getJson("/api/escalas/{$escala->id}/publicacao")->assertForbidden();
        $this->como($this->c['anaU'])->getJson("/api/escalas/{$escala->id}/publicacao")->assertForbidden();
        $this->como($this->c['admU'])->getJson("/api/escalas/{$escala->id}/publicacao")->assertForbidden();
    }
}
