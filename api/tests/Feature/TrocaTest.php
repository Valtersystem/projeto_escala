<?php

namespace Tests\Feature;

use App\Models\Banda;
use App\Models\Funcao;
use App\Models\Pessoa;
use App\Models\Troca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDaIgreja;
use Tests\Concerns\MontaEscalas;
use Tests\TestCase;

/**
 * Pedido de substituição (RN-51 a RN-58).
 */
class TrocaTest extends TestCase
{
    use CenarioDaIgreja;
    use MontaEscalas;
    use RefreshDatabase;

    private $posto;

    private $turno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();

        // Escala publicada do Louvor, com a Helena confirmada no turno.
        $this->posto = $this->posto($this->c['louvor'], 'Som', 2);
        $this->turno = $this->turno($this->escala($this->c['louvor'], 'publicada'), $this->posto, '2026-11-08', '08:00', '11:30');
        $this->confirmar($this->turno, $this->c['helena']->id);
    }

    private function pessoaDaEquipe(string $nome, bool $ativo = true): Pessoa
    {
        return $this->naIgreja($this->c['igreja'], function () use ($nome, $ativo) {
            $pessoa = Pessoa::create(['nome' => $nome, 'ativo' => $ativo]);
            $pessoa->ministerios()->attach($this->c['louvor']->id);

            return $pessoa;
        });
    }

    private function pedir(string $motivo = 'Viagem', ?string $obs = null)
    {
        return $this->como($this->c['helenaU'])->postJson("/api/turnos/{$this->turno->id}/trocas", [
            'motivo' => $motivo, 'observacao' => $obs,
        ]);
    }

    private function troca(): Troca
    {
        $id = $this->pedir()->assertCreated()->json('troca.id');

        return Troca::withoutGlobalScopes()->findOrFail($id);
    }

    private function avisos(string $usuario): array
    {
        return collect($this->como($this->c[$usuario])->getJson('/api/avisos')->json('avisos'))->pluck('texto')->all();
    }

    // ---- pedir e cancelar ----

    public function test_t146_quem_esta_confirmado_pede_substituicao_continua_no_turno_e_o_lider_e_avisado(): void
    {
        $this->pedir('Viagem', 'Viajo no sábado e só volto na segunda.')
            ->assertCreated()
            ->assertJsonPath('troca.estado', 'aberto')
            ->assertJsonPath('troca.motivo', 'Viagem')
            ->assertJsonPath('troca.pessoa.nome', 'Helena');

        $this->assertSame(1, DB::table('confirmacoes')->where('turno_id', $this->turno->id)->count());
        $this->assertSame(['Helena pediu substituição no Som · 08/11.'], $this->avisos('marcosU'));
    }

    public function test_t147_motivo_fora_da_lista_e_recusado_e_a_observacao_e_opcional(): void
    {
        $this->pedir('Preguiça')->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->como($this->c['helenaU'])->postJson("/api/turnos/{$this->turno->id}/trocas", [])->assertStatus(422);
        $this->pedir('Outro motivo')->assertCreated();
    }

    public function test_t148_so_pede_quem_esta_confirmado_em_escala_publicada(): void
    {
        // Quem não está confirmado no turno (o líder serve na equipe, mas não está nesse turno).
        $this->como($this->c['marcosU'])
            ->postJson("/api/turnos/{$this->turno->id}/trocas", ['motivo' => 'Viagem'])
            ->assertStatus(422)->assertJsonPath('message', 'Você não está confirmado neste turno.');

        // Escala só aberta: ainda não é "a escala dele".
        DB::table('escalas')->where('id', $this->turno->escala_id)->update(['status' => 'aberta']);
        $this->pedir()->assertStatus(422)->assertJsonPath('message', 'Só dá para pedir substituição em escala publicada.');

        $this->assertSame(0, DB::table('trocas')->count());
    }

    public function test_t149_um_pedido_aberto_por_pessoa_e_turno_e_dá_para_pedir_de_novo_depois_de_cancelar(): void
    {
        $id = $this->pedir()->assertCreated()->json('troca.id');

        $this->pedir()->assertStatus(422)->assertJsonPath('message', 'Você já tem um pedido aberto nesse turno.');
        $this->assertSame(1, DB::table('trocas')->count());

        $this->deleteJson("/api/trocas/{$id}")->assertOk();
        $this->pedir()->assertCreated();
        $this->assertSame(2, DB::table('trocas')->count());
    }

    public function test_t150_cancelar_avisa_o_lider_e_so_o_dono_cancela(): void
    {
        $troca = $this->troca();

        $this->como($this->c['marcosU'])->deleteJson("/api/trocas/{$troca->id}")->assertForbidden();
        $this->como($this->c['anaU'])->deleteJson("/api/trocas/{$troca->id}")->assertForbidden();

        $this->como($this->c['helenaU'])->deleteJson("/api/trocas/{$troca->id}")
            ->assertOk()->assertJsonPath('troca.estado', 'cancelado');

        $this->assertSame(1, DB::table('confirmacoes')->count());
        $this->assertContains('Helena cancelou o pedido de substituição.', $this->avisos('marcosU'));

        $this->como($this->c['helenaU'])->deleteJson("/api/trocas/{$troca->id}")
            ->assertStatus(422)->assertJsonPath('message', 'Este pedido já foi resolvido.');
    }

    // ---- lista do líder ----

    public function test_t151_o_lider_ve_os_pedidos_abertos_e_quantos_sao(): void
    {
        $troca = $this->troca();
        $this->marcarDisponivel($this->turno, $this->pessoaDaEquipe('Bruno')->id);

        $lista = $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/trocas")->assertOk();

        $this->assertSame(1, $lista->json('total_abertas'));
        $this->assertSame([$troca->id], collect($lista->json('trocas'))->pluck('id')->all());
        $this->assertSame(1, $lista->json('trocas.0.marcaram'));

        $this->como($this->c['helenaU'])->deleteJson("/api/trocas/{$troca->id}")->assertOk();
        $this->como($this->c['marcosU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/trocas")->assertJsonPath('total_abertas', 0)->assertJsonCount(0, 'trocas');
        $this->getJson("/api/ministerios/{$this->c['louvor']->id}/trocas?estado=cancelado")->assertJsonCount(1, 'trocas');

        // Só o líder do ministério.
        $this->como($this->c['helenaU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/trocas")->assertForbidden();
        $this->como($this->c['anaU'])->getJson("/api/ministerios/{$this->c['louvor']->id}/trocas")->assertForbidden();
    }

    // ---- candidatos ----

    public function test_t152_candidatos_so_quem_pode_entrar_e_na_ordem_certa(): void
    {
        $troca = $this->troca();

        $bruno = $this->pessoaDaEquipe('Bruno');        // marcou disponibilidade
        $carla = $this->pessoaDaEquipe('Carla');        // 1 turno no total
        $fabio = $this->pessoaDaEquipe('Fábio');        // nenhum turno
        $inativo = $this->pessoaDaEquipe('Inativo', false);
        $soPalco = $this->pessoaDaEquipe('Só Palco');   // só serve em outro posto do Louvor
        $jaConfirmado = $this->pessoaDaEquipe('Já confirmado');

        $palco = $this->posto($this->c['louvor'], 'Palco');
        $soPalco->postos()->attach($palco->id);

        $this->marcarDisponivel($this->turno, $bruno->id);
        $this->marcarDisponivel($this->turno, $inativo->id);
        $this->confirmar($this->turno, $jaConfirmado->id);
        $outro = $this->turno($this->escala($this->c['louvor'], 'publicada', '2026-12'), $this->posto($this->c['louvor'], 'Mesa'), '2026-12-06');
        $this->confirmar($outro, $carla->id);

        $lista = $this->como($this->c['marcosU'])->getJson("/api/trocas/{$troca->id}/candidatos")->assertOk();

        // Marcou primeiro (Bruno), depois menos turnos (Fábio e o líder Marcos 0, Carla 1).
        // Ficam de fora: a Helena (quem sai), o inativo, o que só serve em outro posto, quem já está no turno e a Ana (outra equipe).
        $this->assertSame(['Bruno', 'Fábio', 'Marcos', 'Carla'], collect($lista->json('candidatos'))->pluck('nome')->all());
        $this->assertSame([true, false, false, false], collect($lista->json('candidatos'))->pluck('marcou')->all());
    }

    public function test_t153_quem_e_da_banda_do_turno_vem_antes_de_quem_so_marcou(): void
    {
        $banda = $this->naIgreja($this->c['igreja'], fn () => Banda::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Banda 1', 'ordem' => 1]));
        DB::table('turnos')->where('id', $this->turno->id)->update(['banda_id' => $banda->id]);

        $deFora = $this->pessoaDaEquipe('De fora');
        $daBanda = $this->pessoaDaEquipe('Da banda');
        $banda->membros()->attach($daBanda->id);
        $this->marcarDisponivel($this->turno, $deFora->id);

        $troca = $this->troca();
        $lista = $this->como($this->c['marcosU'])->getJson("/api/trocas/{$troca->id}/candidatos")->assertOk();

        // Da banda vem antes de quem só marcou; o líder, que não é da banda nem marcou, vem por último.
        $this->assertSame(['Da banda', 'De fora', 'Marcos'], collect($lista->json('candidatos'))->pluck('nome')->all());
        $this->assertSame([false, true, true], collect($lista->json('candidatos'))->pluck('fora_da_banda')->all());
    }

    public function test_t154_candidato_com_choque_aparece_com_o_aviso(): void
    {
        $troca = $this->troca();
        $carla = $this->pessoaDaEquipe('Carla');
        $naMidia = $this->turno($this->escala($this->c['midia'], 'publicada'), $this->posto($this->c['midia'], 'Câmera'), '2026-11-08', '09:00', '10:00');
        $this->confirmar($naMidia, $carla->id);

        $candidatos = collect($this->como($this->c['marcosU'])->getJson("/api/trocas/{$troca->id}/candidatos")->json('candidatos'));
        $candidato = $candidatos->firstWhere('nome', 'Carla');

        $this->assertSame([], $candidatos->firstWhere('nome', 'Marcos')['choques']);
        $this->assertSame([$naMidia->id], collect($candidato['choques'])->pluck('turno_id')->all());
    }

    // ---- resolver ----

    public function test_t155_manter_deixa_a_pessoa_no_turno_e_avisa_ela(): void
    {
        $troca = $this->troca();

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/manter")
            ->assertOk()->assertJsonPath('troca.estado', 'resolvido')->assertJsonPath('troca.resolucao', 'mantido');

        $this->assertSame(1, DB::table('confirmacoes')->where('pessoa_id', $this->c['helena']->id)->count());
        $this->assertSame(['O líder pediu que você continue no Som · 08/11. Fale com ele se não der mesmo.'], $this->avisos('helenaU'));

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/manter")
            ->assertStatus(422)->assertJsonPath('message', 'Este pedido já foi resolvido.');
    }

    public function test_t156_substituir_passa_a_vaga_e_as_funcoes_e_avisa_os_dois(): void
    {
        $voz = $this->naIgreja($this->c['igreja'], fn () => Funcao::create(['ministerio_id' => $this->c['louvor']->id, 'nome' => 'Voz']));
        $confirmacao = DB::table('confirmacoes')->where('pessoa_id', $this->c['helena']->id)->value('id');
        DB::table('confirmacao_funcao')->insert(['confirmacao_id' => $confirmacao, 'funcao_id' => $voz->id]);

        $bruno = $this->pessoaDaEquipe('Bruno');
        $troca = $this->troca();

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/substituir", ['pessoa_id' => $bruno->id])
            ->assertOk()->assertJsonPath('troca.resolucao', 'substituido')->assertJsonPath('troca.substituto_id', $bruno->id);

        $confirmados = DB::table('confirmacoes')->where('turno_id', $this->turno->id)->pluck('pessoa_id')->all();
        $this->assertSame([$bruno->id], $confirmados);

        // O Bruno herda a função da Helena.
        $novaConfirmacao = DB::table('confirmacoes')->where('pessoa_id', $bruno->id)->value('id');
        $this->assertSame([$voz->id], DB::table('confirmacao_funcao')->where('confirmacao_id', $novaConfirmacao)->pluck('funcao_id')->all());
        $this->assertSame(1, DB::table('confirmacao_funcao')->count());

        $this->assertSame(['Bruno assumiu o seu turno no Som · 08/11. Você está liberado.'], $this->avisos('helenaU'));

        $avisoDoBruno = DB::table('avisos')->where('pessoa_id', $bruno->id)->value('texto');
        $this->assertSame('Você entrou no lugar de Helena no Som · 08/11 (08:00 às 11:30) — função: Voz.', $avisoDoBruno);
    }

    public function test_t157_o_lider_pode_escolher_quem_tem_choque_mas_nao_quem_nao_e_candidato(): void
    {
        $carla = $this->pessoaDaEquipe('Carla');
        $naMidia = $this->turno($this->escala($this->c['midia'], 'publicada'), $this->posto($this->c['midia'], 'Câmera'), '2026-11-08', '09:00', '10:00');
        $this->confirmar($naMidia, $carla->id);
        $troca = $this->troca();

        $this->como($this->c['marcosU']);
        $this->postJson("/api/trocas/{$troca->id}/substituir", ['pessoa_id' => $this->c['ana']->id])
            ->assertStatus(422)->assertJsonPath('message', 'Esta pessoa não pode assumir este turno.');
        $this->postJson("/api/trocas/{$troca->id}/substituir", ['pessoa_id' => $this->pessoaDaEquipe('Inativo', false)->id])->assertStatus(422);
        $this->postJson("/api/trocas/{$troca->id}/substituir", [])->assertStatus(422);

        // Carla tem choque com a Mídia, e mesmo assim pode ser escolhida.
        $this->postJson("/api/trocas/{$troca->id}/substituir", ['pessoa_id' => $carla->id])->assertOk();
        $this->assertTrue(DB::table('confirmacoes')->where(['turno_id' => $this->turno->id, 'pessoa_id' => $carla->id])->exists());
    }

    public function test_t158_deixar_a_vaga_em_aberto_libera_a_pessoa_e_avisa(): void
    {
        $troca = $this->troca();

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/vaga-aberta")
            ->assertOk()->assertJsonPath('troca.resolucao', 'vaga_aberta');

        $this->assertSame(0, DB::table('confirmacoes')->where('turno_id', $this->turno->id)->count());
        $this->assertSame(['Sua substituição no Som · 08/11 foi aceita. A vaga ficou em aberto.'], $this->avisos('helenaU'));
    }

    public function test_t159_so_o_lider_do_ministerio_resolve(): void
    {
        $troca = $this->troca();
        $bruno = $this->pessoaDaEquipe('Bruno');

        foreach (['helenaU', 'anaU', 'admU'] as $quem) {
            $this->como($this->c[$quem]);
            $this->postJson("/api/trocas/{$troca->id}/manter")->assertForbidden();
            $this->postJson("/api/trocas/{$troca->id}/vaga-aberta")->assertForbidden();
            $this->postJson("/api/trocas/{$troca->id}/substituir", ['pessoa_id' => $bruno->id])->assertForbidden();
            $this->getJson("/api/trocas/{$troca->id}/candidatos")->assertForbidden();
        }

        $this->assertSame('aberto', DB::table('trocas')->where('id', $troca->id)->value('estado'));
    }

    public function test_t160_se_o_lider_tira_a_pessoa_o_pedido_dela_se_encerra(): void
    {
        $troca = $this->troca();

        $this->como($this->c['marcosU'])
            ->deleteJson("/api/turnos/{$this->turno->id}/confirmacoes/{$this->c['helena']->id}")->assertNoContent();

        $linha = DB::table('trocas')->where('id', $troca->id)->first();
        $this->assertSame('resolvido', $linha->estado);
        $this->assertSame('vaga_aberta', $linha->resolucao);
    }

    public function test_pedido_de_quem_ja_nao_esta_confirmado_nao_pode_ser_resolvido(): void
    {
        $troca = $this->troca();
        DB::table('confirmacoes')->where('turno_id', $this->turno->id)->delete();

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/manter")
            ->assertStatus(422)->assertJsonPath('message', 'A pessoa não está mais confirmada neste turno.');
        $this->postJson("/api/trocas/{$troca->id}/vaga-aberta")->assertStatus(422);
    }

    public function test_t161_minha_escala_mostra_o_pedido_aberto(): void
    {
        $troca = $this->troca();

        $this->como($this->c['helenaU'])->getJson('/api/minha-escala')
            ->assertOk()->assertJsonPath('turnos.0.troca_aberta_id', $troca->id);

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/manter");
        $this->como($this->c['helenaU'])->getJson('/api/minha-escala')->assertJsonPath('turnos.0.troca_aberta_id', null);
    }

    public function test_pedido_de_outra_igreja_responde_404(): void
    {
        $outra = $this->igreja('Igreja B');
        $troca = $this->naIgreja($outra, fn () => Troca::create(['turno_id' => $this->turno->id, 'pessoa_id' => $this->c['helena']->id, 'motivo' => 'Viagem']));

        $this->como($this->c['marcosU'])->postJson("/api/trocas/{$troca->id}/manter")->assertNotFound();
    }
}
