<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\Igreja;
use App\Models\Ministerio;
use App\Models\PedidoEntrada;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/** D-15: o voluntário pede para entrar; o líder aprova ou recusa; o login só nasce na aprovação. */
class PedidoDeEntradaTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    /** @return array<string, mixed> */
    private function pedido(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Carla Dias', 'email' => 'Carla@Teste.com', 'telefone' => '11 98888-7777',
            'senha' => 'minha-senha-1', 'ministerio_id' => $this->c['louvor']->id,
        ], $extra);
    }

    private function codigo(): string
    {
        return $this->c['igreja']->codigo;
    }

    public function test_t182_a_igreja_ganha_um_codigo_unico_a_partir_do_nome(): void
    {
        $outra = Igreja::create(['nome' => 'Igreja A']);

        $this->assertSame('igreja-a', $this->c['igreja']->codigo);
        $this->assertSame('igreja-a-2', $outra->codigo);
    }

    public function test_t183_o_link_publico_mostra_a_igreja_e_so_os_ministerios_dela(): void
    {
        $outra = $this->igreja('Igreja B');
        $this->naIgreja($outra, fn () => Ministerio::create(['nome' => 'Intercessão']));

        $this->getJson("/api/entrada/{$this->codigo()}")
            ->assertOk()
            ->assertJsonPath('igreja.nome', 'Igreja A')
            ->assertJsonCount(2, 'ministerios')
            ->assertJsonMissing(['nome' => 'Intercessão']);

        $this->getJson('/api/entrada/nao-existe')->assertNotFound();
    }

    public function test_t184_o_pedido_nao_cria_login_nem_pessoa_e_avisa_o_lider(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();

        $this->assertDatabaseMissing('users', ['email' => 'carla@teste.com']);
        $this->assertDatabaseMissing('pessoas', ['email' => 'carla@teste.com']);
        $this->assertDatabaseHas('pedidos_entrada', ['email' => 'carla@teste.com', 'estado' => 'pendente']);
        $this->assertNotSame('minha-senha-1', PedidoEntrada::first()->senha_hash);
        $this->assertTrue(Aviso::where('pessoa_id', $this->c['marcos']->id)->where('texto', 'like', '%Carla pediu para entrar no Louvor%')->exists());

        $this->postJson('/api/login', ['email' => 'carla@teste.com', 'senha' => 'minha-senha-1'])->assertStatus(422);
    }

    public function test_t185_pedido_invalido_ou_repetido_e_recusado(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido(['senha' => '123']))->assertStatus(422)->assertJsonValidationErrors('senha');
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido(['ministerio_id' => 999]))->assertStatus(422)->assertJsonValidationErrors('ministerio_id');

        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertStatus(422)->assertJsonValidationErrors('email');

        // E-mail que já tem cadastro na igreja.
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido(['email' => 'helena@teste.com']))->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_t186_nao_da_para_pedir_um_ministerio_de_outra_igreja(): void
    {
        $outra = $this->igreja('Igreja B');
        $alheio = $this->naIgreja($outra, fn () => Ministerio::create(['nome' => 'Intercessão']));

        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido(['ministerio_id' => $alheio->id]))
            ->assertStatus(422)->assertJsonValidationErrors('ministerio_id');
    }

    public function test_t187_o_lider_ve_so_os_pedidos_do_ministerio_dele(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido(['email' => 'rita@teste.com', 'nome' => 'Rita', 'ministerio_id' => $this->c['midia']->id]))->assertCreated();

        $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/pedidos-de-entrada")
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('pedidos.0.nome', 'Carla Dias')
            ->assertJsonMissing(['senha_hash']);

        $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['midia']->id}/pedidos-de-entrada")->assertForbidden();
        $this->como($this->c['helenaU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/pedidos-de-entrada")->assertForbidden();
        $this->como($this->c['admU'])->getJson("/api/ministerios/{$this->c['midia']->id}/pedidos-de-entrada")->assertOk()->assertJsonPath('total', 1);
    }

    public function test_t188_aprovar_cria_o_login_a_pessoa_e_a_equipe_e_a_senha_escolhida_vale(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $pedido = PedidoEntrada::first();

        $this->como($this->c['marcosU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/aprovar")->assertCreated();

        $pessoa = Pessoa::where('email', 'carla@teste.com')->firstOrFail();
        $this->assertTrue($pessoa->serveEm($this->c['louvor']));
        $this->assertNotNull($pessoa->user_id);
        $this->assertSame('aprovado', $pedido->fresh()->estado);
        $this->assertSame($this->c['marcos']->id, $pedido->fresh()->resolvido_por);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'carla@teste.com', 'senha' => 'minha-senha-1'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_t189_recusar_nao_cria_nada_e_o_pedido_nao_pode_ser_resolvido_de_novo(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $pedido = PedidoEntrada::first();

        $this->como($this->c['marcosU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/recusar")->assertOk();

        $this->assertSame('recusado', $pedido->fresh()->estado);
        $this->assertSame(0, User::where('email', 'carla@teste.com')->count());
        $this->como($this->c['marcosU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/aprovar")->assertStatus(422);
        $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/pedidos-de-entrada")->assertJsonPath('total', 0);
    }

    public function test_t190_so_o_lider_do_ministerio_ou_o_adm_resolve_o_pedido(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $pedido = PedidoEntrada::first();

        $this->como($this->c['anaU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/aprovar")->assertForbidden();
        $this->como($this->c['helenaU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/recusar")->assertForbidden();
        $this->assertSame('pendente', $pedido->fresh()->estado);

        $this->como($this->c['admU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/aprovar")->assertCreated();
    }

    public function test_t191_o_pedido_de_outra_igreja_nao_aparece_nem_se_resolve(): void
    {
        $outra = $this->igreja('Igreja B');
        [$bU] = $this->pessoaComLogin($outra, 'adm@b.com', ['nome' => 'Admin B', 'adm' => true]);
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $pedido = PedidoEntrada::withoutGlobalScopes()->first();

        $this->como($bU)->postJson("/api/pedidos-de-entrada/{$pedido->id}/aprovar")->assertNotFound();
    }

    public function test_t192_aprovar_com_e_mail_que_ja_foi_cadastrado_por_outro_caminho_falha_sem_criar_nada(): void
    {
        $this->postJson("/api/entrada/{$this->codigo()}", $this->pedido())->assertCreated();
        $pedido = PedidoEntrada::first();
        $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => 'Carla Dias', 'email' => 'carla@teste.com']));

        $this->como($this->c['marcosU'])->postJson("/api/pedidos-de-entrada/{$pedido->id}/aprovar")->assertStatus(422);

        $this->assertSame('pendente', $pedido->fresh()->estado);
        $this->assertSame(0, User::where('email', 'carla@teste.com')->count());
    }
}
