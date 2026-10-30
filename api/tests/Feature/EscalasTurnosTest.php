<?php

namespace Tests\Feature;

use App\Models\Banda;
use App\Models\Confirmacao;
use App\Models\Culto;
use App\Models\Ministerio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/**
 * Escalas, turnos e rodízio (RN-16 a RN-32, RN-64, D-14).
 */
class EscalasTurnosTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();

        // Louvor com um posto, três bandas na ordem do rodízio e dois cultos de domingo.
        $this->ids = $this->naIgreja($this->c['igreja'], function () {
            $posto = $this->c['louvor']->postos()->create(['nome' => 'Som', 'vagas' => 2]);

            $bandas = [];
            foreach (['Banda 1', 'Banda 2', 'Banda 3'] as $i => $nome) {
                $bandas[] = Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => $nome, 'ordem' => $i + 1])->id;
            }

            $manha = Culto::create(['nome' => 'Manhã', 'tipo' => 'semanal', 'dia_semana' => 0, 'ordem' => 1, 'inicio' => '08:00', 'fim' => '11:30']);
            $noite = Culto::create(['nome' => 'Noite', 'tipo' => 'semanal', 'dia_semana' => 0, 'ordem' => 1, 'inicio' => '19:00', 'fim' => '21:00']);

            return [
                'posto' => $posto->id, 'b1' => $bandas[0], 'b2' => $bandas[1], 'b3' => $bandas[2],
                'manha' => $manha->id, 'noite' => $noite->id,
            ];
        });
    }

    private function louvor(): int
    {
        return $this->c['louvor']->id;
    }

    private function novaEscala(string $mes, ?int $ministerio = null, ?\App\Models\User $como = null): int
    {
        return $this->como($como ?? $this->c['marcosU'])
            ->postJson('/api/ministerios/'.($ministerio ?? $this->louvor()).'/escalas', ['mes' => $mes])
            ->assertCreated()->json('escala.id');
    }

    private function turnoAvulso(int $escala, string $data = '2026-11-01'): array
    {
        return $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $this->ids['posto'], 'data' => $data, 'inicio' => '8h', 'fim' => '11h30',
        ])->assertCreated()->json('turno');
    }

    /** @return list<string> banda de cada turno, na ordem de data e horário */
    private function bandasDaEscala(int $escala): array
    {
        return collect($this->como($this->c['marcosU'])->getJson("/api/escalas/{$escala}")->json('turnos'))
            ->map(fn ($t) => $t['banda']['nome'] ?? '—')->all();
    }

    private function gerar(int $escala, array $cultos, ?array $postos = null)
    {
        return $this->como($this->c['marcosU'])->postJson("/api/escalas/{$escala}/turnos/gerar", [
            'culto_ids' => $cultos, 'posto_ids' => $postos ?? [$this->ids['posto']],
        ]);
    }

    // ---- escalas ----

    public function test_t104_cria_a_escala_com_o_prazo_sugerido_pelo_ajuste_da_igreja(): void
    {
        $this->como($this->c['marcosU']);

        $this->postJson("/api/ministerios/{$this->louvor()}/escalas", ['mes' => '2026-11'])
            ->assertCreated()
            ->assertJsonPath('escala.status', 'rascunho')
            ->assertJsonPath('escala.prazo', '2026-10-27')
            ->assertJsonPath('escala.versao', 1)
            ->assertJsonPath('escala.total_de_turnos', 0);

        // O líder pode escolher o prazo.
        $this->postJson("/api/ministerios/{$this->louvor()}/escalas", ['mes' => '2026-12', 'prazo' => '2026-11-20'])
            ->assertCreated()->assertJsonPath('escala.prazo', '2026-11-20');

        $this->postJson("/api/ministerios/{$this->louvor()}/escalas", ['mes' => '2026-13'])->assertStatus(422);
        $this->postJson("/api/ministerios/{$this->louvor()}/escalas", [])->assertStatus(422);
    }

    public function test_t105_nao_ha_duas_escalas_no_mesmo_mes_do_ministerio(): void
    {
        $this->novaEscala('2026-11');

        $this->postJson("/api/ministerios/{$this->louvor()}/escalas", ['mes' => '2026-11'])
            ->assertStatus(422)->assertJsonPath('message', 'Esse mês já tem escala.');

        // Outro ministério pode ter a sua no mesmo mês.
        $this->como($this->c['anaU'])->postJson("/api/ministerios/{$this->c['midia']->id}/escalas", ['mes' => '2026-11'])->assertCreated();
    }

    public function test_t106_so_o_lider_do_ministerio_cria_escala_e_outra_igreja_nao_aparece(): void
    {
        $url = "/api/ministerios/{$this->louvor()}/escalas";

        $this->como($this->c['helenaU'])->postJson($url, ['mes' => '2026-11'])->assertForbidden();
        $this->como($this->c['anaU'])->postJson($url, ['mes' => '2026-11'])->assertForbidden();
        $this->como($this->c['admU'])->postJson($url, ['mes' => '2026-11'])->assertForbidden();

        $outra = $this->igreja('Igreja B');
        $escalaDeB = $this->naIgreja($outra, function () {
            $m = Ministerio::create(['nome' => 'Louvor B']);

            return $m->escalas()->create(['mes' => '2026-11']);
        });

        $this->como($this->c['marcosU'])->getJson("/api/escalas/{$escalaDeB->id}")->assertNotFound();
    }

    public function test_t107_rascunho_so_o_lider_ve(): void
    {
        $escala = $this->novaEscala('2026-11');

        $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->louvor()}/escalas")->assertJsonCount(1, 'escalas');
        $this->como($this->c['helenaU'])->getJson("/api/ministerios/{$this->louvor()}/escalas")->assertOk()->assertJsonCount(0, 'escalas');
        $this->getJson("/api/escalas/{$escala}")->assertNotFound();
    }

    public function test_t108_so_libera_com_pelo_menos_um_turno_e_so_uma_vez(): void
    {
        $escala = $this->novaEscala('2026-11');

        $this->postJson("/api/escalas/{$escala}/liberar")
            ->assertStatus(422)->assertJsonPath('message', 'Adicione pelo menos um turno antes de liberar a escala.');

        $this->turnoAvulso($escala);

        $this->postJson("/api/escalas/{$escala}/liberar")
            ->assertOk()->assertJsonPath('escala.status', 'aberta')->assertJsonPath('escala.versao', 2);

        $this->postJson("/api/escalas/{$escala}/liberar")->assertStatus(422);
    }

    public function test_t109_publica_mesmo_com_turnos_incompletos_mas_nao_pula_a_etapa_aberta(): void
    {
        $escala = $this->novaEscala('2026-11');
        $this->turnoAvulso($escala);

        $this->postJson("/api/escalas/{$escala}/publicar")->assertStatus(422);

        $this->postJson("/api/escalas/{$escala}/liberar")->assertOk();
        $this->postJson("/api/escalas/{$escala}/publicar")->assertOk()->assertJsonPath('escala.status', 'publicada');
    }

    public function test_t110_reabre_so_escala_publicada(): void
    {
        $escala = $this->novaEscala('2026-11');
        $this->turnoAvulso($escala);
        $this->postJson("/api/escalas/{$escala}/liberar");

        $this->postJson("/api/escalas/{$escala}/reabrir")->assertStatus(422);

        $this->postJson("/api/escalas/{$escala}/publicar");
        $this->postJson("/api/escalas/{$escala}/reabrir")->assertOk()->assertJsonPath('escala.status', 'aberta');

        // Só o líder muda o estado.
        $this->como($this->c['helenaU'])->postJson("/api/escalas/{$escala}/publicar")->assertForbidden();
    }

    public function test_t111_voluntario_nao_ve_vagas_e_em_escala_publicada_so_os_turnos_em_que_esta_confirmado(): void
    {
        $escala = $this->novaEscala('2026-11');
        $primeiro = $this->turnoAvulso($escala, '2026-11-01');
        $this->turnoAvulso($escala, '2026-11-08');
        $this->postJson("/api/escalas/{$escala}/liberar");

        // Aberta: o voluntário vê os dois turnos, sem a quantidade de vagas (RN-64).
        $aberta = $this->como($this->c['helenaU'])->getJson("/api/escalas/{$escala}")->assertOk();
        $aberta->assertJsonCount(2, 'turnos');
        $this->assertArrayNotHasKey('vagas', $aberta->json('turnos.0'));
        $this->assertArrayNotHasKey('versao', $aberta->json('turnos.0'));

        // O líder vê as vagas.
        $this->como($this->c['marcosU'])->getJson("/api/escalas/{$escala}")->assertJsonPath('turnos.0.vagas', 2);

        // Publicada: só aparece o turno em que a Helena está confirmada (RN-23).
        Confirmacao::create(['turno_id' => $primeiro['id'], 'pessoa_id' => $this->c['helena']->id]);
        $this->postJson("/api/escalas/{$escala}/publicar");

        $publicada = $this->como($this->c['helenaU'])->getJson("/api/escalas/{$escala}")->assertOk();
        $publicada->assertJsonCount(1, 'turnos');
        $this->assertSame($primeiro['id'], $publicada->json('turnos.0.id'));
    }

    // ---- gerar turnos e rodízio ----

    public function test_t112_gera_um_turno_por_data_do_culto_com_as_vagas_do_posto_e_o_rodizio(): void
    {
        $escala = $this->novaEscala('2026-11');

        $resposta = $this->gerar($escala, [$this->ids['manha']])->assertCreated()->assertJsonPath('total', 5);

        $this->assertSame([2, 2, 2, 2, 2], collect($resposta->json('turnos'))->pluck('vagas')->all());
        $this->assertSame(
            ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'],
            collect($resposta->json('turnos'))->pluck('data')->all(),
        );
        $this->assertSame(['Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2'], $this->bandasDaEscala($escala));
    }

    public function test_t113_gerar_de_novo_nao_duplica(): void
    {
        $escala = $this->novaEscala('2026-11');
        $this->gerar($escala, [$this->ids['manha']])->assertCreated();

        $this->gerar($escala, [$this->ids['manha']])
            ->assertStatus(422)->assertJsonPath('message', 'Esses turnos já existem nesta escala.');

        $this->assertSame(5, DB::table('turnos')->count());

        // Um culto novo entra normalmente e não repete os existentes.
        $this->gerar($escala, [$this->ids['manha'], $this->ids['noite']])->assertCreated()->assertJsonPath('total', 5);
        $this->assertSame(10, DB::table('turnos')->count());
    }

    public function test_t114_sem_posto_ou_com_posto_de_outro_ministerio_nao_gera(): void
    {
        // Mídia ainda não tem posto.
        $midia = $this->novaEscala('2026-11', $this->c['midia']->id, $this->c['anaU']);
        $this->como($this->c['anaU'])->postJson("/api/escalas/{$midia}/turnos/gerar", ['culto_ids' => [$this->ids['manha']], 'posto_ids' => [1]])
            ->assertStatus(422)->assertJsonPath('message', 'Crie um posto antes.');

        $postoDaMidia = $this->postJson("/api/ministerios/{$this->c['midia']->id}/postos", ['nome' => 'Câmera'])->json('posto.id');

        // O líder do Louvor não pode usar o posto da Mídia.
        $escala = $this->novaEscala('2026-11');
        $this->gerar($escala, [$this->ids['manha']], [$postoDaMidia])
            ->assertStatus(422)->assertJsonValidationErrors('posto_ids');
    }

    public function test_t115_manha_e_noite_seguem_a_sequencia_da_planilha_da_lider(): void
    {
        $escala = $this->novaEscala('2026-11');

        $this->gerar($escala, [$this->ids['noite'], $this->ids['manha']])->assertCreated()->assertJsonPath('total', 10);

        $this->assertSame(
            ['Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2', 'Banda 3', 'Banda 1'],
            $this->bandasDaEscala($escala),
        );
    }

    public function test_t116_dezembro_continua_de_onde_novembro_parou(): void
    {
        $novembro = $this->novaEscala('2026-11');
        $this->gerar($novembro, [$this->ids['manha'], $this->ids['noite']])->assertCreated();

        $dezembro = $this->novaEscala('2026-12');
        $this->gerar($dezembro, [$this->ids['manha'], $this->ids['noite']])->assertCreated()->assertJsonPath('total', 8);

        // Novembro terminou na Banda 1 (29/11 à noite).
        $this->assertSame(
            ['Banda 2', 'Banda 3', 'Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2', 'Banda 3'],
            $this->bandasDaEscala($dezembro),
        );
    }

    public function test_t117_ministerio_sem_bandas_gera_turnos_sem_banda(): void
    {
        $this->como($this->c['anaU']);
        $posto = $this->postJson("/api/ministerios/{$this->c['midia']->id}/postos", ['nome' => 'Câmera', 'vagas' => 1])->json('posto.id');
        $escala = $this->novaEscala('2026-11', $this->c['midia']->id, $this->c['anaU']);

        $this->como($this->c['anaU'])->postJson("/api/escalas/{$escala}/turnos/gerar", [
            'culto_ids' => [$this->ids['manha']], 'posto_ids' => [$posto],
        ])->assertCreated()->assertJsonPath('total', 5)->assertJsonPath('turnos.0.banda', null);
    }

    public function test_t118_dois_postos_no_mesmo_horario_recebem_a_mesma_banda(): void
    {
        $palco = $this->como($this->c['marcosU'])
            ->postJson("/api/ministerios/{$this->louvor()}/postos", ['nome' => 'Palco', 'vagas' => 3])->json('posto.id');
        $escala = $this->novaEscala('2026-11');

        $turnos = $this->gerar($escala, [$this->ids['manha']], [$this->ids['posto'], $palco])
            ->assertCreated()->assertJsonPath('total', 10)->json('turnos');

        $bandaPorData = collect($turnos)->groupBy('data')->map(fn ($grupo) => $grupo->pluck('banda.nome')->unique()->values()->all());

        foreach ($bandaPorData as $nomes) {
            $this->assertCount(1, $nomes);
        }

        $this->assertSame(['Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2'], $bandaPorData->flatten()->all());
    }

    public function test_t119_culto_que_nao_cai_no_mes_nao_gera_nada(): void
    {
        $quintaQuarta = $this->naIgreja($this->c['igreja'], fn () => Culto::create([
            'nome' => 'Quinta quarta', 'tipo' => 'mensal', 'dia_semana' => 3, 'ordem' => 5, 'inicio' => '20:00', 'fim' => '21:30',
        ]));
        $escala = $this->novaEscala('2026-11');

        $this->gerar($escala, [$quintaQuarta->id])
            ->assertStatus(422)->assertJsonPath('message', 'Nenhum culto cai neste mês.');
    }

    public function test_gerar_exige_escala_nao_publicada_e_escolhas_validas(): void
    {
        $escala = $this->novaEscala('2026-11');

        $this->gerar($escala, [])->assertStatus(422)->assertJsonValidationErrors('culto_ids');
        $this->gerar($escala, [999999])->assertStatus(422)->assertJsonValidationErrors('culto_ids');

        $this->turnoAvulso($escala);
        $this->postJson("/api/escalas/{$escala}/liberar");
        $this->postJson("/api/escalas/{$escala}/publicar");

        $this->gerar($escala, [$this->ids['manha']])->assertStatus(422);
        $this->como($this->c['helenaU'])->postJson("/api/escalas/{$escala}/turnos/gerar", [
            'culto_ids' => [$this->ids['manha']], 'posto_ids' => [$this->ids['posto']],
        ])->assertForbidden();
    }

    // ---- turnos avulsos, edição e exclusão ----

    public function test_t120_turno_avulso_usa_as_vagas_do_posto_e_padroniza_o_horario(): void
    {
        $escala = $this->novaEscala('2026-11');

        $turno = $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $this->ids['posto'], 'culto_id' => $this->ids['manha'],
            'data' => '2026-11-08', 'inicio' => '8h30', 'fim' => '11h',
        ])->assertCreated();

        $turno->assertJsonPath('turno.inicio', '08:30')
            ->assertJsonPath('turno.fim', '11:00')
            ->assertJsonPath('turno.vagas', 2)
            ->assertJsonPath('turno.culto', 'Manhã')
            ->assertJsonPath('turno.posto.nome', 'Som');

        $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $this->ids['posto'], 'data' => '2026-11-15', 'inicio' => '8h', 'fim' => '9h', 'vagas' => 0,
        ])->assertCreated()->assertJsonPath('turno.vagas', 1);

        $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $this->ids['posto'], 'data' => '2026-11-15', 'inicio' => 'cedo', 'fim' => '9h',
        ])->assertStatus(422)->assertJsonValidationErrors('inicio');
    }

    public function test_turno_so_aceita_posto_e_banda_do_proprio_ministerio(): void
    {
        $postoDaMidia = $this->como($this->c['anaU'])
            ->postJson("/api/ministerios/{$this->c['midia']->id}/postos", ['nome' => 'Câmera'])->json('posto.id');
        $escala = $this->novaEscala('2026-11');

        $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $postoDaMidia, 'data' => '2026-11-01', 'inicio' => '8h', 'fim' => '9h',
        ])->assertStatus(422)->assertJsonValidationErrors('posto_id');

        $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $this->ids['posto'], 'banda_id' => 999999, 'data' => '2026-11-01', 'inicio' => '8h', 'fim' => '9h',
        ])->assertStatus(422)->assertJsonValidationErrors('banda_id');
    }

    public function test_t121_o_que_se_edita_no_turno_depende_do_estado_da_escala(): void
    {
        $escala = $this->novaEscala('2026-11');
        $turno = $this->turnoAvulso($escala);

        // Rascunho: muda tudo.
        $editado = $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 1, 'data' => '2026-11-08', 'inicio' => '9h', 'vagas' => 3, 'banda_id' => $this->ids['b2']])
            ->assertOk()->assertJsonPath('turno.data', '2026-11-08')->assertJsonPath('turno.inicio', '09:00')
            ->assertJsonPath('turno.vagas', 3)->assertJsonPath('turno.banda.nome', 'Banda 2')->assertJsonPath('turno.versao', 2);

        $this->postJson("/api/escalas/{$escala}/liberar");

        // Aberta: só as vagas, e só para mais.
        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 2, 'data' => '2026-11-15'])->assertStatus(422);
        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 2, 'vagas' => 1])->assertStatus(422)
            ->assertJsonPath('message', 'Depois de liberada, as vagas só podem aumentar.');
        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 2, 'vagas' => 4])->assertOk()->assertJsonPath('turno.vagas', 4);

        // Publicada: nada muda.
        $this->postJson("/api/escalas/{$escala}/publicar");
        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 3, 'vagas' => 5])
            ->assertStatus(422)->assertJsonPath('message', 'Reabra a escala para alterar o turno.');

        $this->assertNotNull($editado->json('turno.id'));
    }

    public function test_t122_so_exclui_turno_em_rascunho_e_leva_disponibilidades_e_confirmacoes(): void
    {
        $escala = $this->novaEscala('2026-11');
        $primeiro = $this->turnoAvulso($escala, '2026-11-01');
        $segundo = $this->turnoAvulso($escala, '2026-11-08');

        DB::table('disponibilidades')->insert(['turno_id' => $primeiro['id'], 'pessoa_id' => $this->c['helena']->id, 'created_at' => now(), 'updated_at' => now()]);
        Confirmacao::create(['turno_id' => $primeiro['id'], 'pessoa_id' => $this->c['helena']->id]);

        $this->deleteJson("/api/turnos/{$primeiro['id']}")->assertNoContent();

        $this->assertSame(0, DB::table('disponibilidades')->count());
        $this->assertSame(0, DB::table('confirmacoes')->count());

        $this->postJson("/api/escalas/{$escala}/liberar");
        $this->deleteJson("/api/turnos/{$segundo['id']}")
            ->assertStatus(422)->assertJsonPath('message', 'Só dá para excluir turnos de escala em rascunho.');

        $this->como($this->c['helenaU'])->deleteJson("/api/turnos/{$segundo['id']}")->assertForbidden();
    }

    // ---- concorrência (D-14) ----

    public function test_t123_edicao_com_versao_antiga_responde_409_e_nao_grava(): void
    {
        $escala = $this->novaEscala('2026-11');
        $turno = $this->turnoAvulso($escala);

        // Outra pessoa já alterou o turno (versão 1 → 2).
        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 1, 'vagas' => 3])->assertOk();

        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 1, 'vagas' => 9])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este registro foi alterado por outra pessoa. Recarregue a tela e tente de novo.');

        $this->assertSame(3, DB::table('turnos')->where('id', $turno['id'])->value('vagas'));
        $this->assertSame(2, DB::table('turnos')->where('id', $turno['id'])->value('versao'));

        // Com a versão atual, funciona.
        $this->patchJson("/api/turnos/{$turno['id']}", ['versao' => 2, 'vagas' => 4])->assertOk()->assertJsonPath('turno.versao', 3);
    }

    public function test_t124_prazo_da_escala_tambem_usa_versao_e_o_lider_pode_estender(): void
    {
        $escala = $this->novaEscala('2026-11');

        $this->patchJson("/api/escalas/{$escala}", ['prazo' => '2026-11-03', 'versao' => 1])
            ->assertOk()->assertJsonPath('escala.prazo', '2026-11-03')->assertJsonPath('escala.versao', 2);

        $this->patchJson("/api/escalas/{$escala}", ['prazo' => '2026-11-10', 'versao' => 1])->assertStatus(409);
        $this->patchJson("/api/escalas/{$escala}", ['prazo' => 'amanhã', 'versao' => 2])->assertStatus(422);
        $this->patchJson("/api/escalas/{$escala}", ['prazo' => '2026-11-10'])->assertStatus(422)->assertJsonValidationErrors('versao');

        $this->como($this->c['helenaU'])->patchJson("/api/escalas/{$escala}", ['prazo' => '2026-11-10', 'versao' => 2])->assertForbidden();
    }
}
