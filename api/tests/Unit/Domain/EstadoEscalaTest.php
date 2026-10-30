<?php

namespace Tests\Unit\Domain;

use App\Domain\EstadoEscala;
use App\Exceptions\RegraDeNegocio;
use PHPUnit\Framework\TestCase;

class EstadoEscalaTest extends TestCase
{
    public function test_t14_nao_libera_escala_sem_turnos(): void
    {
        $this->expectException(RegraDeNegocio::class);
        $this->expectExceptionMessage('pelo menos um turno');

        EstadoEscala::Rascunho->validarTransicao(EstadoEscala::Aberta, 0);
    }

    public function test_libera_escala_com_turnos(): void
    {
        EstadoEscala::Rascunho->validarTransicao(EstadoEscala::Aberta, 3);

        $this->assertTrue(EstadoEscala::Rascunho->podePara(EstadoEscala::Aberta));
    }

    public function test_t16_publica_mesmo_com_turnos_incompletos(): void
    {
        // A transição não olha vagas preenchidas: só avisa na tela (RN-21).
        EstadoEscala::Aberta->validarTransicao(EstadoEscala::Publicada, 5);

        $this->assertTrue(EstadoEscala::Aberta->podePara(EstadoEscala::Publicada));
    }

    public function test_t18_escala_publicada_pode_ser_reaberta(): void
    {
        EstadoEscala::Publicada->validarTransicao(EstadoEscala::Aberta, 5);

        $this->assertTrue(EstadoEscala::Publicada->podePara(EstadoEscala::Aberta));
    }

    public function test_transicoes_invalidas_sao_recusadas(): void
    {
        $this->assertFalse(EstadoEscala::Rascunho->podePara(EstadoEscala::Publicada));
        $this->assertFalse(EstadoEscala::Aberta->podePara(EstadoEscala::Rascunho));
        $this->assertFalse(EstadoEscala::Publicada->podePara(EstadoEscala::Rascunho));

        $this->expectException(RegraDeNegocio::class);
        EstadoEscala::Rascunho->validarTransicao(EstadoEscala::Publicada, 5);
    }
}
