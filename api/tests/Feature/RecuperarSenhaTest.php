<?php

namespace Tests\Feature;

use App\Notifications\RedefinirSenha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CenarioDaIgreja;
use Tests\TestCase;

/** RN-126 e RN-127: recuperação de senha por e-mail. */
class RecuperarSenhaTest extends TestCase
{
    use CenarioDaIgreja;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    public function test_t209_manda_o_link_para_quem_tem_login_ativo_com_o_endereco_do_app(): void
    {
        Notification::fake();

        $this->postJson('/api/esqueci-senha', ['email' => 'helena@teste.com'])->assertOk();

        Notification::assertSentTo($this->c['helenaU'], RedefinirSenha::class, function (RedefinirSenha $n) {
            $link = RedefinirSenha::link($n->token, 'helena@teste.com');

            $this->assertStringStartsWith(rtrim(config('app.frontend_url'), '/').'/redefinir-senha/', $link);
            $this->assertStringContainsString('email=helena%40teste.com', $link);

            return true;
        });
    }

    public function test_t210_a_resposta_e_igual_para_e_mail_que_nao_existe_e_nada_e_enviado(): void
    {
        Notification::fake();

        $existe = $this->postJson('/api/esqueci-senha', ['email' => 'helena@teste.com'])->assertOk()->json();
        $naoExiste = $this->postJson('/api/esqueci-senha', ['email' => 'ninguem@teste.com'])->assertOk()->json();

        $this->assertSame($existe, $naoExiste);
        Notification::assertSentTimes(RedefinirSenha::class, 1);
    }

    public function test_t211_pessoa_desativada_nao_recebe_o_link(): void
    {
        Notification::fake();
        $this->c['helena']->update(['ativo' => false]);

        $this->postJson('/api/esqueci-senha', ['email' => 'helena@teste.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_t212_o_e_mail_chega_em_portugues_com_o_botao_para_a_nova_senha(): void
    {
        $mail = (new RedefinirSenha('abc'))->toMail($this->c['helenaU']);

        $this->assertSame('Nova senha para o app de escalas', $mail->subject);
        $this->assertSame('Escolher nova senha', $mail->actionText);
        $this->assertStringContainsString('/redefinir-senha/abc?email=helena%40teste.com', $mail->actionUrl);
    }

    public function test_t213_com_o_token_certo_a_senha_muda_e_as_sessoes_antigas_caem(): void
    {
        $token = Password::createToken($this->c['helenaU']);
        $this->c['helenaU']->createToken('app');

        $this->postJson('/api/redefinir-senha', ['email' => 'helena@teste.com', 'token' => $token, 'senha' => 'nova-senha-123'])->assertOk();

        $this->assertSame(0, $this->c['helenaU']->tokens()->count());
        $this->postJson('/api/login', ['email' => 'helena@teste.com', 'senha' => 'nova-senha-123'])->assertOk();
        $this->postJson('/api/login', ['email' => 'helena@teste.com', 'senha' => self::SENHA])->assertStatus(422);
    }

    public function test_t214_token_errado_ou_ja_usado_e_senha_curta_sao_recusados(): void
    {
        $token = Password::createToken($this->c['helenaU']);

        $this->postJson('/api/redefinir-senha', ['email' => 'helena@teste.com', 'token' => 'errado', 'senha' => 'nova-senha-123'])
            ->assertStatus(422)->assertJsonPath('errors.token.0', 'Link inválido ou expirado. Peça um novo.');
        $this->postJson('/api/redefinir-senha', ['email' => 'helena@teste.com', 'token' => $token, 'senha' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors('senha');

        $this->postJson('/api/redefinir-senha', ['email' => 'helena@teste.com', 'token' => $token, 'senha' => 'nova-senha-123'])->assertOk();
        $this->postJson('/api/redefinir-senha', ['email' => 'helena@teste.com', 'token' => $token, 'senha' => 'outra-senha-123'])->assertStatus(422);
    }

    public function test_t215_o_token_de_uma_pessoa_nao_serve_para_outra(): void
    {
        $token = Password::createToken($this->c['helenaU']);

        $this->postJson('/api/redefinir-senha', ['email' => 'marcos@teste.com', 'token' => $token, 'senha' => 'nova-senha-123'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => 'marcos@teste.com', 'senha' => self::SENHA])->assertOk();
    }

    public function test_t216_o_pedido_de_link_tem_limite_por_ip(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/esqueci-senha', ['email' => "x{$i}@teste.com"])->assertOk();
        }

        $this->postJson('/api/esqueci-senha', ['email' => 'x6@teste.com'])->assertStatus(429);
    }
}
