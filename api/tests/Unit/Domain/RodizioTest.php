<?php

namespace Tests\Unit\Domain;

use App\Domain\Rodizio;
use PHPUnit\Framework\TestCase;

class RodizioTest extends TestCase
{
    private const DOMINGOS_NOV = ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'];

    /** @return list<array{data: string, inicio: string}> */
    private function manhaENoite(array $datas): array
    {
        $horarios = [];

        foreach ($datas as $data) {
            $horarios[] = ['data' => $data, 'inicio' => '08h00'];
            $horarios[] = ['data' => $data, 'inicio' => '19h00'];
        }

        return $horarios;
    }

    public function test_t24_sequencia_de_tres_bandas_confere_com_a_planilha_da_lider(): void
    {
        $resultado = Rodizio::atribuir(['b1', 'b2', 'b3'], $this->manhaENoite(self::DOMINGOS_NOV));

        $this->assertSame(
            ['b1', 'b2', 'b3', 'b1', 'b2', 'b3', 'b1', 'b2', 'b3', 'b1'],
            array_values($resultado),
        );
        $this->assertSame('b1', $resultado['2026-11-01|08h00']);
        $this->assertSame('b2', $resultado['2026-11-01|19h00']);
        $this->assertSame('b3', $resultado['2026-11-08|08h00']);
        $this->assertSame('b1', $resultado['2026-11-08|19h00']);
    }

    public function test_t25_dezembro_continua_de_onde_novembro_parou(): void
    {
        // Novembro terminou na Banda 1 (29/11 noite).
        $dezembro = $this->manhaENoite(['2026-12-06', '2026-12-13']);

        $resultado = Rodizio::atribuir(['b1', 'b2', 'b3'], $dezembro, 'b1');

        $this->assertSame('b2', $resultado['2026-12-06|08h00']);
        $this->assertSame('b3', $resultado['2026-12-06|19h00']);
        $this->assertSame('b1', $resultado['2026-12-13|08h00']);
    }

    public function test_t26_sem_bandas_nada_e_atribuido(): void
    {
        $this->assertSame([], Rodizio::atribuir([], $this->manhaENoite(self::DOMINGOS_NOV)));
    }

    public function test_t62_turnos_do_mesmo_horario_recebem_a_mesma_banda(): void
    {
        // Dois postos no mesmo dia e horário geram o mesmo horário duas vezes.
        $horarios = [
            ['data' => '2026-11-01', 'inicio' => '08h00'],
            ['data' => '2026-11-01', 'inicio' => '08h00'],
            ['data' => '2026-11-01', 'inicio' => '19h00'],
        ];

        $resultado = Rodizio::atribuir(['b1', 'b2', 'b3'], $horarios);

        $this->assertCount(2, $resultado);
        $this->assertSame('b1', $resultado['2026-11-01|08h00']);
        $this->assertSame('b2', $resultado['2026-11-01|19h00']);
    }

    public function test_horarios_fora_de_ordem_sao_ordenados_por_data_e_inicio(): void
    {
        $horarios = [
            ['data' => '2026-11-08', 'inicio' => '19h00'],
            ['data' => '2026-11-01', 'inicio' => '19h00'],
            ['data' => '2026-11-01', 'inicio' => '08h00'],
        ];

        $resultado = Rodizio::atribuir(['b1', 'b2', 'b3'], $horarios);

        $this->assertSame('b1', $resultado['2026-11-01|08h00']);
        $this->assertSame('b2', $resultado['2026-11-01|19h00']);
        $this->assertSame('b3', $resultado['2026-11-08|19h00']);
    }

    public function test_ultima_banda_desconhecida_comeca_pela_primeira(): void
    {
        $resultado = Rodizio::atribuir(['b1', 'b2'], [['data' => '2026-11-01', 'inicio' => '08h00']], 'banda-removida');

        $this->assertSame('b1', $resultado['2026-11-01|08h00']);
    }

    public function test_funciona_com_ids_numericos(): void
    {
        $resultado = Rodizio::atribuir([10, 20, 30], [['data' => '2026-11-01', 'inicio' => '08h00']], 30);

        $this->assertSame(10, $resultado['2026-11-01|08h00']);
    }
}
