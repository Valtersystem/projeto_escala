<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Pessoa;
use App\Models\Troca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/** D-12: quem mudou o quê. */
class AuditoriaTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function registro(string $acao): ?Auditoria
    {
        return Auditoria::withoutGlobalScopes()->where('acao', $acao)->latest('id')->first();
    }

    public function test_t199_liberar_publicar_e_reabrir_ficam_registrados_com_quem_fez(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $escala = $this->escala($this->c['louvor'], 'rascunho');
        $this->turno($escala, $posto);

        $this->como($this->c['marcosU'])->postJson("/api/escalas/{$escala->id}/liberar")->assertOk();
        $this->como($this->c['marcosU'])->postJson("/api/escalas/{$escala->id}/publicar")->assertOk();
        $this->como($this->c['marcosU'])->postJson("/api/escalas/{$escala->id}/reabrir")->assertOk();

        $publicada = $this->registro('escala.publicada');
        $this->assertSame($this->c['marcos']->id, $publicada->pessoa_id);
        $this->assertSame($this->c['louvor']->id, $publicada->ministerio_id);
        $this->assertSame('Publicou a escala de 2026-11', $publicada->descricao);
        $this->assertSame(['status' => 'aberta'], $publicada->antes);
        $this->assertSame(['status' => 'publicada'], $publicada->depois);
        $this->assertNotNull($this->registro('escala.liberada'));
        $this->assertSame('Reabriu a escala de 2026-11', $this->registro('escala.reaberta')->descricao);
    }

    public function test_t200_mudar_o_prazo_guarda_o_antes_e_o_depois(): void
    {
        $escala = $this->escala($this->c['louvor'], 'aberta');

        $this->como($this->c['marcosU'])->patchJson("/api/escalas/{$escala->id}", ['prazo' => '2999-01-15', 'versao' => $escala->versao])->assertOk();

        $r = $this->registro('escala.prazo');
        $this->assertSame(['prazo' => '2999-12-31'], $r->antes);
        $this->assertSame(['prazo' => '2999-01-15'], $r->depois);
    }

    public function test_t201_turno_so_entra_na_trilha_depois_de_liberada_a_escala(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $rascunho = $this->escala($this->c['louvor'], 'rascunho');
        $turnoRascunho = $this->turno($rascunho, $posto);

        $this->como($this->c['marcosU'])->patchJson("/api/turnos/{$turnoRascunho->id}", ['vagas' => 5, 'versao' => $turnoRascunho->versao])->assertOk();
        $this->assertNull($this->registro('turno.alterado'));

        $aberta = $this->escala($this->c['louvor'], 'aberta', '2026-12');
        $turno = $this->turno($aberta, $posto, '2026-12-06');
        $this->como($this->c['marcosU'])->patchJson("/api/turnos/{$turno->id}", ['vagas' => 4, 'versao' => $turno->versao])->assertOk();

        $r = $this->registro('turno.alterado');
        $this->assertSame(['vagas' => 2], $r->antes);
        $this->assertSame(['vagas' => 4], $r->depois);
        $this->assertStringContainsString('Som · 06/12 às 08:00', $r->descricao);
    }

    public function test_t202_confirmar_trocar_funcoes_e_tirar_pessoa_ficam_registrados(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $turno = $this->turno($this->escala($this->c['louvor'], 'aberta'), $posto);
        $helena = $this->c['helena'];

        $this->como($this->c['marcosU'])->postJson("/api/turnos/{$turno->id}/confirmacoes", ['pessoa_id' => $helena->id])->assertCreated();
        $this->como($this->c['marcosU'])->deleteJson("/api/turnos/{$turno->id}/confirmacoes/{$helena->id}")->assertNoContent();

        $this->assertStringContainsString('Confirmou Helena em Som', $this->registro('confirmacao.criada')->descricao);
        $this->assertStringContainsString('Tirou Helena de Som', $this->registro('confirmacao.removida')->descricao);

        // Tirar quem nem estava confirmado não gera registro.
        $antes = Auditoria::withoutGlobalScopes()->count();
        $this->como($this->c['marcosU'])->deleteJson("/api/turnos/{$turno->id}/confirmacoes/{$helena->id}")->assertNoContent();
        $this->assertSame($antes, Auditoria::withoutGlobalScopes()->count());
    }

    public function test_t203_resolver_pedido_de_troca_fica_registrado(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $turno = $this->turno($this->escala($this->c['louvor'], 'publicada'), $posto);
        $this->confirmar($turno, $this->c['helena']->id);
        $troca = $this->naIgreja($this->c['igreja'], fn () => Troca::create(['turno_id' => $turno->id, 'pessoa_id' => $this->c['helena']->id, 'motivo' => 'Viagem']));

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/manter")->assertOk();

        $this->assertStringContainsString('Pediu que Helena continue em Som', $this->registro('troca.mantida')->descricao);
    }

    public function test_t204_pessoa_ativo_adm_e_exclusao_ficam_registrados_so_quando_mudam(): void
    {
        $alvo = $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => 'Carla']));

        $this->como($this->c['admU'])->patchJson("/api/pessoas/{$alvo->id}", ['ativo' => false])->assertOk();
        $this->como($this->c['admU'])->patchJson("/api/pessoas/{$alvo->id}", ['ativo' => false, 'nome' => 'Carla D.'])->assertOk();
        $this->como($this->c['admU'])->patchJson("/api/pessoas/{$alvo->id}", ['adm' => true])->assertOk();
        $this->como($this->c['admU'])->deleteJson("/api/pessoas/{$alvo->id}")->assertNoContent();

        $this->assertSame(1, Auditoria::withoutGlobalScopes()->where('acao', 'pessoa.ativo')->count());
        $this->assertSame('Desativou Carla', $this->registro('pessoa.ativo')->descricao);
        $this->assertSame('Tornou Adm Carla D.', $this->registro('pessoa.adm')->descricao);
        $this->assertSame('Excluiu Carla D.', $this->registro('pessoa.excluida')->descricao);
    }

    public function test_t205_o_adm_ve_tudo_em_ordem_do_mais_novo_com_quem_fez(): void
    {
        $escala = $this->escala($this->c['louvor'], 'aberta');
        $this->como($this->c['marcosU'])->patchJson("/api/escalas/{$escala->id}", ['prazo' => '2999-01-15', 'versao' => $escala->versao])->assertOk();
        $this->como($this->c['admU'])->putJson("/api/ministerios/{$this->c['midia']->id}/lider", ['pessoa_id' => $this->c['helena']->id])->assertOk();

        $r = $this->como($this->c['admU'])->getJson('/api/auditoria')->assertOk();

        $this->assertSame('ministerio.lider', $r->json('registros.0.acao'));
        $this->assertSame('Valter', $r->json('registros.0.quem.nome'));
        $this->assertSame('escala.prazo', $r->json('registros.1.acao'));
        $this->assertSame('Marcos', $r->json('registros.1.quem.nome'));
        $this->assertFalse($r->json('tem_mais'));
    }

    public function test_t206_o_lider_ve_so_o_historico_do_proprio_ministerio(): void
    {
        $louvor = $this->escala($this->c['louvor'], 'aberta');
        $midia = $this->escala($this->c['midia'], 'aberta');
        $this->como($this->c['marcosU'])->patchJson("/api/escalas/{$louvor->id}", ['prazo' => '2999-01-15', 'versao' => $louvor->versao])->assertOk();
        $this->como($this->c['anaU'])->patchJson("/api/escalas/{$midia->id}", ['prazo' => '2999-01-16', 'versao' => $midia->versao])->assertOk();

        $r = $this->como($this->c['marcosU'])->getJson("/api/auditoria?ministerio_id={$this->c['louvor']->id}")->assertOk();

        $this->assertCount(1, $r->json('registros'));
        $this->assertSame($this->c['louvor']->id, Auditoria::withoutGlobalScopes()->find($r->json('registros.0.id'))->ministerio_id);
    }

    public function test_t207_quem_nao_e_adm_nem_lider_nao_ve_a_trilha(): void
    {
        $this->como($this->c['marcosU'])->getJson('/api/auditoria')->assertForbidden();
        $this->como($this->c['marcosU'])->getJson("/api/auditoria?ministerio_id={$this->c['midia']->id}")->assertForbidden();
        $this->como($this->c['helenaU'])->getJson("/api/auditoria?ministerio_id={$this->c['louvor']->id}")->assertForbidden();
    }

    public function test_t208_a_trilha_nao_vaza_entre_igrejas_e_pagina(): void
    {
        $outra = $this->igreja('Igreja B');
        [$bU] = $this->pessoaComLogin($outra, 'adm@b.com', ['nome' => 'Admin B', 'adm' => true]);

        $this->naIgreja($this->c['igreja'], function () {
            foreach (range(1, 35) as $i) {
                Auditoria::create(['acao' => 'pessoa.criada', 'entidade' => 'pessoa', 'descricao' => "Cadastrou {$i}"]);
            }
        });

        $this->como($bU)->getJson('/api/auditoria')->assertOk()->assertJsonCount(0, 'registros');

        $p1 = $this->como($this->c['admU'])->getJson('/api/auditoria')->assertOk();
        $this->assertCount(30, $p1->json('registros'));
        $this->assertTrue($p1->json('tem_mais'));
        $this->assertCount(5, $this->como($this->c['admU'])->getJson('/api/auditoria?pagina=2')->json('registros'));
    }
}
