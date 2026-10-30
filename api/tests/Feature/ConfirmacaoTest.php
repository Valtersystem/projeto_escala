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
 * Confirmação pelo líder, avisos e "minha escala" (RN-39 a RN-44, RN-59, RN-60, RN-23).
 */
class ConfirmacaoTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    private function confirmarPelaApi(int $turno, int $pessoa, array $funcoes = [])
    {
        return $this->como($this->c['marcosU'])->postJson("/api/turnos/{$turno}/confirmacoes", [
            'pessoa_id' => $pessoa, 'funcao_ids' => $funcoes,
        ]);
    }

    /** Pessoa extra na equipe do Louvor. */
    private function novaPessoa(string $nome, bool $ativo = true): Pessoa
    {
        return $this->naIgreja($this->c['igreja'], function () use ($nome, $ativo) {
            $pessoa = Pessoa::create(['nome' => $nome, 'ativo' => $ativo]);
            $pessoa->ministerios()->attach($this->c['louvor']->id);

            return $pessoa;
        });
    }

    private function funcao(string $nome, $ministerio = null): Funcao
    {
        return $this->naIgreja($this->c['igreja'], fn () => Funcao::create(['ministerio_id' => ($ministerio ?? $this->c['louvor'])->id, 'nome' => $nome]));
    }

    private function avisosDe(string $usuario): array
    {
        return collect($this->como($this->c[$usuario])->getJson('/api/avisos')->json('avisos'))->pluck('texto')->all();
    }

    // ---- confirmar ----

    public function test_t134_turno_lotado_bloqueia_a_confirmacao_ate_o_lider_aumentar_as_vagas(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']), vagas: 1);
        $outra = $this->novaPessoa('Bruno');

        $this->confirmarPelaApi($turno->id, $this->c['helena']->id)->assertCreated();

        $this->confirmarPelaApi($turno->id, $outra->id)
            ->assertStatus(422)->assertJsonPath('message', 'Turno lotado. Aumente as vagas para confirmar mais gente.');
        $this->assertSame(1, DB::table('confirmacoes')->count());

        $this->patchJson("/api/turnos/{$turno->id}", ['versao' => $turno->versao, 'vagas' => 2])->assertOk();
        $this->confirmarPelaApi($turno->id, $outra->id)->assertCreated();
        $this->assertSame(2, DB::table('confirmacoes')->count());
    }

    public function test_t135_recusa_duplicada_inativa_fora_da_equipe_e_escala_em_rascunho(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));

        $this->confirmarPelaApi($turno->id, $this->c['helena']->id)->assertCreated();
        $this->confirmarPelaApi($turno->id, $this->c['helena']->id)
            ->assertStatus(422)->assertJsonPath('message', 'Esta pessoa já está confirmada neste turno.');

        $this->confirmarPelaApi($turno->id, $this->novaPessoa('Inativo', false)->id)
            ->assertStatus(422)->assertJsonPath('message', 'Esta pessoa está inativa.');

        $this->confirmarPelaApi($turno->id, $this->c['ana']->id)
            ->assertStatus(422)->assertJsonPath('message', 'Esta pessoa não está na equipe do ministério.');

        $rascunho = $this->turno($this->escala($this->c['louvor'], 'rascunho', '2026-12'), $this->posto($this->c['louvor'], 'Palco'));
        $this->confirmarPelaApi($rascunho->id, $this->c['helena']->id)
            ->assertStatus(422)->assertJsonPath('message', 'Libere a escala antes de confirmar pessoas.');

        // Pessoa de outra igreja nem existe para esta.
        $outra = $this->igreja('Igreja B');
        $deB = $this->naIgreja($outra, fn () => Pessoa::create(['nome' => 'De outra igreja']));
        $this->confirmarPelaApi($turno->id, $deB->id)->assertStatus(422)->assertJsonValidationErrors('pessoa_id');
    }

    public function test_t136_choque_nao_bloqueia_mas_avisa_a_pessoa(): void
    {
        $naMidia = $this->turno($this->escala($this->c['midia'], 'publicada'), $this->posto($this->c['midia'], 'Câmera'), '2026-11-08', '09:00', '10:00');
        $this->confirmar($naMidia, $this->c['helena']->id);
        $noLouvor = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']), '2026-11-08', '08:00', '11:30');

        $resposta = $this->confirmarPelaApi($noLouvor->id, $this->c['helena']->id)->assertCreated();

        $this->assertSame([$naMidia->id], collect($resposta->json('choques'))->pluck('turno_id')->all());
        $this->assertSame(2, DB::table('confirmacoes')->where('pessoa_id', $this->c['helena']->id)->count());

        $avisos = $this->avisosDe('helenaU');
        $this->assertCount(1, $avisos);
        $this->assertStringContainsString('dois lugares no mesmo horário', $avisos[0]);
        $this->assertStringContainsString('Louvor · Som 08:00–11:30', $avisos[0]);
        $this->assertStringContainsString('Mídia · Câmera 09:00–10:00', $avisos[0]);
    }

    public function test_t137_funcoes_na_confirmacao_depois_e_so_do_ministerio(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));
        $voz = $this->funcao('Voz');
        $guitarra = $this->funcao('Guitarra');
        $camera = $this->funcao('Câmera', $this->c['midia']);

        $this->confirmarPelaApi($turno->id, $this->c['helena']->id, [$voz->id])
            ->assertCreated()->assertJsonPath('confirmacao.funcoes.0.nome', 'Voz');

        $this->putJson("/api/turnos/{$turno->id}/confirmacoes/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$voz->id, $guitarra->id]])
            ->assertOk()->assertJsonCount(2, 'confirmacao.funcoes');

        $this->putJson("/api/turnos/{$turno->id}/confirmacoes/{$this->c['helena']->id}/funcoes", ['funcao_ids' => [$camera->id]])
            ->assertStatus(422)->assertJsonValidationErrors('funcao_ids');

        $this->confirmarPelaApi($turno->id, $this->c['marcos']->id, [$camera->id])->assertStatus(422);
    }

    public function test_t138_tirar_da_escala_leva_as_funcoes_junto(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));
        $voz = $this->funcao('Voz');
        $this->confirmarPelaApi($turno->id, $this->c['helena']->id, [$voz->id]);
        $this->assertSame(1, DB::table('confirmacao_funcao')->count());

        $this->deleteJson("/api/turnos/{$turno->id}/confirmacoes/{$this->c['helena']->id}")->assertNoContent();

        $this->assertSame(0, DB::table('confirmacoes')->count());
        $this->assertSame(0, DB::table('confirmacao_funcao')->count());

        // Confirmar de novo depois de tirar funciona.
        $this->confirmarPelaApi($turno->id, $this->c['helena']->id)->assertCreated();
    }

    public function test_so_o_lider_do_ministerio_confirma_e_ve_a_folha_do_turno(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor']), $this->posto($this->c['louvor']));
        $corpo = ['pessoa_id' => $this->c['helena']->id];

        $this->como($this->c['helenaU'])->postJson("/api/turnos/{$turno->id}/confirmacoes", $corpo)->assertForbidden();
        $this->como($this->c['anaU'])->postJson("/api/turnos/{$turno->id}/confirmacoes", $corpo)->assertForbidden();
        $this->como($this->c['admU'])->postJson("/api/turnos/{$turno->id}/confirmacoes", $corpo)->assertForbidden();

        $this->como($this->c['helenaU'])->getJson("/api/turnos/{$turno->id}/pessoas")->assertForbidden();
        $this->como($this->c['helenaU'])->deleteJson("/api/turnos/{$turno->id}/confirmacoes/{$this->c['helena']->id}")->assertForbidden();
    }

    // ---- folha do turno ----

    public function test_t139_folha_do_turno_mostra_confirmados_disponiveis_carga_choque_e_fora_da_banda(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $banda = $this->naIgreja($this->c['igreja'], function () {
            $banda = Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 1', 'ordem' => 1]);
            $banda->membros()->attach($this->c['helena']->id);

            return $banda;
        });
        $escala = $this->escala($this->c['louvor']);
        $turno = $this->turno($escala, $posto, '2026-11-08', '08:00', '11:30', 3, $banda);

        $bruno = $this->novaPessoa('Bruno');          // fora da banda, com carga alta e choque
        $inativo = $this->novaPessoa('Inativo', false);
        $confirmado = $this->novaPessoa('Confirmado');

        foreach ([$this->c['helena'], $bruno, $inativo] as $pessoa) {
            $this->marcarDisponivel($turno, $pessoa->id);
        }
        $this->confirmar($turno, $confirmado->id);

        // O Bruno já tem 4 turnos confirmados em novembro (limite 3); um deles choca.
        $publicada = $this->escala($this->c['louvor'], 'publicada', '2026-12');
        foreach (['2026-11-08' => ['09:00', '10:00'], '2026-11-15' => ['08:00', '09:00'], '2026-11-22' => ['08:00', '09:00'], '2026-11-29' => ['08:00', '09:00']] as $data => [$ini, $fim]) {
            $this->confirmar($this->turno($publicada, $posto, $data, $ini, $fim), $bruno->id);
        }

        $folha = $this->como($this->c['marcosU'])->getJson("/api/turnos/{$turno->id}/pessoas")->assertOk();

        $this->assertSame(['Confirmado'], collect($folha->json('confirmados'))->pluck('nome')->all());
        $this->assertTrue($folha->json('confirmados.0.fora_da_banda'));

        // RN-65: o inativo não aparece.
        $this->assertSame(['Bruno', 'Helena'], collect($folha->json('disponiveis'))->pluck('nome')->all());

        $porNome = collect($folha->json('disponiveis'))->keyBy('nome');
        $this->assertSame(4, $porNome['Bruno']['turnos_no_mes']);
        $this->assertTrue($porNome['Bruno']['carga']);
        $this->assertTrue($porNome['Bruno']['fora_da_banda']);
        $this->assertCount(1, $porNome['Bruno']['choques']);

        $this->assertSame(0, $porNome['Helena']['turnos_no_mes']);
        $this->assertFalse($porNome['Helena']['carga']);
        $this->assertFalse($porNome['Helena']['fora_da_banda']);
        $this->assertSame([], $porNome['Helena']['choques']);
    }

    public function test_t140_a_carga_conta_so_o_mes_do_turno(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $turno = $this->turno($this->escala($this->c['louvor']), $posto, '2026-11-08');
        $this->marcarDisponivel($turno, $this->c['helena']->id);

        // Quatro turnos confirmados, dois em outubro e dois em dezembro: nenhum mês passa de 3.
        $publicada = $this->escala($this->c['louvor'], 'publicada', '2026-10');
        foreach (['2026-10-04', '2026-10-11', '2026-12-06', '2026-12-13'] as $data) {
            $this->confirmar($this->turno($publicada, $posto, $data), $this->c['helena']->id);
        }

        $folha = $this->como($this->c['marcosU'])->getJson("/api/turnos/{$turno->id}/pessoas")->assertOk();

        $this->assertSame(0, $folha->json('disponiveis.0.turnos_no_mes'));
        $this->assertFalse($folha->json('disponiveis.0.carga'));
    }

    // ---- avisos ----

    public function test_t141_publicar_avisa_cada_confirmado_com_data_horario_e_funcao(): void
    {
        $escala = $this->escala($this->c['louvor']);
        $turno = $this->turno($escala, $this->posto($this->c['louvor']), '2026-11-08', '09:00', '10:30', culto: 'Culto da Família');
        $voz = $this->funcao('Voz');
        $bruno = $this->novaPessoa('Bruno');

        $this->confirmarPelaApi($turno->id, $this->c['helena']->id, [$voz->id])->assertCreated();
        $this->confirmarPelaApi($turno->id, $bruno->id)->assertCreated();

        $this->postJson("/api/escalas/{$escala->id}/publicar")->assertOk();

        $this->assertSame(
            ['Escala publicada: Culto da Família · 08/11 às 09:00 — você fica em Voz.'],
            $this->avisosDe('helenaU'),
        );
        $this->assertSame(
            1,
            DB::table('avisos')->where('pessoa_id', $bruno->id)->where('texto', 'like', '%função a combinar com o líder.')->count(),
        );
    }

    public function test_t142_confirmar_depois_da_publicacao_tambem_avisa(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor'], 'publicada'), $this->posto($this->c['louvor']), '2026-11-08', '09:00', '10:30');

        $this->confirmarPelaApi($turno->id, $this->c['helena']->id)->assertCreated();

        $this->assertSame(['Escala publicada: 08/11 às 09:00 — função a combinar com o líder.'], $this->avisosDe('helenaU'));
    }

    public function test_t143_cada_pessoa_ve_so_os_proprios_avisos_e_marca_como_lidos(): void
    {
        $turno = $this->turno($this->escala($this->c['louvor'], 'publicada'), $this->posto($this->c['louvor']));
        $this->confirmarPelaApi($turno->id, $this->c['helena']->id);

        $lista = $this->como($this->c['helenaU'])->getJson('/api/avisos')->assertOk();
        $this->assertSame(1, $lista->json('nao_lidos'));
        $this->assertFalse($lista->json('avisos.0.lido'));

        $this->como($this->c['marcosU'])->getJson('/api/avisos')->assertJsonPath('nao_lidos', 0)->assertJsonCount(0, 'avisos');

        $this->como($this->c['helenaU'])->postJson('/api/avisos/lidos')->assertOk()->assertJsonPath('nao_lidos', 0);
        $depois = $this->getJson('/api/avisos')->assertOk();
        $this->assertSame(0, $depois->json('nao_lidos'));
        $this->assertTrue($depois->json('avisos.0.lido'));
    }

    // ---- minha escala ----

    public function test_t144_minha_escala_so_tem_turnos_confirmados_em_escala_publicada(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $voz = $this->funcao('Voz');

        $publicada = $this->turno($this->escala($this->c['louvor'], 'publicada'), $posto, '2026-11-08');
        $aberta = $this->turno($this->escala($this->c['louvor'], 'aberta', '2026-12'), $posto, '2026-12-06');
        $semMim = $this->turno($this->escala($this->c['louvor'], 'publicada', '2027-01'), $posto, '2027-01-10');

        $this->confirmar($publicada, $this->c['helena']->id);
        $this->confirmar($aberta, $this->c['helena']->id);
        $this->confirmar($semMim, $this->c['marcos']->id);
        DB::table('confirmacao_funcao')->insert([
            'confirmacao_id' => DB::table('confirmacoes')->where('turno_id', $publicada->id)->value('id'), 'funcao_id' => $voz->id,
        ]);

        $resposta = $this->como($this->c['helenaU'])->getJson('/api/minha-escala')->assertOk();

        $this->assertSame([$publicada->id], collect($resposta->json('turnos'))->pluck('id')->all());
        $this->assertSame('Voz', $resposta->json('turnos.0.funcoes.0.nome'));
        $this->assertSame('Louvor', $resposta->json('turnos.0.ministerio'));
        $this->assertArrayNotHasKey('vagas', $resposta->json('turnos.0'));

        $this->getJson('/api/minha-escala?mes=2026-12')->assertOk()->assertJsonCount(0, 'turnos');
        $this->getJson('/api/minha-escala?mes=2026-13')->assertStatus(422);
    }

    public function test_t145_minha_escala_marca_o_choque_entre_bandas_do_mesmo_ministerio(): void
    {
        $posto = $this->posto($this->c['louvor']);
        [$b1, $b2] = $this->naIgreja($this->c['igreja'], fn () => [
            Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 1', 'ordem' => 1]),
            Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 2', 'ordem' => 2]),
        ]);
        $escala = $this->escala($this->c['louvor'], 'publicada');

        $primeiro = $this->turno($escala, $posto, '2026-11-08', '08:00', '11:30', banda: $b1);
        $segundo = $this->turno($escala, $posto, '2026-11-08', '10:00', '12:00', banda: $b2);
        $livre = $this->turno($escala, $posto, '2026-11-15', '08:00', '11:30', banda: $b1);

        foreach ([$primeiro, $segundo, $livre] as $turno) {
            $this->confirmar($turno, $this->c['helena']->id);
        }

        $turnos = collect($this->como($this->c['helenaU'])->getJson('/api/minha-escala')->assertOk()->json('turnos'))->keyBy('id');

        $this->assertSame([$segundo->id], collect($turnos[$primeiro->id]['choque'])->pluck('turno_id')->all());
        $this->assertSame([$primeiro->id], collect($turnos[$segundo->id]['choque'])->pluck('turno_id')->all());
        $this->assertSame([], $turnos[$livre->id]['choque']);
        $this->assertSame('Banda 1', $turnos[$primeiro->id]['banda']['nome']);
    }

    public function test_t229_minha_escala_traz_a_parte_os_dias_marcados_que_o_lider_ainda_nao_confirmou(): void
    {
        $posto = $this->posto($this->c['louvor']);
        $aberta = $this->escala($this->c['louvor'], 'aberta', '2026-12');

        $marcado = $this->turno($aberta, $posto, '2026-12-06');
        $jaConfirmado = $this->turno($aberta, $posto, '2026-12-13');
        $naoMarcado = $this->turno($aberta, $posto, '2026-12-20');
        $deOutra = $this->turno($aberta, $posto, '2026-12-27');
        $publicado = $this->turno($this->escala($this->c['louvor'], 'publicada'), $posto, '2026-11-08');
        $this->confirmar($jaConfirmado, $this->c['helena']->id);
        $this->confirmar($publicado, $this->c['helena']->id);

        foreach ([[$marcado, 'helena'], [$jaConfirmado, 'helena'], [$deOutra, 'marcos'], [$publicado, 'helena']] as [$turno, $quem]) {
            DB::table('disponibilidades')->insert(['turno_id' => $turno->id, 'pessoa_id' => $this->c[$quem]->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $resposta = $this->como($this->c['helenaU'])->getJson('/api/minha-escala')->assertOk();

        // Só o marcado e ainda sem confirmação; o confirmado e o de escala publicada não entram aqui.
        $this->assertSame([$marcado->id], collect($resposta->json('aguardando'))->pluck('id')->all());
        $this->assertSame('Louvor', $resposta->json('aguardando.0.ministerio'));
        $this->assertArrayNotHasKey('vagas', $resposta->json('aguardando.0'));
        $this->assertNotContains($naoMarcado->id, collect($resposta->json('aguardando'))->pluck('id')->all());
        // RN-23 continua valendo: o dia só aguardando não vira turno.
        $this->assertSame([$publicado->id], collect($resposta->json('turnos'))->pluck('id')->all());

        $this->getJson('/api/minha-escala?mes=2026-11')->assertOk()->assertJsonCount(0, 'aguardando');
    }
}
