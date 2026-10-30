<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/** RN-128 a RN-130: login com Google. */
class LoginGoogleTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    private const CLIENTE = 'cliente-123.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => self::CLIENTE]);
        $this->montarCenario();
    }

    /** @param array<string, mixed> $perfil */
    private function google(array $perfil = [], int $status = 200): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(array_merge([
            'aud' => self::CLIENTE, 'sub' => 'g-111', 'email' => 'helena@teste.com', 'email_verified' => 'true',
        ], $perfil), $status)]);
    }

    private function entrar()
    {
        return $this->postJson('/api/login/google', ['credencial' => 'jwt-do-google']);
    }

    public function test_t217_entra_com_o_google_quando_o_e_mail_e_de_um_login_existente_e_vincula(): void
    {
        $this->google();

        $this->entrar()->assertOk()->assertJsonPath('pessoa.nome', 'Helena')->assertJsonStructure(['token']);

        $this->assertSame('g-111', $this->c['helenaU']->fresh()->google_id);
        Http::assertSent(fn ($r) => $r['id_token'] === 'jwt-do-google');
    }

    public function test_t218_depois_de_vinculado_entra_pelo_google_mesmo_que_o_e_mail_do_google_mude(): void
    {
        $this->c['helenaU']->forceFill(['google_id' => 'g-111'])->save();
        $this->google(['email' => 'outro-email@gmail.com']);

        $this->entrar()->assertOk()->assertJsonPath('pessoa.nome', 'Helena');
    }

    public function test_t219_credencial_de_outro_aplicativo_e_recusada(): void
    {
        $this->google(['aud' => 'outro-cliente']);

        $this->entrar()->assertStatus(422)->assertJsonValidationErrors('credencial');

        $this->assertNull($this->c['helenaU']->fresh()->google_id);
    }

    public function test_t219b_e_mail_nao_verificado_pelo_google_e_recusado(): void
    {
        $this->google(['email_verified' => 'false']);

        $this->entrar()->assertStatus(422)->assertJsonValidationErrors('credencial');

        $this->assertNull($this->c['helenaU']->fresh()->google_id);
    }

    public function test_t220_credencial_que_o_google_nao_reconhece_e_recusada(): void
    {
        $this->google([], 400);

        $this->entrar()->assertStatus(422)->assertJsonValidationErrors('credencial');
    }

    public function test_t221_e_mail_que_ninguem_cadastrou_nao_ganha_acesso_nem_cria_nada(): void
    {
        $this->google(['email' => 'estranho@gmail.com', 'sub' => 'g-999']);

        $this->entrar()->assertStatus(422)->assertJsonPath('errors.credencial.0', fn ($m) => str_contains($m, 'não está cadastrado'));

        $this->assertSame(0, User::where('email', 'estranho@gmail.com')->count());
        $this->assertSame(0, Pessoa::withoutGlobalScopes()->where('email', 'estranho@gmail.com')->count());
    }

    public function test_t222_pessoa_convidada_so_com_e_mail_ganha_o_login_na_primeira_entrada_pelo_google(): void
    {
        $nova = $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => 'Carla', 'email' => 'carla@gmail.com']));
        $this->google(['email' => 'Carla@Gmail.com', 'sub' => 'g-carla']);

        $this->entrar()->assertOk()->assertJsonPath('pessoa.nome', 'Carla');

        $user = User::where('email', 'carla@gmail.com')->firstOrFail();
        $this->assertSame($user->id, $nova->fresh()->user_id);
        $this->assertSame('g-carla', $user->google_id);
        $this->assertNull($user->password);
    }

    public function test_t223_e_mail_cadastrado_em_duas_igrejas_sem_login_e_ambiguo_e_recusado(): void
    {
        $outra = $this->igreja('Igreja B');
        $this->naIgreja($this->c['igreja'], fn () => Pessoa::create(['nome' => 'Carla', 'email' => 'carla@gmail.com']));
        $this->naIgreja($outra, fn () => Pessoa::create(['nome' => 'Carla B', 'email' => 'carla@gmail.com']));
        $this->google(['email' => 'carla@gmail.com', 'sub' => 'g-carla']);

        $this->entrar()->assertStatus(422);

        $this->assertSame(0, User::where('email', 'carla@gmail.com')->count());
    }

    public function test_t224_pessoa_desativada_nao_entra_pelo_google_e_nao_recebe_token(): void
    {
        $this->c['helena']->update(['ativo' => false]);
        $this->google();

        $this->entrar()->assertStatus(422)->assertJsonMissing(['token']);
    }

    public function test_t225_login_com_google_de_login_ja_ligado_a_outra_conta_google_e_recusado(): void
    {
        $this->c['helenaU']->forceFill(['google_id' => 'g-outra'])->save();
        $this->google(['sub' => 'g-111']);

        $this->entrar()->assertStatus(422);

        $this->assertSame('g-outra', $this->c['helenaU']->fresh()->google_id);
    }

    public function test_t226_sem_client_id_configurado_o_login_com_google_nao_existe(): void
    {
        config(['services.google.client_id' => null]);
        Http::fake();

        $this->entrar()->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_t228_o_login_com_google_tem_limite_por_ip(): void
    {
        $this->google([], 400);

        foreach (range(1, 10) as $i) {
            $this->entrar()->assertStatus(422);
        }

        $this->entrar()->assertStatus(429);
    }

    public function test_t227_exige_a_credencial(): void
    {
        $this->postJson('/api/login/google', [])->assertStatus(422)->assertJsonValidationErrors('credencial');
    }
}
