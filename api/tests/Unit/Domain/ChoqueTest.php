<?php

namespace Tests\Unit\Domain;

use App\Domain\Choque;
use App\Domain\Faixa;
use PHPUnit\Framework\TestCase;

class ChoqueTest extends TestCase
{
    private function faixa(string $data, ?string $ini, ?string $fim): Faixa
    {
        return new Faixa($data, $ini, $fim);
    }

    public function test_t36_horarios_que_se_sobrepoem_chocam(): void
    {
        $a = $this->faixa('2026-11-08', '08h00', '11h30');
        $b = $this->faixa('2026-11-08', '10h00', '12h00');

        $this->assertTrue(Choque::chocam($a, $b));
        $this->assertTrue(Choque::chocam($b, $a));
    }

    public function test_t37_fim_de_um_igual_ao_inicio_do_outro_nao_choca(): void
    {
        $a = $this->faixa('2026-11-08', '08h00', '11h30');
        $b = $this->faixa('2026-11-08', '11h30', '13h00');

        $this->assertFalse(Choque::chocam($a, $b));
    }

    public function test_t38_mesmo_dia_sem_sobreposicao_nao_choca_mas_e_mesmo_dia(): void
    {
        $a = $this->faixa('2026-11-08', '08h00', '11h30');
        $b = $this->faixa('2026-11-08', '19h00', '21h00');

        $this->assertFalse(Choque::chocam($a, $b));
        $this->assertTrue(Choque::mesmoDia($a, $b));
    }

    public function test_dias_diferentes_nunca_chocam(): void
    {
        $a = $this->faixa('2026-11-08', '08h00', '11h30');
        $b = $this->faixa('2026-11-15', '08h00', '11h30');

        $this->assertFalse(Choque::chocam($a, $b));
        $this->assertFalse(Choque::mesmoDia($a, $b));
    }

    public function test_t40_fim_anterior_ao_inicio_dura_60_minutos(): void
    {
        $a = $this->faixa('2026-11-08', '10h00', '09h00');
        $b = $this->faixa('2026-11-08', '10h30', '12h00');
        $c = $this->faixa('2026-11-08', '11h00', '12h00');

        $this->assertSame([600, 660], $a->intervalo());
        $this->assertTrue(Choque::chocam($a, $b));
        $this->assertFalse(Choque::chocam($a, $c));
    }

    public function test_horario_ilegivel_no_mesmo_dia_conta_como_choque(): void
    {
        $a = $this->faixa('2026-11-08', 'manhã', null);
        $b = $this->faixa('2026-11-08', '19h00', '21h00');

        $this->assertTrue(Choque::chocam($a, $b));
    }

    public function test_formatos_diferentes_do_mesmo_horario_chocam(): void
    {
        $a = $this->faixa('2026-11-08', '8:30', '10:00');
        $b = $this->faixa('2026-11-08', '09h00', '09h30');

        $this->assertTrue(Choque::chocam($a, $b));
    }
}
