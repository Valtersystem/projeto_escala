<?php

namespace Tests\Feature\Auth;

use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaIgreja;
use Tests\TestCase;

/**
 * Login por e-mail e senha (D-16, RN-10).
 */
class LoginTest extends TestCase
{
    use MontaIgreja;
    use RefreshDatabase;

    public function test_t64_login_com_sucesso_devolve_token_e_a_pessoa(): void
    {
        $igreja = $this->igreja('Igreja A');
        $this->pessoaComLogin($igreja, 'ana@teste.com', ['nome' => 'Ana Lima']);

        $resposta = $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => self::SENHA]);

        $resposta->assertOk()
            ->assertJsonPath('pessoa.nome', 'Ana Lima')
            ->assertJsonPath('pessoa.igreja.nome', 'Igreja A')
            ->assertJsonStructure(['token', 'pessoa' => ['id', 'nome', 'email', 'adm', 'igreja', 'ministerios']]);

        $this->withToken($resposta->json('token'))
            ->getJson('/api/eu')
            ->assertOk()
            ->assertJsonPath('pessoa.email', 'ana@teste.com');
    }

    public function test_t65_senha_errada_e_email_inexistente_dao_a_mesma_mensagem(): void
    {
        $this->pessoaComLogin($this->igreja(), 'ana@teste.com');

        $senhaErrada = $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => 'outra-senha-1']);
        $semConta = $this->postJson('/api/login', ['email' => 'ninguem@teste.com', 'senha' => self::SENHA]);

        $senhaErrada->assertStatus(422)->assertJsonValidationErrors('email');
        $semConta->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame($senhaErrada->json('errors.email'), $semConta->json('errors.email'));
    }

    public function test_login_so_com_google_nao_entra_por_senha(): void
    {
        $this->pessoaComLogin($this->igreja(), 'ana@teste.com', [], null);

        $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => 'qualquer-senha-1'])
            ->assertStatus(422);
    }

    public function test_t66_pessoa_inativa_nao_entra(): void
    {
        [, $pessoa] = $this->pessoaComLogin($this->igreja(), 'ana@teste.com', ['ativo' => false]);

        $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => self::SENHA])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Este acesso está desativado. Fale com o líder.');

        $this->assertFalse($pessoa->ativo);
    }

    public function test_t67_token_de_quem_foi_desativado_perde_o_acesso(): void
    {
        [$user, $pessoa] = $this->pessoaComLogin($this->igreja(), 'ana@teste.com');
        $token = $user->createToken('app')->plainTextToken;

        Pessoa::withoutGlobalScopes()->whereKey($pessoa->id)->update(['ativo' => false]);

        $this->withToken($token)->getJson('/api/eu')->assertForbidden();
    }

    public function test_login_sem_pessoa_vinculada_nao_entra(): void
    {
        User::create(['name' => 'Solto', 'email' => 'solto@teste.com', 'password' => self::SENHA]);

        $this->postJson('/api/login', ['email' => 'solto@teste.com', 'senha' => self::SENHA])
            ->assertStatus(422);
    }

    public function test_t68_logout_invalida_o_token(): void
    {
        $this->pessoaComLogin($this->igreja(), 'ana@teste.com');
        $token = $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => self::SENHA])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        // O guard guarda o usuário da requisição anterior dentro do mesmo teste.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/eu')->assertUnauthorized();
    }

    public function test_t69_rota_protegida_exige_token(): void
    {
        $this->getJson('/api/eu')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_rota_protegida_responde_json_sem_cabecalho_accept(): void
    {
        $this->get('/api/eu')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_t70_depois_de_cinco_tentativas_o_login_e_limitado(): void
    {
        $this->pessoaComLogin($this->igreja(), 'ana@teste.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => 'errada-'.$i])->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => self::SENHA])->assertStatus(429);
    }

    public function test_eu_lista_os_ministerios_da_pessoa_e_se_ela_lidera(): void
    {
        $igreja = $this->igreja();
        [, $ana] = $this->pessoaComLogin($igreja, 'ana@teste.com', ['nome' => 'Ana', 'adm' => true]);

        $this->naIgreja($igreja, function () use ($ana) {
            $midia = Ministerio::create(['nome' => 'Mídia', 'lider_id' => $ana->id]);
            $louvor = Ministerio::create(['nome' => 'Louvor']);
            $midia->equipe()->attach($ana);
            $louvor->equipe()->attach($ana);
        });

        $token = $this->postJson('/api/login', ['email' => 'ana@teste.com', 'senha' => self::SENHA])->json('token');

        $resposta = $this->withToken($token)->getJson('/api/eu')->assertOk();

        $this->assertTrue($resposta->json('pessoa.adm'));
        $ministerios = collect($resposta->json('pessoa.ministerios'))->keyBy('nome');
        $this->assertTrue($ministerios['Mídia']['lider']);
        $this->assertFalse($ministerios['Louvor']['lider']);
    }
}
