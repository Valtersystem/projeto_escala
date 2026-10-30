<?php

namespace Tests\Unit\Domain;

use App\Domain\Horario;
use PHPUnit\Framework\TestCase;

class HorarioTest extends TestCase
{
    public function test_t39_formatos_diferentes_sao_lidos_como_o_mesmo_valor(): void
    {
        $this->assertSame(510, Horario::minutos('8:30'));
        $this->assertSame(510, Horario::minutos('08h30'));
        $this->assertSame(510, Horario::minutos('8.30'));
        $this->assertSame(510, Horario::minutos('8,30'));
    }

    public function test_hora_sem_minutos(): void
    {
        $this->assertSame(480, Horario::minutos('8'));
        $this->assertSame(480, Horario::minutos('08h00'));
    }

    public function test_formato_do_banco(): void
    {
        $this->assertSame(1140, Horario::minutos('19:00:00'));
    }

    public function test_texto_invalido_retorna_nulo(): void
    {
        $this->assertNull(Horario::minutos(''));
        $this->assertNull(Horario::minutos(null));
        $this->assertNull(Horario::minutos('manhã'));
        $this->assertNull(Horario::minutos('25h00'));
        $this->assertNull(Horario::minutos('10h75'));
    }
}
