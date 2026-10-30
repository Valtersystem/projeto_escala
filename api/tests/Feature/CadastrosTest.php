<?php

namespace Tests\Feature;

use App\Models\Banda;
use App\Models\Escala;
use App\Models\Funcao;
use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\Turno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/**
 * Cadastros e permissões por papel (seções 2, 4 e 5 do documento de regras).
 */
class CadastrosTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    // ---- ministérios ----

    public function test_t74_so_o_adm_cria_ministerio(): void
    {
        $this->como($this->c['admU'])->postJson('/api/ministerios', ['nome' => 'Recepção'])
            ->assertCreated()
            ->assertJsonPath('ministerio.nome', 'Recepção');

        $this->assertSame($this->c['igreja']->id, DB::table('ministerios')->where('nome', 'Recepção')->value('igreja_id'));

        $this->como($this->c['marcosU'])->postJson('/api/ministerios', ['nome' => 'Cozinha'])->assertForbidden();
        $this->como($this->c['helenaU'])->postJson('/api/ministerios', ['nome' => 'Cozinha'])->assertForbidden();
    }

    public function test_t75_nome_vazio_ou_repetido_e_recusado(): void
    {
        $this->como($this->c['admU'])->postJson('/api/ministerios', ['nome' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nome');

        $this->postJson('/api/ministerios', ['nome' => 'Louvor'])
            ->assertStatus(422)->assertJsonValidationErrors('nome');

        $this->postJson('/api/pessoas', ['nome' => ''])->assertStatus(422)->assertJsonValidationErrors('nome');

        $this->como($this->c['marcosU'])
            ->postJson('/api/ministerios/'.$this->c['louvor']->id.'/postos', ['nome' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_t76_adm_vincula_o_lider(): void
    {
        $this->como($this->c['admU'])
            ->putJson('/api/ministerios/'.$this->c['louvor']->id.'/lider', ['pessoa_id' => $this->c['helena']->id])
            ->assertOk()
            ->assertJsonPath('ministerio.lider.nome', 'Helena');

        $this->como($this->c['marcosU'])
            ->putJson('/api/ministerios/'.$this->c['louvor']->id.'/lider', ['pessoa_id' => $this->c['marcos']->id])
            ->assertForbidden();
    }

    public function test_t80_excluir_ministerio_apaga_postos_escalas_e_turnos_e_mantem_as_pessoas(): void
    {
        $this->naIgreja($this->c['igreja'], function () {
            $posto = $this->c['louvor']->postos()->create(['nome' => 'Som', 'vagas' => 2]);
            $escala = Escala::create(['ministerio_id' => $this->c['louvor']->id, 'mes' => '2026-11']);
            Turno::create(['escala_id' => $escala->id, 'posto_id' => $posto->id, 'data' => '2026-11-01',
                'inicio' => '08:00', 'fim' => '11:30', 'vagas' => 2]);
        });

        $this->como($this->c['admU'])->deleteJson('/api/ministerios/'.$this->c['louvor']->id)->assertNoContent();

        $this->assertSame(0, DB::table('postos')->count());
        $this->assertSame(0, DB::table('escalas')->count());
        $this->assertSame(0, DB::table('turnos')->count());
        $this->assertSame(4, DB::table('pessoas')->count());

        $this->como($this->c['marcosU'])->deleteJson('/api/ministerios/'.$this->c['midia']->id)->assertForbidden();
    }

    public function test_t81_registro_de_outra_igreja_responde_404(): void
    {
        $outra = $this->igreja('Igreja B');
        $deB = $this->naIgreja($outra, fn () => Ministerio::create(['nome' => 'Mídia B']));

        $this->como($this->c['admU'])->patchJson('/api/ministerios/'.$deB->id, ['nome' => 'Invadida'])->assertNotFound();
        $this->deleteJson('/api/ministerios/'.$deB->id)->assertNotFound();

        $this->assertSame('Mídia B', DB::table('ministerios')->where('id', $deB->id)->value('nome'));
    }

    // ---- postos ----

    public function test_t77_lider_de_outro_ministerio_e_voluntario_nao_mexem_nos_postos(): void
    {
        $louvor = $this->c['louvor']->id;

        // T-01: líder da Mídia não enxerga o Louvor.
        $this->como($this->c['anaU'])->getJson("/api/ministerios/{$louvor}/postos")->assertForbidden();

        // T-02: voluntária não cria posto.
        $this->como($this->c['helenaU'])->postJson("/api/ministerios/{$louvor}/postos", ['nome' => 'Som'])->assertForbidden();

        // Quem serve no ministério pode ver os postos.
        $this->getJson("/api/ministerios/{$louvor}/postos")->assertOk();

        // O Adm não gere postos de ministério alheio (a menos que também lidere).
        $this->como($this->c['admU'])->postJson("/api/ministerios/{$louvor}/postos", ['nome' => 'Som'])->assertForbidden();
    }

    public function test_t78_vagas_abaixo_de_um_viram_um(): void
    {
        $louvor = $this->c['louvor']->id;

        $this->como($this->c['marcosU'])
            ->postJson("/api/ministerios/{$louvor}/postos", ['nome' => 'Som', 'vagas' => 0])
            ->assertCreated()->assertJsonPath('posto.vagas', 1);

        $this->postJson("/api/ministerios/{$louvor}/postos", ['nome' => 'Palco', 'vagas' => -3])
            ->assertCreated()->assertJsonPath('posto.vagas', 1);

        $this->postJson("/api/ministerios/{$louvor}/postos", ['nome' => 'Câmera'])
            ->assertCreated()->assertJsonPath('posto.vagas', 1);

        $id = $this->postJson("/api/ministerios/{$louvor}/postos", ['nome' => 'Mesa', 'vagas' => 3])->json('posto.id');
        $this->patchJson("/api/postos/{$id}", ['nome' => 'Mesa', 'vagas' => 0])->assertOk()->assertJsonPath('posto.vagas', 1);
    }

    public function test_t79_excluir_posto_apaga_os_turnos_e_mantem_as_pessoas(): void
    {
        $posto = $this->naIgreja($this->c['igreja'], function () {
            $posto = $this->c['louvor']->postos()->create(['nome' => 'Som', 'vagas' => 2]);
            $posto->pessoas()->attach($this->c['helena']->id);
            $escala = Escala::create(['ministerio_id' => $this->c['louvor']->id, 'mes' => '2026-11']);
            Turno::create(['escala_id' => $escala->id, 'posto_id' => $posto->id, 'data' => '2026-11-01',
                'inicio' => '08:00', 'fim' => '11:30', 'vagas' => 2]);

            return $posto;
        });

        $this->como($this->c['anaU'])->deleteJson('/api/postos/'.$posto->id)->assertForbidden();
        $this->como($this->c['marcosU'])->deleteJson('/api/postos/'.$posto->id)->assertNoContent();

        $this->assertSame(0, DB::table('turnos')->count());
        $this->assertSame(0, DB::table('posto_pessoa')->count());
        $this->assertSame(4, DB::table('pessoas')->count());
    }

    // ---- equipe ----

    public function test_t82_sair_da_equipe_tira_postos_funcoes_e_bandas_so_daquele_ministerio(): void
    {
        $this->naIgreja($this->c['igreja'], function () {
            $helena = $this->c['helena'];
            foreach (['louvor', 'midia'] as $chave) {
                $ministerio = $this->c[$chave];
                $helena->postos()->attach($ministerio->postos()->create(['nome' => 'Posto '.$chave, 'vagas' => 1])->id);
                $helena->bandas()->attach(Banda::create(['ministerio_id' => $ministerio->id, 'nome' => 'Banda '.$chave])->id);
                $helena->funcoes()->attach(Funcao::create(['ministerio_id' => $ministerio->id, 'nome' => 'Função '.$chave])->id);
            }
        });

        $this->como($this->c['marcosU'])
            ->deleteJson('/api/ministerios/'.$this->c['louvor']->id.'/equipe/'.$this->c['helena']->id)
            ->assertNoContent();

        $helena = $this->naIgreja($this->c['igreja'], fn () => Pessoa::with(['ministerios', 'postos', 'bandas', 'funcoes'])->find($this->c['helena']->id));

        $this->assertSame(['Mídia'], $helena->ministerios->pluck('nome')->all());
        $this->assertSame(['Posto midia'], $helena->postos->pluck('nome')->all());
        $this->assertSame(['Banda midia'], $helena->bandas->pluck('nome')->all());
        $this->assertSame(['Função midia'], $helena->funcoes->pluck('nome')->all());
    }

    public function test_lider_inclui_na_equipe_quem_ja_existe_na_igreja(): void
    {
        $this->como($this->c['marcosU'])
            ->postJson('/api/ministerios/'.$this->c['louvor']->id.'/equipe', ['pessoa_id' => $this->c['ana']->id])
            ->assertCreated();

        $this->assertTrue(DB::table('ministerio_pessoa')
            ->where(['ministerio_id' => $this->c['louvor']->id, 'pessoa_id' => $this->c['ana']->id])->exists());

        // Líder da Mídia não mexe na equipe do Louvor.
        $this->como($this->c['anaU'])
            ->postJson('/api/ministerios/'.$this->c['louvor']->id.'/equipe', ['pessoa_id' => $this->c['ana']->id])
            ->assertForbidden();
    }

    // ---- pessoas ----

    public function test_t83_lider_cadastra_pessoa_so_na_propria_equipe(): void
    {
        $louvor = $this->c['louvor']->id;
        $midia = $this->c['midia']->id;

        $this->como($this->c['marcosU'])
            ->postJson('/api/pessoas', ['nome' => 'Pedro', 'email' => 'pedro@teste.com', 'ministerio_ids' => [$louvor]])
            ->assertCreated()
            ->assertJsonPath('pessoa.ministerios.0.nome', 'Louvor')
            ->assertJsonPath('pessoa.adm', false);

        $this->postJson('/api/pessoas', ['nome' => 'Paulo', 'ministerio_ids' => [$midia]])->assertForbidden();
        $this->postJson('/api/pessoas', ['nome' => 'Paulo'])->assertStatus(422)->assertJsonValidationErrors('ministerio_ids');
        $this->postJson('/api/pessoas', ['nome' => 'Paulo', 'ministerio_ids' => [$louvor], 'adm' => true])->assertForbidden();

        $this->como($this->c['helenaU'])->postJson('/api/pessoas', ['nome' => 'Paulo', 'ministerio_ids' => [$louvor]])->assertForbidden();
    }

    public function test_adm_cadastra_pessoa_sem_ministerio_e_pode_marcar_como_adm(): void
    {
        $this->como($this->c['admU'])
            ->postJson('/api/pessoas', ['nome' => 'Nova Adm', 'email' => 'nova@teste.com', 'adm' => true])
            ->assertCreated()
            ->assertJsonPath('pessoa.adm', true)
            ->assertJsonPath('pessoa.tem_login', false);
    }

    public function test_email_repetido_na_igreja_e_recusado(): void
    {
        $this->como($this->c['admU'])
            ->postJson('/api/pessoas', ['nome' => 'Outra Helena', 'email' => 'helena@teste.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_t84_lider_cuida_da_propria_equipe_mas_nao_faz_adm_nem_mexe_em_pessoa_alheia(): void
    {
        $helena = $this->c['helena']->id;

        $this->como($this->c['marcosU'])->patchJson("/api/pessoas/{$helena}", ['ativo' => false, 'telefone' => '11999990000'])
            ->assertOk()->assertJsonPath('pessoa.ativo', false);

        $this->patchJson("/api/pessoas/{$helena}", ['adm' => true])->assertForbidden();

        // A Ana lidera a Mídia, e a Helena também serve lá; já o Marcos não lidera ninguém da Mídia sozinha.
        $soDaMidia = $this->naIgreja($this->c['igreja'], function () {
            $p = Pessoa::create(['nome' => 'Só Mídia']);
            $p->ministerios()->attach($this->c['midia']->id);

            return $p;
        });

        $this->patchJson('/api/pessoas/'.$soDaMidia->id, ['telefone' => '1'])->assertForbidden();

        $this->como($this->c['helenaU'])->patchJson("/api/pessoas/{$helena}", ['telefone' => '1'])->assertForbidden();
    }

    public function test_t85_email_de_quem_ja_tem_login_nao_muda(): void
    {
        $this->como($this->c['admU'])
            ->patchJson('/api/pessoas/'.$this->c['helena']->id, ['email' => 'novo@teste.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        // Repetir o mesmo e-mail não é troca.
        $this->patchJson('/api/pessoas/'.$this->c['helena']->id, ['email' => 'helena@teste.com', 'nome' => 'Helena Costa'])
            ->assertOk()->assertJsonPath('pessoa.nome', 'Helena Costa');
    }

    public function test_t86_adm_exclui_a_pessoa_e_o_login_dela(): void
    {
        $this->como($this->c['admU'])->deleteJson('/api/pessoas/'.$this->c['helena']->id)->assertNoContent();

        $this->assertSame(0, DB::table('pessoas')->where('email', 'helena@teste.com')->count());
        $this->assertSame(0, DB::table('users')->where('email', 'helena@teste.com')->count());
        $this->assertSame(0, DB::table('ministerio_pessoa')->where('pessoa_id', $this->c['helena']->id)->count());

        $this->deleteJson('/api/pessoas/'.$this->c['adm']->id)->assertStatus(422);

        $this->como($this->c['marcosU'])->deleteJson('/api/pessoas/'.$this->c['ana']->id)->assertForbidden();
    }

    public function test_t87_listagens_dependem_do_papel(): void
    {
        $nomes = fn ($resposta, string $chave) => collect($resposta->json($chave))->pluck('nome')->sort()->values()->all();

        $adm = $this->como($this->c['admU'])->getJson('/api/pessoas')->assertOk();
        $this->assertSame(['Ana', 'Helena', 'Marcos', 'Valter'], $nomes($adm, 'pessoas'));

        $lider = $this->como($this->c['marcosU'])->getJson('/api/pessoas')->assertOk();
        $this->assertSame(['Helena', 'Marcos'], $nomes($lider, 'pessoas'));

        $this->como($this->c['helenaU'])->getJson('/api/pessoas')->assertForbidden();

        $admMin = $this->como($this->c['admU'])->getJson('/api/ministerios')->assertOk();
        $this->assertSame(['Louvor', 'Mídia'], $nomes($admMin, 'ministerios'));

        $liderMin = $this->como($this->c['marcosU'])->getJson('/api/ministerios')->assertOk();
        $this->assertSame(['Louvor'], $nomes($liderMin, 'ministerios'));

        $volMin = $this->como($this->c['helenaU'])->getJson('/api/ministerios')->assertOk();
        $this->assertSame(['Louvor', 'Mídia'], $nomes($volMin, 'ministerios'));
    }
}
