<?php

namespace Tests\Unit\Domain;

use App\Domain\Prazo;
use PHPUnit\Framework\TestCase;

class PrazoTest extends TestCase
{
    public function test_t56_prazo_sugerido_e_o_inicio_do_mes_menos_os_dias_padrao(): void
    {
        $this->assertSame('2026-10-27', Prazo::sugerido('2026-11', 5));
    }

    public function test_prazo_cruza_o_ano(): void
    {
        $this->assertSame('2026-12-27', Prazo::sugerido('2027-01', 5));
    }

    public function test_zero_dias_e_o_primeiro_dia_do_mes(): void
    {
        $this->assertSame('2026-11-01', Prazo::sugerido('2026-11', 0));
    }
}
