<?php

namespace Tests\Unit\Domain;

use App\Domain\GradeDeCultos;
use PHPUnit\Framework\TestCase;

class GradeDeCultosTest extends TestCase
{
    private const DOMINGO = 0;

    private const QUARTA = 3;

    private const SEXTA = 5;

    public function test_t03_culto_semanal_de_domingo_em_novembro_de_2026(): void
    {
        $this->assertSame(
            ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'],
            GradeDeCultos::ocorrencias('semanal', self::DOMINGO, 1, '2026-11'),
        );
    }

    public function test_t04_culto_mensal_na_segunda_sexta(): void
    {
        $this->assertSame(
            ['2026-11-13'],
            GradeDeCultos::ocorrencias('mensal', self::SEXTA, 2, '2026-11'),
        );
    }

    public function test_t05_quinta_quarta_que_nao_existe_no_mes_nao_gera_data(): void
    {
        // Novembro de 2026 tem quatro quartas (04, 11, 18, 25).
        $this->assertSame([], GradeDeCultos::ocorrencias('mensal', self::QUARTA, 5, '2026-11'));
    }

    public function test_t06_ordem_menos_um_retorna_a_ultima_ocorrencia(): void
    {
        $this->assertSame(
            ['2026-11-27'],
            GradeDeCultos::ocorrencias('mensal', self::SEXTA, -1, '2026-11'),
        );
    }

    public function test_mes_com_cinco_ocorrencias_tem_quinta_ordem(): void
    {
        // Novembro de 2026 tem cinco domingos.
        $this->assertSame(
            ['2026-11-29'],
            GradeDeCultos::ocorrencias('mensal', self::DOMINGO, 5, '2026-11'),
        );
    }
}
