<?php

namespace Tests\Feature;

use App\Models\Escala;
use App\Models\Igreja;
use App\Models\Pessoa;
use App\Models\Turno;
use App\Support\IgrejaAtual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comando que monta a igreja de exemplo para testar o app com tudo preenchido.
 */
class DadosDeExemploCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'senha-de-teste-1';

    protected function setUp(): void
    {
        parent::setUp();

        // Data fixa: o comando monta o mês atual, o próximo e o seguinte.
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(9, 0));

        $this->artisan('escala:dados-de-exemplo', ['--senha' => self::SENHA])->assertSuccessful();
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'senha' => self::SENHA])->assertOk()->json('token');
    }

    private function naIgreja(callable $fazer): mixed
    {
        $atual = app(IgrejaAtual::class);
        $atual->definir(Igreja::where('nome', 'Igreja Exemplo')->value('id'));

        try {
            return $fazer();
        } finally {
            $atual->definir(null);
        }
    }

    public function test_t166_os_logins_de_exemplo_entram_com_o_papel_certo(): void
    {
        $adm = $this->postJson('/api/login', ['email' => 'adm@exemplo.test', 'senha' => self::SENHA])->assertOk();
        $this->assertTrue($adm->json('pessoa.adm'));

        $marcos = $this->postJson('/api/login', ['email' => 'marcos@exemplo.test', 'senha' => self::SENHA])->assertOk();
        $this->assertSame(['Louvor' => true], collect($marcos->json('pessoa.ministerios'))->where('lider', true)->pluck('lider', 'nome')->all());

        $helena = $this->postJson('/api/login', ['email' => 'helena@exemplo.test', 'senha' => self::SENHA])->assertOk();
        $this->assertSame(['Louvor', 'Mídia'], collect($helena->json('pessoa.ministerios'))->pluck('nome')->sort()->values()->all());
    }

    public function test_t167_o_louvor_tem_tres_meses_em_tres_estados_com_o_rodizio_continuo(): void
    {
        [$escalas, $bandas] = $this->naIgreja(function () {
            $escalas = Escala::whereHas('ministerio', fn ($m) => $m->where('nome', 'Louvor'))->orderBy('mes')->get();
            $bandas = Turno::whereIn('escala_id', $escalas->pluck('id'))->with('banda:id,nome')->orderBy('data')->orderBy('inicio')->get()
                ->map(fn ($t) => $t->banda->nome);

            return [$escalas, $bandas];
        });

        $this->assertSame(['2026-11', '2026-12', '2027-01'], $escalas->pluck('mes')->all());
        $this->assertSame(['publicada', 'aberta', 'rascunho'], $escalas->map(fn ($e) => $e->status->value)->all());

        // Novembro de 2026: 5 domingos × manhã e noite; a sequência é contínua entre os meses.
        $sequencia = $bandas->take(10)->all();
        $this->assertSame(['Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2', 'Banda 3', 'Banda 1', 'Banda 2', 'Banda 3', 'Banda 1'], $sequencia);
        $this->assertSame('Banda 2', $bandas[10]); // dezembro começa de onde novembro parou
    }

    public function test_t168_o_mes_atual_esta_publicado_com_gente_confirmada_e_o_proximo_com_disponibilidade(): void
    {
        $this->naIgreja(function () {
            $atual = Escala::where('mes', '2026-11')->whereHas('ministerio', fn ($m) => $m->where('nome', 'Louvor'))->first();
            $proximo = Escala::where('mes', '2026-12')->whereHas('ministerio', fn ($m) => $m->where('nome', 'Louvor'))->first();

            foreach ($atual->turnos as $turno) {
                $this->assertCount(2, $turno->confirmacoes, 'Cada turno publicado tem duas pessoas confirmadas.');
            }

            foreach ($proximo->turnos as $turno) {
                $this->assertGreaterThan(0, $turno->disponibilidades()->count());
                $this->assertCount(0, $turno->confirmacoes);
            }

            // O prazo da escala aberta ainda não passou.
            $this->assertGreaterThanOrEqual('2026-11-24', $proximo->prazo->toDateString());
        });
    }

    public function test_t169_o_rafael_ve_o_choque_pronto_ao_marcar_louvor_e_midia_no_mesmo_horario(): void
    {
        $token = $this->login('rafael@exemplo.test');
        $this->withToken($token);

        $escalaDoLouvor = $this->naIgreja(fn () => Escala::where('mes', '2026-12')->whereHas('ministerio', fn ($m) => $m->where('nome', 'Louvor'))->value('id'));
        $escalaDaMidia = $this->naIgreja(fn () => Escala::where('mes', '2026-12')->whereHas('ministerio', fn ($m) => $m->where('nome', 'Mídia'))->value('id'));

        $turnosDoLouvor = $this->getJson("/api/escalas/{$escalaDoLouvor}/disponibilidade")->assertOk()->json('turnos');
        $meu = collect($turnosDoLouvor)->first(fn ($t) => $t['marquei'] && $t['inicio'] === '08:00');
        $this->assertNotNull($meu, 'O Rafael já marcou o culto da manhã da banda dele.');

        $namidia = collect($this->getJson("/api/escalas/{$escalaDaMidia}/disponibilidade")->assertOk()->json('turnos'))
            ->first(fn ($t) => $t['data'] === $meu['data']);

        $this->putJson("/api/turnos/{$namidia['id']}/disponibilidade", ['marcado' => true])
            ->assertOk()
            ->assertJsonPath('choques.0.ministerio', 'Louvor')
            ->assertJsonPath('choques.0.data', $meu['data']);
    }

    public function test_so_ha_logins_para_quem_precisa_e_os_demais_aguardam_convite(): void
    {
        $this->naIgreja(function () {
            $this->assertSame(7, Pessoa::whereNotNull('user_id')->count());
            $this->assertSame(14, Pessoa::whereNull('user_id')->count());
        });

        $this->assertSame(7, DB::table('users')->count());
    }

    public function test_nao_roda_duas_vezes_nem_com_senha_curta(): void
    {
        $this->artisan('escala:dados-de-exemplo', ['--senha' => self::SENHA])->assertFailed();
        $this->assertSame(1, Igreja::where('nome', 'Igreja Exemplo')->count());
    }

    public function test_em_producao_so_com_force(): void
    {
        DB::table('igrejas')->delete();
        $this->app['env'] = 'production';

        $this->artisan('escala:dados-de-exemplo', ['--senha' => self::SENHA])->assertFailed();
        $this->assertSame(0, Igreja::count());

        $this->artisan('escala:dados-de-exemplo', ['--senha' => 'curta'])->assertFailed();
    }
}
