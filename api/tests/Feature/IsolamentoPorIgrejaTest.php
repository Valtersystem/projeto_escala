<?php

namespace Tests\Feature;

use App\Models\Ministerio;
use App\Models\Posto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaIgreja;
use Tests\TestCase;

/**
 * Cada igreja só enxerga os próprios dados (D-17).
 */
class IsolamentoPorIgrejaTest extends TestCase
{
    use MontaIgreja;
    use RefreshDatabase;

    public function test_t71_cada_igreja_ve_so_os_proprios_ministerios(): void
    {
        $a = $this->igreja('Igreja A');
        $b = $this->igreja('Igreja B');

        $this->naIgreja($a, fn () => Ministerio::create(['nome' => 'Mídia A']));
        $this->naIgreja($b, fn () => Ministerio::create(['nome' => 'Mídia B']));

        $this->assertSame(['Mídia A'], $this->naIgreja($a, fn () => Ministerio::pluck('nome')->all()));
        $this->assertSame(['Mídia B'], $this->naIgreja($b, fn () => Ministerio::pluck('nome')->all()));
    }

    public function test_t72_sem_igreja_definida_a_consulta_volta_vazia(): void
    {
        $a = $this->igreja();
        $this->naIgreja($a, fn () => Ministerio::create(['nome' => 'Mídia']));

        $this->assertSame(0, Ministerio::count());
    }

    public function test_ao_criar_a_igreja_atual_e_preenchida_sozinha(): void
    {
        $a = $this->igreja();

        $ministerio = $this->naIgreja($a, fn () => Ministerio::create(['nome' => 'Mídia']));

        $this->assertSame($a->id, $ministerio->igreja_id);
    }

    public function test_criar_sem_igreja_definida_e_recusado(): void
    {
        $this->expectException(QueryException::class);

        Ministerio::create(['nome' => 'Sem igreja']);
    }

    public function test_registro_de_outra_igreja_nao_e_encontrado_pelo_id(): void
    {
        $a = $this->igreja('Igreja A');
        $b = $this->igreja('Igreja B');
        $deB = $this->naIgreja($b, fn () => Ministerio::create(['nome' => 'Mídia B']));

        $this->assertNull($this->naIgreja($a, fn () => Ministerio::find($deB->id)));
        $this->assertNotNull($this->naIgreja($b, fn () => Ministerio::find($deB->id)));
    }

    public function test_relacoes_tambem_respeitam_a_igreja(): void
    {
        $a = $this->igreja('Igreja A');
        $b = $this->igreja('Igreja B');

        $this->naIgreja($a, function () {
            $midia = Ministerio::create(['nome' => 'Mídia']);
            $midia->postos()->create(['nome' => 'Câmera', 'vagas' => 2]);
        });

        $this->assertSame(1, $this->naIgreja($a, fn () => Posto::count()));
        $this->assertSame(0, $this->naIgreja($b, fn () => Posto::count()));
    }

    public function test_codigo_de_sistema_pode_ler_sem_escopo_de_proposito(): void
    {
        $a = $this->igreja('Igreja A');
        $b = $this->igreja('Igreja B');
        $this->naIgreja($a, fn () => Ministerio::create(['nome' => 'Mídia A']));
        $this->naIgreja($b, fn () => Ministerio::create(['nome' => 'Mídia B']));

        $this->assertSame(2, Ministerio::withoutGlobalScopes()->count());
    }
}
