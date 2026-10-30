<?php

namespace Tests\Concerns;

use App\Models\Banda;
use App\Models\Escala;
use App\Models\Ministerio;
use App\Models\Posto;
use App\Models\Turno;
use Illuminate\Support\Facades\DB;

/**
 * Cria postos, escalas e turnos direto nos modelos, para os testes irem direto ao ponto.
 * Exige o CenarioDaIgreja.
 */
trait MontaEscalas
{
    protected function posto(Ministerio $ministerio, string $nome = 'Som', int $vagas = 2): Posto
    {
        return $this->naIgreja($this->c['igreja'], fn () => $ministerio->postos()->create(['nome' => $nome, 'vagas' => $vagas]));
    }

    protected function escala(Ministerio $ministerio, string $status = 'aberta', string $mes = '2026-11', ?string $prazo = '2999-12-31'): Escala
    {
        return $this->naIgreja($this->c['igreja'], fn () => $ministerio->escalas()->create([
            'mes' => $mes, 'status' => $status, 'prazo' => $prazo,
        ]))->fresh();
    }

    protected function turno(Escala $escala, Posto $posto, string $data = '2026-11-01', string $inicio = '08:00', string $fim = '11:30', int $vagas = 2, ?Banda $banda = null, ?string $culto = null): Turno
    {
        return $this->naIgreja($this->c['igreja'], fn () => $escala->turnos()->create([
            'posto_id' => $posto->id, 'banda_id' => $banda?->id, 'culto_nome' => $culto,
            'data' => $data, 'inicio' => $inicio, 'fim' => $fim, 'vagas' => $vagas,
        ]))->fresh();
    }

    protected function confirmar(Turno $turno, int $pessoaId): void
    {
        DB::table('confirmacoes')->insert(['turno_id' => $turno->id, 'pessoa_id' => $pessoaId, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function marcarDisponivel(Turno $turno, int $pessoaId): void
    {
        DB::table('disponibilidades')->insert(['turno_id' => $turno->id, 'pessoa_id' => $pessoaId, 'created_at' => now(), 'updated_at' => now()]);
    }
}
