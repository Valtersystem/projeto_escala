<?php

namespace Tests\Feature;

use App\Models\Convite;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/**
 * Convite por link para o primeiro acesso e para trocar a senha (RN-73 a RN-77).
 */
class ConviteTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    /** Pessoa da equipe do Louvor, cadastrada pelo líder, ainda sem login. */
    private function pessoaSemLogin(?string $email = 'pedro@teste.com'): Pessoa
    {
        return $this->naIgreja($this->c['igreja'], function () use ($email) {
            $pessoa = Pessoa::create(['nome' => 'Pedro', 'email' => $email]);
            $pessoa->ministerios()->attach($this->c['louvor']->id);

            return $pessoa;
        });
    }

    public function test_t88_o_lider_convida_e_a_pessoa_cria_a_senha_e_entra(): void
    {
        $pedro = $this->pessoaSemLogin();

        $convite = $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$pedro->id}/convite")
            ->assertCreated()
            ->assertJsonStructure(['token', 'link', 'expira_em']);

        $this->assertStringContainsString('/convite/'.$convite->json('token'), $convite->json('link'));

        $this->app['auth']->forgetGuards();

        $aceito = $this->postJson('/api/convites/aceitar', ['token' => $convite->json('token'), 'senha' => 'minha-senha-1'])
            ->assertOk()
            ->assertJsonPath('pessoa.nome', 'Pedro')
            ->assertJsonPath('pessoa.ministerios.0.nome', 'Louvor');

        $this->assertNotEmpty($aceito->json('token'));
        $this->assertNotNull(Pessoa::withoutGlobalScopes()->find($pedro->id)->user_id);

        $this->postJson('/api/login', ['email' => 'pedro@teste.com', 'senha' => 'minha-senha-1'])->assertOk();
    }

    public function test_t89_sem_email_nao_da_para_convidar(): void
    {
        $semEmail = $this->pessoaSemLogin(null);

        $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$semEmail->id}/convite")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cadastre o e-mail da pessoa antes de convidar.');
    }

    public function test_pessoa_inativa_nao_recebe_convite(): void
    {
        $pedro = $this->pessoaSemLogin();
        Pessoa::withoutGlobalScopes()->whereKey($pedro->id)->update(['ativo' => false]);

        $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$pedro->id}/convite")->assertStatus(422);
    }

    public function test_t90_convite_invalido_expirado_ou_ja_usado_e_recusado(): void
    {
        $pedro = $this->pessoaSemLogin();

        $this->postJson('/api/convites/aceitar', ['token' => 'nao-existe', 'senha' => 'minha-senha-1'])
            ->assertStatus(422)->assertJsonValidationErrors('token');

        $token = $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$pedro->id}/convite")->json('token');
        $this->app['auth']->forgetGuards();

        // Expirado.
        Convite::query()->update(['expira_em' => now()->subMinute()]);
        $this->postJson('/api/convites/aceitar', ['token' => $token, 'senha' => 'minha-senha-1'])
            ->assertStatus(422)->assertJsonValidationErrors('token');

        // Vigente, mas só vale uma vez.
        Convite::query()->update(['expira_em' => now()->addDay()]);
        $this->postJson('/api/convites/aceitar', ['token' => $token, 'senha' => 'minha-senha-1'])->assertOk();
        $this->postJson('/api/convites/aceitar', ['token' => $token, 'senha' => 'outra-senha-9'])
            ->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_t91_um_convite_novo_invalida_o_anterior(): void
    {
        $pedro = $this->pessoaSemLogin();

        $this->como($this->c['marcosU']);
        $primeiro = $this->postJson("/api/pessoas/{$pedro->id}/convite")->json('token');
        $segundo = $this->postJson("/api/pessoas/{$pedro->id}/convite")->json('token');

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/convites/aceitar', ['token' => $primeiro, 'senha' => 'minha-senha-1'])
            ->assertStatus(422);
        $this->postJson('/api/convites/aceitar', ['token' => $segundo, 'senha' => 'minha-senha-1'])
            ->assertOk();
    }

    public function test_t92_quem_ja_tem_login_troca_a_senha_e_perde_as_sessoes(): void
    {
        $helena = $this->c['helena'];
        $tokenAntigo = $this->c['helenaU']->createToken('app')->plainTextToken;

        $convite = $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$helena->id}/convite")->assertCreated()->json('token');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/convites/aceitar', ['token' => $convite, 'senha' => 'senha-nova-99'])->assertOk();

        $this->postJson('/api/login', ['email' => 'helena@teste.com', 'senha' => 'senha-nova-99'])->assertOk();
        $this->postJson('/api/login', ['email' => 'helena@teste.com', 'senha' => self::SENHA])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->withToken($tokenAntigo)->getJson('/api/eu')->assertUnauthorized();

        $this->assertSame(1, User::where('email', 'helena@teste.com')->count());
    }

    public function test_t93_so_quem_gere_a_pessoa_convida(): void
    {
        $pedro = $this->pessoaSemLogin();

        // Voluntária e líder de outro ministério não podem.
        $this->como($this->c['helenaU'])->postJson("/api/pessoas/{$pedro->id}/convite")->assertForbidden();
        $this->como($this->c['anaU'])->postJson("/api/pessoas/{$pedro->id}/convite")->assertForbidden();

        // O Adm pode.
        $this->como($this->c['admU'])->postJson("/api/pessoas/{$pedro->id}/convite")->assertCreated();
    }

    public function test_t94_senha_curta_e_recusada_e_o_convite_continua_valendo(): void
    {
        $pedro = $this->pessoaSemLogin();
        $token = $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$pedro->id}/convite")->json('token');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/convites/aceitar', ['token' => $token, 'senha' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors('senha');

        $this->postJson('/api/convites/aceitar', ['token' => $token, 'senha' => 'senha-boa-123'])->assertOk();
    }

    public function test_t95_o_token_nao_fica_guardado_em_claro(): void
    {
        $pedro = $this->pessoaSemLogin();
        $token = $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$pedro->id}/convite")->json('token');

        $guardado = DB::table('convites')->value('token_hash');

        $this->assertNotSame($token, $guardado);
        $this->assertSame(hash('sha256', $token), $guardado);
    }

    public function test_email_que_ja_tem_login_de_outra_pessoa_nao_vira_segundo_login(): void
    {
        // Existe um login solto com o e-mail do Pedro (não vinculado a ninguém).
        User::create(['name' => 'Solto', 'email' => 'pedro@teste.com', 'password' => self::SENHA]);
        $pedro = $this->pessoaSemLogin();

        $token = $this->como($this->c['marcosU'])->postJson("/api/pessoas/{$pedro->id}/convite")->json('token');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/convites/aceitar', ['token' => $token, 'senha' => 'minha-senha-1'])
            ->assertStatus(422)->assertJsonValidationErrors('token');

        $this->assertNull(Pessoa::withoutGlobalScopes()->find($pedro->id)->user_id);
    }
}
