<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Banda;
use App\Models\Culto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/**
 * O líder escolhe os módulos do ministério (RN-135).
 */
class ModulosTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function rota(string $ministerio = 'louvor'): string
    {
        return "/api/ministerios/{$this->c[$ministerio]->id}/modulos";
    }

    private function bandas(): array
    {
        return $this->naIgreja($this->c['igreja'], fn () => [
            Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 1', 'ordem' => 1]),
            Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 2', 'ordem' => 2]),
        ]);
    }

    private function desligar()
    {
        return $this->como($this->c['marcosU'])->putJson($this->rota(), ['ativos' => []])->assertOk();
    }

    public function test_t233_o_lider_ve_o_catalogo_com_o_estado_de_cada_modulo(): void
    {
        $this->como($this->c['marcosU'])->getJson($this->rota())->assertOk()
            ->assertJsonCount(1, 'modulos')
            ->assertJsonPath('modulos.0.chave', 'bandas')
            ->assertJsonPath('modulos.0.nome', 'Bandas')
            ->assertJsonPath('modulos.0.ativo', true);

        // Mídia nasce sem módulos.
        $this->como($this->c['anaU'])->getJson($this->rota('midia'))->assertOk()->assertJsonPath('modulos.0.ativo', false);
    }

    public function test_t234_so_o_lider_do_ministerio_mexe_nos_modulos(): void
    {
        foreach (['helenaU', 'anaU', 'admU'] as $quem) {
            $this->como($this->c[$quem])->getJson($this->rota())->assertForbidden();
            $this->como($this->c[$quem])->putJson($this->rota(), ['ativos' => []])->assertForbidden();
        }

        $this->como($this->c['marcosU'])->getJson($this->rota('midia'))->assertForbidden();
        $this->assertTrue($this->c['louvor']->fresh()->usa('bandas'));
    }

    public function test_t235_liga_e_desliga_e_a_escolha_fica_guardada(): void
    {
        $this->como($this->c['anaU'])->putJson($this->rota('midia'), ['ativos' => ['bandas']])
            ->assertOk()->assertJsonPath('modulos.0.ativo', true);
        $this->assertTrue($this->c['midia']->fresh()->usa('bandas'));
        $this->getJson($this->rota('midia'))->assertJsonPath('modulos.0.ativo', true);

        $this->putJson($this->rota('midia'), ['ativos' => []])->assertOk()->assertJsonPath('modulos.0.ativo', false);
        $this->assertFalse($this->c['midia']->fresh()->usa('bandas'));
    }

    public function test_t236_rejeita_modulo_que_nao_existe_ou_lista_malformada(): void
    {
        $this->como($this->c['marcosU']);

        $this->putJson($this->rota(), ['ativos' => ['bandas', 'foguete']])->assertStatus(422)->assertJsonValidationErrors('ativos.1');
        $this->putJson($this->rota(), ['ativos' => ['bandas', 'bandas']])->assertStatus(422);
        $this->putJson($this->rota(), [])->assertStatus(422)->assertJsonValidationErrors('ativos');
        $this->putJson($this->rota(), ['ativos' => 'bandas'])->assertStatus(422);

        $this->assertTrue($this->c['louvor']->fresh()->usa('bandas')); // nada mudou
    }

    public function test_t237_so_registra_o_que_mudou(): void
    {
        $this->como($this->c['marcosU']);
        $antes = Auditoria::withoutGlobalScopes()->where('acao', 'ministerio.modulo')->count();

        $this->putJson($this->rota(), ['ativos' => ['bandas']])->assertOk(); // já estava ligado
        $this->assertSame($antes, Auditoria::withoutGlobalScopes()->where('acao', 'ministerio.modulo')->count());

        $this->putJson($this->rota(), ['ativos' => []])->assertOk();
        $this->putJson($this->rota(), ['ativos' => ['bandas']])->assertOk();

        $registros = Auditoria::withoutGlobalScopes()->where('acao', 'ministerio.modulo')->orderBy('id')->get();
        $this->assertCount($antes + 2, $registros);
        $this->assertSame('Desativou o módulo Bandas em Louvor', $registros[$antes]->descricao);
        $this->assertSame('Ativou o módulo Bandas em Louvor', $registros[$antes + 1]->descricao);
        $this->assertSame($this->c['louvor']->id, $registros[$antes]->ministerio_id);
        $this->assertSame($this->c['marcos']->id, $registros[$antes]->pessoa_id);
    }

    public function test_t238_desligado_o_ministerio_se_comporta_como_sem_bandas_mas_nada_se_perde(): void
    {
        [$b1] = $this->bandas();
        $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/bandas")->assertJsonCount(2, 'bandas');

        $this->desligar();

        $this->getJson("/api/ministerios/{$this->c['louvor']->id}/bandas")->assertOk()->assertJsonCount(0, 'bandas');
        $this->assertSame(2, Banda::withoutGlobalScopes()->where('ministerio_id', $this->c['louvor']->id)->count()); // guardadas

        $this->putJson($this->rota(), ['ativos' => ['bandas']])->assertOk();
        $this->getJson("/api/ministerios/{$this->c['louvor']->id}/bandas")->assertJsonCount(2, 'bandas');
        $this->assertSame($b1->id, $this->getJson("/api/ministerios/{$this->c['louvor']->id}/bandas")->json('bandas.0.id'));
    }

    public function test_t239_desligado_nao_cria_banda_nem_escolhe_banda_num_turno(): void
    {
        [$b1] = $this->bandas();
        $escala = $this->escala($this->c['louvor'], 'rascunho');
        $posto = $this->posto($this->c['louvor']);
        $turno = $this->turno($escala, $posto);
        $this->desligar();

        $this->postJson("/api/ministerios/{$this->c['louvor']->id}/bandas", ['nome' => 'Banda 3'])
            ->assertStatus(422)->assertJsonPath('message', 'Ative o módulo Bandas deste ministério antes de criar bandas.');

        $dados = ['posto_id' => $posto->id, 'data' => '2026-11-08', 'inicio' => '08:00', 'fim' => '11:30'];
        $this->postJson("/api/escalas/{$escala->id}/turnos", $dados + ['banda_id' => $b1->id])
            ->assertStatus(422)->assertJsonPath('message', 'Ative o módulo Bandas deste ministério para escolher uma banda.');
        $this->postJson("/api/escalas/{$escala->id}/turnos", $dados + ['banda_id' => null])->assertCreated();
        $this->postJson("/api/escalas/{$escala->id}/turnos", $dados)->assertCreated();

        $this->patchJson("/api/turnos/{$turno->id}", ['versao' => $turno->versao, 'banda_id' => $b1->id])->assertStatus(422);
        $this->patchJson("/api/turnos/{$turno->id}", ['versao' => $turno->versao, 'banda_id' => null])->assertOk();
    }

    public function test_t240_desligado_gerar_turnos_nao_aplica_o_rodizio(): void
    {
        $this->bandas();
        $posto = $this->posto($this->c['louvor']);
        $culto = $this->naIgreja($this->c['igreja'], fn () => Culto::create(['nome' => 'Manhã', 'tipo' => 'semanal', 'dia_semana' => 0, 'ordem' => 1, 'inicio' => '08:00', 'fim' => '11:30']));
        $escala = $this->como($this->c['marcosU'])->postJson("/api/ministerios/{$this->c['louvor']->id}/escalas", ['mes' => '2026-11'])->assertCreated()->json('escala.id');
        $gerar = ['culto_ids' => [$culto->id], 'posto_ids' => [$posto->id]];

        $this->desligar();
        $this->postJson("/api/escalas/{$escala}/turnos/gerar", $gerar)->assertCreated();

        $bandas = collect($this->getJson("/api/escalas/{$escala}")->json('turnos'))->pluck('banda')->all();
        $this->assertNotEmpty($bandas);
        $this->assertSame([null], array_values(array_unique($bandas, SORT_REGULAR)));
    }

    public function test_t241_ligado_gerar_turnos_aplica_o_rodizio(): void
    {
        $this->bandas();
        $posto = $this->posto($this->c['louvor']);
        $culto = $this->naIgreja($this->c['igreja'], fn () => Culto::create(['nome' => 'Manhã', 'tipo' => 'semanal', 'dia_semana' => 0, 'ordem' => 1, 'inicio' => '08:00', 'fim' => '11:30']));
        $escala = $this->como($this->c['marcosU'])->postJson("/api/ministerios/{$this->c['louvor']->id}/escalas", ['mes' => '2026-11'])->assertCreated()->json('escala.id');

        $this->postJson("/api/escalas/{$escala}/turnos/gerar", ['culto_ids' => [$culto->id], 'posto_ids' => [$posto->id]])->assertCreated();

        $nomes = collect($this->getJson("/api/escalas/{$escala}")->json('turnos'))->pluck('banda.nome')->all();
        $this->assertSame(['Banda 1', 'Banda 2', 'Banda 1', 'Banda 2', 'Banda 1'], $nomes);
    }
}
