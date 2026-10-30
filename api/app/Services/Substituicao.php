<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\Turno;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RN-56: quem pode assumir um turno no lugar de outra pessoa.
 */
final class Substituicao
{
    /**
     * Pessoas ativas da equipe, que não estão confirmadas no turno e atendem o posto
     * (ou não têm nenhum posto marcado neste ministério). Ordem: dentro da banda do
     * turno primeiro, depois quem marcou disponibilidade, depois quem tem menos turnos.
     *
     * @return Collection<int, array<string, mixed>> cada item traz `pessoa` e os dados para a tela
     */
    public static function candidatos(Turno $turno, Pessoa $quemSai): Collection
    {
        $ministerio = $turno->escala->ministerio;
        $confirmados = $turno->confirmacoes()->pluck('pessoa_id')->all();
        $marcaram = $turno->disponibilidades()->pluck('pessoa_id')->all();
        $membrosDaBanda = $turno->banda_id ? $turno->banda->membros()->pluck('pessoas.id')->all() : [];

        $postosDoMinisterio = $ministerio->postos()->pluck('id')->all();
        $postosPorPessoa = DB::table('posto_pessoa')
            ->whereIn('posto_id', $postosDoMinisterio)->get()->groupBy('pessoa_id');

        return $ministerio->equipe()
            ->where('pessoas.ativo', true)
            ->whereNotIn('pessoas.id', [...$confirmados, $quemSai->id])
            ->orderBy('pessoas.nome') // desempate previsível; a ordenação abaixo é estável
            ->get(['pessoas.id', 'pessoas.nome'])
            ->filter(function (Pessoa $p) use ($postosPorPessoa, $turno) {
                $meusPostos = $postosPorPessoa->get($p->id);

                return $meusPostos === null || $meusPostos->contains('posto_id', $turno->posto_id);
            })
            ->map(function (Pessoa $p) use ($turno, $marcaram, $membrosDaBanda) {
                $carga = CargaDaPessoa::de($p->id);

                return [
                    'pessoa' => $p,
                    'marcou' => in_array($p->id, $marcaram, true),
                    'turnos_no_total' => $carga->firmes->count(),
                    'fora_da_banda' => $turno->banda_id !== null && ! in_array($p->id, $membrosDaBanda, true),
                    'choques' => $carga->conflitosCom($turno),
                ];
            })
            ->sort(fn (array $a, array $b) => [$a['fora_da_banda'], ! $a['marcou'], $a['turnos_no_total']]
                <=> [$b['fora_da_banda'], ! $b['marcou'], $b['turnos_no_total']])
            ->values();
    }
}
