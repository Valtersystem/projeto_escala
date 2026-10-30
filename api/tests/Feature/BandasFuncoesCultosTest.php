<?php

namespace Tests\Feature;

use App\Models\Banda;
use App\Models\Escala;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/**
 * Bandas (RN-15, RN-29), funções (RN-14) e grade de cultos (RN-03 a RN-06).
 */
class BandasFuncoesCultosTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function louvor(): int
    {
        return $this->c['louvor']->id;
    }

    // ---- bandas ----

    public function test_t96_lider_cria_bandas_e_a_ordem_do_rodizio_segue_a_criacao(): void
    {
        $this->como($this->c['marcosU']);

        foreach (['Banda 1', 'Banda 2', 'Banda 3'] as $nome) {
            $this->postJson("/api/ministerios/{$this->louvor()}/bandas", ['nome' => $nome])->assertCreated();
        }

        $lista = $this->getJson("/api/ministerios/{$this->louvor()}/bandas")->assertOk();

        $this->assertSame(['Banda 1', 'Banda 2', 'Banda 3'], collect($lista->json('bandas'))->pluck('nome')->all());
        $this->assertSame([1, 2, 3], collect($lista->json('bandas'))->pluck('ordem')->all());
    }

    public function test_t97_so_o_lider_do_ministerio_mexe_nas_bandas(): void
    {
        $this->como($this->c['anaU'])->postJson("/api/ministerios/{$this->louvor()}/bandas", ['nome' => 'X'])->assertForbidden();
        $this->como($this->c['helenaU'])->postJson("/api/ministerios/{$this->louvor()}/bandas", ['nome' => 'X'])->assertForbidden();
        $this->como($this->c['admU'])->postJson("/api/ministerios/{$this->louvor()}/bandas", ['nome' => 'X'])->assertForbidden();

        // Quem serve no ministério vê as bandas; líder de outro ministério não.
        $this->como($this->c['helenaU'])->getJson("/api/ministerios/{$this->louvor()}/bandas")->assertOk();
        $this->como($this->c['anaU'])->getJson("/api/ministerios/{$this->louvor()}/bandas")->assertForbidden();
    }

    public function test_t98_membros_da_banda_so_da_equipe_do_ministerio(): void
    {
        $banda = $this->naIgreja($this->c['igreja'], fn () => Banda::create(['ministerio_id' => $this->louvor(), 'nome' => 'Banda 1', 'ordem' => 1]));

        $this->como($this->c['marcosU'])
            ->putJson("/api/bandas/{$banda->id}/membros", ['pessoa_ids' => [$this->c['helena']->id, $this->c['marcos']->id]])
            ->assertOk()
            ->assertJsonCount(2, 'banda.membros');

        // A Ana não está na equipe do Louvor.
        $this->putJson("/api/bandas/{$banda->id}/membros", ['pessoa_ids' => [$this->c['ana']->id]])
            ->assertStatus(422)->assertJsonValidationErrors('pessoa_ids');

        // Lista vazia esvazia a banda.
        $this->putJson("/api/bandas/{$banda->id}/membros", ['pessoa_ids' => []])->assertOk()->assertJsonCount(0, 'banda.membros');
    }

    public function test_t99_excluir_banda_mantem_o_turno_sem_banda(): void
    {
        [$banda, $turno] = $this->naIgreja($this->c['igreja'], function () {
            $banda = Banda::create(['ministerio_id' => $this->louvor(), 'nome' => 'Banda 1', 'ordem' => 1]);
            $posto = $this->c['louvor']->postos()->create(['nome' => 'Som', 'vagas' => 2]);
            $escala = Escala::create(['ministerio_id' => $this->louvor(), 'mes' => '2026-11']);
            $turno = Turno::create(['escala_id' => $escala->id, 'posto_id' => $posto->id, 'banda_id' => $banda->id,
                'data' => '2026-11-01', 'inicio' => '08:00', 'fim' => '11:30', 'vagas' => 2]);

            return [$banda, $turno];
        });

        $this->como($this->c['marcosU'])->deleteJson("/api/bandas/{$banda->id}")->assertNoContent();

        $this->assertNull(DB::table('turnos')->where('id', $turno->id)->value('banda_id'));
    }

    // ---- funções ----

    public function test_t100_lider_cria_funcoes_e_define_o_que_cada_pessoa_faz(): void
    {
        $this->como($this->c['marcosU']);

        $voz = $this->postJson("/api/ministerios/{$this->louvor()}/funcoes", ['nome' => 'Voz'])->assertCreated()->json('funcao.id');
        $guitarra = $this->postJson("/api/ministerios/{$this->louvor()}/funcoes", ['nome' => 'Guitarra'])->json('funcao.id');

        $this->putJson("/api/ministerios/{$this->louvor()}/equipe/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$voz, $guitarra]])
            ->assertOk()->assertJsonCount(2, 'funcao_ids');

        // Trocar a lista só mexe nas funções deste ministério.
        $this->putJson("/api/ministerios/{$this->louvor()}/equipe/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$voz]])
            ->assertOk()->assertJsonCount(1, 'funcao_ids');

        $this->assertSame(1, DB::table('funcao_pessoa')->where('pessoa_id', $this->c['helena']->id)->count());
    }

    public function test_funcao_de_outro_ministerio_ou_pessoa_fora_da_equipe_e_recusada(): void
    {
        $this->como($this->c['anaU']);
        $funcaoDaMidia = $this->postJson("/api/ministerios/{$this->c['midia']->id}/funcoes", ['nome' => 'Câmera'])->json('funcao.id');

        $this->como($this->c['marcosU'])
            ->putJson("/api/ministerios/{$this->louvor()}/equipe/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$funcaoDaMidia]])
            ->assertStatus(422)->assertJsonValidationErrors('funcao_ids');

        $this->putJson("/api/ministerios/{$this->louvor()}/equipe/{$this->c['ana']->id}/funcoes", ['funcao_ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('pessoa');
    }

    public function test_excluir_funcao_tira_das_pessoas_e_os_outros_papeis_nao_mexem(): void
    {
        $this->como($this->c['marcosU']);
        $voz = $this->postJson("/api/ministerios/{$this->louvor()}/funcoes", ['nome' => 'Voz'])->json('funcao.id');
        $this->putJson("/api/ministerios/{$this->louvor()}/equipe/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$voz]]);

        $this->como($this->c['helenaU'])->deleteJson("/api/funcoes/{$voz}")->assertForbidden();
        $this->como($this->c['marcosU'])->deleteJson("/api/funcoes/{$voz}")->assertNoContent();

        $this->assertSame(0, DB::table('funcao_pessoa')->count());
    }

    // ---- cultos ----

    public function test_t101_so_o_adm_altera_a_grade_e_o_horario_e_padronizado(): void
    {
        $dados = ['nome' => 'Culto da Família', 'tipo' => 'semanal', 'dia_semana' => 0, 'inicio' => '9h', 'fim' => '10h30'];

        $this->como($this->c['marcosU'])->postJson('/api/cultos', $dados)->assertForbidden();

        $this->como($this->c['admU'])->postJson('/api/cultos', $dados)
            ->assertCreated()
            ->assertJsonPath('culto.inicio', '09:00')
            ->assertJsonPath('culto.fim', '10:30');

        // Todos veem a grade.
        $this->como($this->c['helenaU'])->getJson('/api/cultos')->assertOk()->assertJsonCount(1, 'cultos');
    }

    public function test_culto_com_dados_invalidos_e_recusado(): void
    {
        $this->como($this->c['admU']);

        $this->postJson('/api/cultos', ['nome' => 'X', 'tipo' => 'anual', 'dia_semana' => 0, 'inicio' => '9h', 'fim' => '10h'])
            ->assertStatus(422)->assertJsonValidationErrors('tipo');
        $this->postJson('/api/cultos', ['nome' => 'X', 'tipo' => 'semanal', 'dia_semana' => 9, 'inicio' => '9h', 'fim' => '10h'])
            ->assertStatus(422)->assertJsonValidationErrors('dia_semana');
        $this->postJson('/api/cultos', ['nome' => 'X', 'tipo' => 'semanal', 'dia_semana' => 1, 'inicio' => 'manhã', 'fim' => '10h'])
            ->assertStatus(422)->assertJsonValidationErrors('inicio');
        $this->postJson('/api/cultos', ['nome' => 'X', 'tipo' => 'mensal', 'dia_semana' => 5, 'ordem' => 7, 'inicio' => '20h', 'fim' => '21h30'])
            ->assertStatus(422)->assertJsonValidationErrors('ordem');
    }

    public function test_t102_ocorrencias_do_culto_no_mes(): void
    {
        $this->como($this->c['admU']);

        $domingo = $this->postJson('/api/cultos', ['nome' => 'Domingo', 'tipo' => 'semanal', 'dia_semana' => 0, 'inicio' => '9h', 'fim' => '10h30'])->json('culto.id');
        $sexta = $this->postJson('/api/cultos', ['nome' => 'Sexta', 'tipo' => 'mensal', 'dia_semana' => 5, 'ordem' => 2, 'inicio' => '20h', 'fim' => '21h30'])->json('culto.id');
        $ultima = $this->postJson('/api/cultos', ['nome' => 'Última', 'tipo' => 'mensal', 'dia_semana' => 5, 'ordem' => -1, 'inicio' => '20h', 'fim' => '21h30'])->json('culto.id');

        $this->getJson("/api/cultos/{$domingo}/ocorrencias?mes=2026-11")
            ->assertOk()->assertJsonPath('datas', ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29']);
        $this->getJson("/api/cultos/{$sexta}/ocorrencias?mes=2026-11")->assertJsonPath('datas', ['2026-11-13']);
        $this->getJson("/api/cultos/{$ultima}/ocorrencias?mes=2026-11")->assertJsonPath('datas', ['2026-11-27']);
        $this->getJson("/api/cultos/{$domingo}/ocorrencias?mes=2026-13")->assertStatus(422);
    }

    public function test_t103_excluir_culto_mantem_o_turno_com_o_nome_do_culto(): void
    {
        $culto = $this->como($this->c['admU'])
            ->postJson('/api/cultos', ['nome' => 'Culto da Família', 'tipo' => 'semanal', 'dia_semana' => 0, 'inicio' => '9h', 'fim' => '10h30'])
            ->json('culto.id');

        $this->como($this->c['marcosU']);
        $posto = $this->postJson("/api/ministerios/{$this->louvor()}/postos", ['nome' => 'Som', 'vagas' => 2])->json('posto.id');
        $escala = $this->postJson("/api/ministerios/{$this->louvor()}/escalas", ['mes' => '2026-11'])->json('escala.id');
        $turno = $this->postJson("/api/escalas/{$escala}/turnos", [
            'posto_id' => $posto, 'culto_id' => $culto, 'data' => '2026-11-01', 'inicio' => '9h', 'fim' => '10h30',
        ])->assertCreated()->assertJsonPath('turno.culto', 'Culto da Família')->json('turno.id');

        $this->como($this->c['admU'])->deleteJson("/api/cultos/{$culto}")->assertNoContent();

        $guardado = DB::table('turnos')->where('id', $turno)->first();
        $this->assertNull($guardado->culto_id);
        $this->assertSame('Culto da Família', $guardado->culto_nome);
    }
}
