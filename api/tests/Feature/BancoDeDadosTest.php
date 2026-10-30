<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restrições e cascatas do banco (RN-08, RN-12 a RN-16, RN-22, D-07).
 */
class BancoDeDadosTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    private function inserir(string $tabela, array $dados): int
    {
        return DB::table($tabela)->insertGetId($dados + ['created_at' => now(), 'updated_at' => now()]);
    }

    private function montar(): void
    {
        $igreja = $this->inserir('igrejas', ['nome' => 'Igreja Teste']);
        $lider = $this->inserir('pessoas', ['igreja_id' => $igreja, 'nome' => 'Marcos', 'email' => 'marcos@teste.com']);
        $voluntaria = $this->inserir('pessoas', ['igreja_id' => $igreja, 'nome' => 'Helena', 'email' => 'helena@teste.com']);
        $ministerio = $this->inserir('ministerios', ['igreja_id' => $igreja, 'nome' => 'Louvor', 'lider_id' => $lider]);
        DB::table('ministerio_pessoa')->insert([
            ['ministerio_id' => $ministerio, 'pessoa_id' => $lider],
            ['ministerio_id' => $ministerio, 'pessoa_id' => $voluntaria],
        ]);
        $posto = $this->inserir('postos', ['igreja_id' => $igreja, 'ministerio_id' => $ministerio, 'nome' => 'Som', 'vagas' => 2]);
        DB::table('posto_pessoa')->insert(['posto_id' => $posto, 'pessoa_id' => $voluntaria]);
        $banda = $this->inserir('bandas', ['igreja_id' => $igreja, 'ministerio_id' => $ministerio, 'nome' => 'Banda 1', 'ordem' => 1]);
        $funcao = $this->inserir('funcoes', ['igreja_id' => $igreja, 'ministerio_id' => $ministerio, 'nome' => 'Voz']);
        DB::table('funcao_pessoa')->insert(['funcao_id' => $funcao, 'pessoa_id' => $voluntaria]);
        $culto = $this->inserir('cultos', [
            'igreja_id' => $igreja, 'nome' => 'Culto da Família', 'tipo' => 'semanal',
            'dia_semana' => 0, 'ordem' => 1, 'inicio' => '09:00', 'fim' => '10:30',
        ]);
        $escala = $this->inserir('escalas', ['igreja_id' => $igreja, 'ministerio_id' => $ministerio, 'mes' => '2026-11']);
        $turno = $this->inserir('turnos', [
            'igreja_id' => $igreja, 'escala_id' => $escala, 'posto_id' => $posto, 'banda_id' => $banda,
            'culto_id' => $culto, 'culto_nome' => 'Culto da Família',
            'data' => '2026-11-01', 'inicio' => '09:00', 'fim' => '10:30', 'vagas' => 2,
        ]);
        DB::table('disponibilidades')->insert(['turno_id' => $turno, 'pessoa_id' => $voluntaria, 'created_at' => now(), 'updated_at' => now()]);
        $confirmacao = $this->inserir('confirmacoes', ['turno_id' => $turno, 'pessoa_id' => $voluntaria]);
        DB::table('confirmacao_funcao')->insert(['confirmacao_id' => $confirmacao, 'funcao_id' => $funcao]);

        $this->ids = compact(
            'igreja', 'lider', 'voluntaria', 'ministerio', 'posto', 'banda', 'funcao',
            'culto', 'escala', 'turno', 'confirmacao',
        );
    }

    public function test_t13_nao_permite_duas_escalas_no_mesmo_mes_do_ministerio(): void
    {
        $this->montar();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->inserir('escalas', [
            'igreja_id' => $this->ids['igreja'],
            'ministerio_id' => $this->ids['ministerio'],
            'mes' => '2026-11',
        ]);
    }

    public function test_outro_mes_do_mesmo_ministerio_e_permitido(): void
    {
        $this->montar();

        $this->inserir('escalas', [
            'igreja_id' => $this->ids['igreja'],
            'ministerio_id' => $this->ids['ministerio'],
            'mes' => '2026-12',
        ]);

        $this->assertSame(2, DB::table('escalas')->count());
    }

    public function test_t08_excluir_ministerio_apaga_postos_escalas_e_turnos_e_mantem_as_pessoas(): void
    {
        $this->montar();

        DB::table('ministerios')->where('id', $this->ids['ministerio'])->delete();

        $this->assertSame(0, DB::table('postos')->count());
        $this->assertSame(0, DB::table('escalas')->count());
        $this->assertSame(0, DB::table('turnos')->count());
        $this->assertSame(0, DB::table('disponibilidades')->count());
        $this->assertSame(0, DB::table('confirmacoes')->count());
        $this->assertSame(0, DB::table('ministerio_pessoa')->count());
        $this->assertSame(2, DB::table('pessoas')->count());
    }

    public function test_t10_excluir_posto_apaga_os_turnos_e_tira_o_posto_das_pessoas(): void
    {
        $this->montar();

        DB::table('postos')->where('id', $this->ids['posto'])->delete();

        $this->assertSame(0, DB::table('turnos')->count());
        $this->assertSame(0, DB::table('posto_pessoa')->count());
        $this->assertSame(2, DB::table('pessoas')->count());
    }

    public function test_t22_excluir_turno_apaga_disponibilidades_e_confirmacoes(): void
    {
        $this->montar();

        DB::table('turnos')->where('id', $this->ids['turno'])->delete();

        $this->assertSame(0, DB::table('disponibilidades')->count());
        $this->assertSame(0, DB::table('confirmacoes')->count());
        $this->assertSame(0, DB::table('confirmacao_funcao')->count());
    }

    public function test_t07_excluir_culto_mantem_o_turno_e_o_nome_do_culto(): void
    {
        $this->montar();

        DB::table('cultos')->where('id', $this->ids['culto'])->delete();

        $turno = DB::table('turnos')->where('id', $this->ids['turno'])->first();

        $this->assertNotNull($turno);
        $this->assertNull($turno->culto_id);
        $this->assertSame('Culto da Família', $turno->culto_nome);
    }

    public function test_excluir_banda_solta_a_banda_dos_turnos_sem_apagar_o_turno(): void
    {
        $this->montar();

        DB::table('bandas')->where('id', $this->ids['banda'])->delete();

        $turno = DB::table('turnos')->where('id', $this->ids['turno'])->first();

        $this->assertNotNull($turno);
        $this->assertNull($turno->banda_id);
    }

    public function test_rn12_excluir_pessoa_remove_confirmacoes_e_disponibilidades_e_libera_a_lideranca(): void
    {
        $this->montar();

        DB::table('pessoas')->where('id', $this->ids['voluntaria'])->delete();
        DB::table('pessoas')->where('id', $this->ids['lider'])->delete();

        $this->assertSame(0, DB::table('disponibilidades')->count());
        $this->assertSame(0, DB::table('confirmacoes')->count());
        $this->assertNull(DB::table('ministerios')->where('id', $this->ids['ministerio'])->value('lider_id'));
    }

    public function test_rn14_excluir_funcao_remove_das_pessoas_e_dos_turnos(): void
    {
        $this->montar();

        DB::table('funcoes')->where('id', $this->ids['funcao'])->delete();

        $this->assertSame(0, DB::table('funcao_pessoa')->count());
        $this->assertSame(0, DB::table('confirmacao_funcao')->count());
        $this->assertSame(1, DB::table('confirmacoes')->count());
    }

    public function test_a_mesma_pessoa_nao_marca_nem_e_confirmada_duas_vezes_no_turno(): void
    {
        $this->montar();

        try {
            DB::table('disponibilidades')->insert([
                'turno_id' => $this->ids['turno'], 'pessoa_id' => $this->ids['voluntaria'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('A disponibilidade duplicada deveria ser recusada.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        $this->inserir('confirmacoes', ['turno_id' => $this->ids['turno'], 'pessoa_id' => $this->ids['voluntaria']]);
    }

    public function test_email_e_unico_por_igreja_mas_pode_repetir_em_outra_igreja(): void
    {
        $this->montar();
        $outraIgreja = $this->inserir('igrejas', ['nome' => 'Outra Igreja']);

        $this->inserir('pessoas', ['igreja_id' => $outraIgreja, 'nome' => 'Marcos', 'email' => 'marcos@teste.com']);
        $this->assertSame(2, DB::table('pessoas')->where('email', 'marcos@teste.com')->count());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->inserir('pessoas', ['igreja_id' => $this->ids['igreja'], 'nome' => 'Outro', 'email' => 'marcos@teste.com']);
    }

    public function test_excluir_a_igreja_apaga_tudo_dela(): void
    {
        $this->montar();

        DB::table('igrejas')->where('id', $this->ids['igreja'])->delete();

        foreach (['pessoas', 'ministerios', 'postos', 'bandas', 'funcoes', 'cultos', 'escalas', 'turnos'] as $tabela) {
            $this->assertSame(0, DB::table($tabela)->count(), "Sobrou registro em {$tabela}.");
        }
    }
}
