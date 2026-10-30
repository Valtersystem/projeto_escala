<?php

namespace Tests\Feature;

use App\Models\Banda;
use App\Models\Funcao;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/**
 * O que o líder precisa para cuidar da equipe pelo app (RN-09, RN-11, RN-14, RN-15).
 */
class EquipeDoLiderTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function pessoa(string $nome, bool $ativo = true): Pessoa
    {
        return $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => $nome, 'ativo' => $ativo]));
    }

    public function test_t173_a_lista_traz_os_postos_funcoes_e_bandas_so_do_ministerio_pedido(): void
    {
        $som = $this->posto($this->c['louvor'], 'Som');
        $camera = $this->posto($this->c['midia'], 'Câmera');

        [$voz, $banda] = $this->naIgreja($this->c['igreja'], function () {
            $voz = Funcao::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Voz']);
            $edicao = Funcao::create(['ministerio_id' => $this->c['midia']->id, 'nome' => 'Edição']);
            $banda = Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 1', 'ordem' => 1]);

            $this->c['helena']->postos()->attach([$this->c['louvor']->postos()->first()->id, $this->c['midia']->postos()->first()->id]);
            $this->c['helena']->funcoes()->attach([$voz->id, $edicao->id]);
            $banda->membros()->attach($this->c['helena']->id);

            return [$voz, $banda];
        });

        $lista = $this->como($this->c['marcosU'])->getJson("/api/pessoas?ministerio_id={$this->c['louvor']->id}")->assertOk();
        $helena = collect($lista->json('pessoas'))->firstWhere('nome', 'Helena');

        // Só o que é do Louvor: o posto da Mídia e a função da Mídia não vazam para a tela do líder do Louvor.
        $this->assertSame([$som->id], $helena['posto_ids']);
        $this->assertSame([$voz->id], $helena['funcao_ids']);
        $this->assertSame([$banda->id], $helena['banda_ids']);
        $this->assertNotContains($camera->id, $helena['posto_ids']);
    }

    public function test_sem_o_ministerio_na_consulta_a_lista_continua_a_mesma(): void
    {
        $lista = $this->como($this->c['admU'])->getJson('/api/pessoas')->assertOk();

        $this->assertArrayNotHasKey('posto_ids', $lista->json('pessoas.0'));
    }

    public function test_t174_o_lider_define_os_postos_da_pessoa_sem_mexer_nos_de_outro_ministerio(): void
    {
        $som = $this->posto($this->c['louvor'], 'Som');
        $palco = $this->posto($this->c['louvor'], 'Palco');
        $camera = $this->posto($this->c['midia'], 'Câmera');
        $this->c['helena']->postos()->attach($camera->id);

        $url = "/api/ministerios/{$this->c['louvor']->id}/equipe/{$this->c['helena']->id}/postos";

        $this->como($this->c['marcosU'])->putJson($url, ['posto_ids' => [$som->id, $palco->id]])
            ->assertOk()->assertJsonCount(2, 'posto_ids');

        $this->putJson($url, ['posto_ids' => [$palco->id]])->assertOk()->assertJsonPath('posto_ids', [$palco->id]);

        $this->assertEqualsCanonicalizing(
            [$palco->id, $camera->id],
            DB::table('posto_pessoa')->where('pessoa_id', $this->c['helena']->id)->pluck('posto_id')->all(),
        );

        $this->putJson($url, ['posto_ids' => []])->assertOk();
        $this->assertSame([$camera->id], DB::table('posto_pessoa')->where('pessoa_id', $this->c['helena']->id)->pluck('posto_id')->all());
    }

    public function test_posto_de_outro_ministerio_ou_pessoa_fora_da_equipe_e_recusado(): void
    {
        $camera = $this->posto($this->c['midia'], 'Câmera');
        $som = $this->posto($this->c['louvor'], 'Som');

        $this->como($this->c['marcosU'])
            ->putJson("/api/ministerios/{$this->c['louvor']->id}/equipe/{$this->c['helena']->id}/postos", ['posto_ids' => [$camera->id]])
            ->assertStatus(422)->assertJsonValidationErrors('posto_ids');

        $this->putJson("/api/ministerios/{$this->c['louvor']->id}/equipe/{$this->c['ana']->id}/postos", ['posto_ids' => [$som->id]])
            ->assertStatus(422)->assertJsonValidationErrors('pessoa');
    }

    public function test_so_o_lider_do_ministerio_ou_o_adm_define_postos(): void
    {
        $som = $this->posto($this->c['louvor'], 'Som');
        $url = "/api/ministerios/{$this->c['louvor']->id}/equipe/{$this->c['helena']->id}/postos";

        $this->como($this->c['helenaU'])->putJson($url, ['posto_ids' => [$som->id]])->assertForbidden();
        $this->como($this->c['anaU'])->putJson($url, ['posto_ids' => [$som->id]])->assertForbidden();
        $this->como($this->c['admU'])->putJson($url, ['posto_ids' => [$som->id]])->assertOk();
    }

    public function test_t175_pessoas_de_fora_lista_quem_esta_ativo_e_ainda_nao_esta_na_equipe(): void
    {
        $this->pessoa('Bruno');
        $this->pessoa('Inativo', false);

        $lista = $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/pessoas-de-fora")->assertOk();

        $nomes = collect($lista->json('pessoas'))->pluck('nome')->all();
        $this->assertContains('Bruno', $nomes);
        $this->assertContains('Ana', $nomes);          // serve só na Mídia
        $this->assertNotContains('Marcos', $nomes);    // já está na equipe
        $this->assertNotContains('Helena', $nomes);
        $this->assertNotContains('Inativo', $nomes);

        // Mostra onde a pessoa já serve, e nada além do nome.
        $ana = collect($lista->json('pessoas'))->firstWhere('nome', 'Ana');
        $this->assertSame(['Mídia'], $ana['ministerios']);
        $this->assertSame(['id', 'nome', 'ministerios'], array_keys($ana));
    }

    public function test_pessoas_de_fora_nunca_mostra_gente_de_outra_igreja(): void
    {
        $outra = $this->igreja('Igreja B');
        $this->naIgreja($outra, fn () => Pessoa::create(['nome' => 'De outra igreja']));

        $nomes = collect($this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/pessoas-de-fora")->json('pessoas'))->pluck('nome');

        $this->assertNotContains('De outra igreja', $nomes);
    }

    public function test_so_o_lider_do_ministerio_ou_o_adm_ve_as_pessoas_de_fora(): void
    {
        $url = "/api/ministerios/{$this->c['louvor']->id}/pessoas-de-fora";

        $this->como($this->c['helenaU'])->getJson($url)->assertForbidden();
        $this->como($this->c['anaU'])->getJson($url)->assertForbidden();
        $this->como($this->c['admU'])->getJson($url)->assertOk();
    }
}
