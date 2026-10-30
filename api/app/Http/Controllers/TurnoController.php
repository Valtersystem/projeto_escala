<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Domain\EstadoEscala;
use App\Domain\GradeDeCultos;
use App\Domain\Horario;
use App\Domain\Rodizio;
use App\Exceptions\RegraDeNegocio;
use App\Models\Culto;
use App\Models\Escala;
use App\Models\Ministerio;
use App\Models\Turno;
use App\Rules\HorarioValido;
use App\Support\Concorrencia;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** RN-24 a RN-32: turnos da escala. */
class TurnoController extends Controller
{
    /** RN-28: turno avulso, com ou sem culto e com ou sem banda. */
    public function store(Request $request, Escala $escala): JsonResponse
    {
        $ministerio = $escala->ministerio;
        Gate::authorize('lidera', $ministerio);
        $this->exigirEscalaEditavel($escala, 'Reabra a escala para adicionar turnos.');

        $dados = $request->validate([
            'posto_id' => ['required', 'integer', Rule::exists('postos', 'id')->where('ministerio_id', $ministerio->id)],
            'banda_id' => ['nullable', 'integer', Rule::exists('bandas', 'id')->where('ministerio_id', $ministerio->id)],
            'culto_id' => ['nullable', 'integer', Rule::exists('cultos', 'id')->where('igreja_id', $ministerio->igreja_id)],
            'data' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['required', new HorarioValido()],
            'fim' => ['required', new HorarioValido()],
            'vagas' => ['nullable', 'integer'],
        ]);

        $posto = $ministerio->postos()->findOrFail($dados['posto_id']);
        $culto = isset($dados['culto_id']) ? Culto::find($dados['culto_id']) : null;

        $turno = $escala->turnos()->create([
            'posto_id' => $posto->id,
            'banda_id' => $dados['banda_id'] ?? null,
            'culto_id' => $culto?->id,
            'culto_nome' => $culto?->nome,
            'data' => $dados['data'],
            'inicio' => Horario::formatar($dados['inicio']),
            'fim' => Horario::formatar($dados['fim']),
            'vagas' => max(1, (int) ($dados['vagas'] ?? $posto->vagas)),
        ]);

        if ($escala->status !== EstadoEscala::Rascunho) {
            Registro::feito('turno.criado', 'Criou o turno '.Registro::turno($turno), $escala->ministerio_id, $turno->id);
        }

        return response()->json(['turno' => self::resumo($turno->fresh(['posto:id,nome', 'banda:id,nome']), true)], 201);
    }

    /**
     * RN-25: em rascunho muda tudo; depois de liberada só as vagas, e só para mais;
     * publicada não muda (reabra antes).
     */
    public function update(Request $request, Turno $turno): JsonResponse
    {
        $escala = $turno->escala;
        $ministerio = $escala->ministerio;
        Gate::authorize('lidera', $ministerio);

        $dados = $request->validate([
            'versao' => ['required', 'integer'],
            'posto_id' => ['sometimes', 'integer', Rule::exists('postos', 'id')->where('ministerio_id', $ministerio->id)],
            'banda_id' => ['sometimes', 'nullable', 'integer', Rule::exists('bandas', 'id')->where('ministerio_id', $ministerio->id)],
            'data' => ['sometimes', 'date_format:Y-m-d'],
            'inicio' => ['sometimes', new HorarioValido()],
            'fim' => ['sometimes', new HorarioValido()],
            'vagas' => ['sometimes', 'integer'],
        ]);

        $versao = $dados['versao'];
        unset($dados['versao']);

        if ($escala->status === EstadoEscala::Publicada) {
            throw new RegraDeNegocio('Reabra a escala para alterar o turno.');
        }

        if ($escala->status === EstadoEscala::Aberta) {
            if (array_diff(array_keys($dados), ['vagas']) !== []) {
                throw new RegraDeNegocio('Depois de liberada, só as vagas do turno podem mudar.');
            }

            if (isset($dados['vagas']) && $dados['vagas'] < $turno->vagas) {
                throw new RegraDeNegocio('Depois de liberada, as vagas só podem aumentar.');
            }
        }

        foreach (['inicio', 'fim'] as $campo) {
            if (isset($dados[$campo])) {
                $dados[$campo] = Horario::formatar($dados[$campo]);
            }
        }

        if (isset($dados['vagas'])) {
            $dados['vagas'] = max(1, $dados['vagas']);
        }

        $antes = $turno->only(array_keys($dados));

        Concorrencia::atualizar($turno, $versao, $dados);

        if ($escala->status !== EstadoEscala::Rascunho) {
            Registro::feito('turno.alterado', 'Alterou o turno '.Registro::turno($turno), $ministerio->id, $turno->id, $antes, $dados);
        }

        return response()->json(['turno' => self::resumo($turno->load(['posto:id,nome', 'banda:id,nome']), true)]);
    }

    /** RN-25: só se exclui turno de escala em rascunho. */
    public function destroy(Turno $turno): Response
    {
        Gate::authorize('lidera', $turno->escala->ministerio);

        if ($turno->escala->status !== EstadoEscala::Rascunho) {
            throw new RegraDeNegocio('Só dá para excluir turnos de escala em rascunho.');
        }

        $turno->delete();

        return response()->noContent();
    }

    /**
     * RN-26/27/29 a 31: gera os turnos a partir da grade de cultos. Para cada culto,
     * data do mês e posto escolhidos nasce um turno; não duplica o que já existe; se o
     * ministério tem bandas, cada horário recebe a próxima banda do rodízio.
     */
    public function gerar(Request $request, Escala $escala): JsonResponse
    {
        $ministerio = $escala->ministerio;
        Gate::authorize('lidera', $ministerio);
        $this->exigirEscalaEditavel($escala, 'Reabra a escala para gerar turnos.');

        $dados = $request->validate([
            'culto_ids' => ['required', 'array', 'min:1'],
            'culto_ids.*' => ['integer', 'distinct'],
            'posto_ids' => ['required', 'array', 'min:1'],
            'posto_ids.*' => ['integer', 'distinct'],
        ]);

        if (! $ministerio->postos()->exists()) {
            throw new RegraDeNegocio('Crie um posto antes.');
        }

        $postos = $ministerio->postos()->whereIn('id', $dados['posto_ids'])->get();
        $cultos = Culto::whereIn('id', $dados['culto_ids'])->orderBy('dia_semana')->orderBy('inicio')->get();

        if ($postos->count() !== count($dados['posto_ids'])) {
            throw ValidationException::withMessages(['posto_ids' => ['Use só postos deste ministério.']]);
        }

        if ($cultos->count() !== count($dados['culto_ids'])) {
            throw ValidationException::withMessages(['culto_ids' => ['Culto não encontrado.']]);
        }

        $plano = $this->montarPlano($escala, $postos, $cultos, $temData);

        if (! $temData) {
            throw new RegraDeNegocio('Nenhum culto cai neste mês.');
        }

        if ($plano === []) {
            throw new RegraDeNegocio('Esses turnos já existem nesta escala.');
        }

        $plano = $this->aplicarRodizio($ministerio, $plano);

        $criados = new EloquentCollection(
            DB::transaction(fn () => array_map(fn (array $t) => $escala->turnos()->create($t)->fresh(), $plano)),
        );

        return response()->json([
            'total' => $criados->count(),
            'turnos' => $criados->load(['posto:id,nome', 'banda:id,nome'])->map(fn (Turno $t) => self::resumo($t, true))->values(),
        ], 201);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Posto>  $postos
     * @param  \Illuminate\Support\Collection<int, Culto>  $cultos
     * @return list<array<string, mixed>>
     */
    private function montarPlano(Escala $escala, $postos, $cultos, ?bool &$temData): array
    {
        $temData = false;

        $existentes = $escala->turnos()->get(['posto_id', 'data', 'inicio'])
            ->map(fn (Turno $t) => $this->chave($t->posto_id, $t->data->toDateString(), substr((string) $t->inicio, 0, 5)))
            ->all();

        $plano = [];

        foreach ($cultos as $culto) {
            $inicio = substr((string) $culto->inicio, 0, 5);
            $fim = substr((string) $culto->fim, 0, 5);

            foreach (GradeDeCultos::ocorrencias($culto->tipo, $culto->dia_semana, $culto->ordem, $escala->mes) as $data) {
                $temData = true;

                foreach ($postos as $posto) {
                    $chave = $this->chave($posto->id, $data, $inicio);

                    if (in_array($chave, $existentes, true)) {
                        continue;
                    }

                    $existentes[] = $chave;
                    $plano[] = [
                        'posto_id' => $posto->id,
                        'culto_id' => $culto->id,
                        'culto_nome' => $culto->nome,
                        'data' => $data,
                        'inicio' => $inicio,
                        'fim' => $fim,
                        'vagas' => max(1, $posto->vagas),
                        'banda_id' => null,
                    ];
                }
            }
        }

        return $plano;
    }

    /**
     * RN-30: continua a sequência a partir da banda do último turno anterior do ministério.
     *
     * @param  list<array<string, mixed>>  $plano
     * @return list<array<string, mixed>>
     */
    private function aplicarRodizio(Ministerio $ministerio, array $plano): array
    {
        $bandas = $ministerio->bandas()->pluck('id')->all();

        if ($bandas === []) {
            return $plano;
        }

        usort($plano, fn (array $a, array $b) => [$a['data'], Horario::minutos($a['inicio'])] <=> [$b['data'], Horario::minutos($b['inicio'])]);

        $primeiro = $plano[0];
        $ultima = $this->bandaDoUltimoTurnoAntesDe($ministerio, $primeiro['data'], $primeiro['inicio']);

        $mapa = Rodizio::atribuir(
            $bandas,
            array_map(fn (array $t) => ['data' => $t['data'], 'inicio' => $t['inicio']], $plano),
            $ultima,
        );

        foreach ($plano as &$turno) {
            $turno['banda_id'] = $mapa[$turno['data'].'|'.$turno['inicio']];
        }
        unset($turno);

        return $plano;
    }

    private function bandaDoUltimoTurnoAntesDe(Ministerio $ministerio, string $data, string $inicio): int|string|null
    {
        $candidatos = Turno::whereHas('escala', fn ($e) => $e->where('ministerio_id', $ministerio->id))
            ->whereNotNull('banda_id')
            ->where('data', '<=', $data)
            ->orderByDesc('data')->orderByDesc('inicio')
            ->limit(30)->get();

        foreach ($candidatos as $turno) {
            $antes = $turno->data->toDateString() < $data
                || Horario::minutos((string) $turno->inicio) < Horario::minutos($inicio);

            if ($antes) {
                return $turno->banda_id;
            }
        }

        return null;
    }

    private function chave(int $posto, string $data, string $inicio): string
    {
        return "{$posto}|{$data}|{$inicio}";
    }

    private function exigirEscalaEditavel(Escala $escala, string $mensagem): void
    {
        if ($escala->status === EstadoEscala::Publicada) {
            throw new RegraDeNegocio($mensagem);
        }
    }

    /**
     * RN-64: o voluntário não vê a quantidade de vagas; só o líder.
     *
     * @return array<string, mixed>
     */
    public static function resumo(Turno $t, bool $lider): array
    {
        $dados = [
            'id' => $t->id,
            'escala_id' => $t->escala_id,
            'data' => $t->data->toDateString(),
            'inicio' => substr((string) $t->inicio, 0, 5),
            'fim' => substr((string) $t->fim, 0, 5),
            'posto' => $t->posto ? ['id' => $t->posto->id, 'nome' => $t->posto->nome] : null,
            'banda' => $t->banda ? ['id' => $t->banda->id, 'nome' => $t->banda->nome] : null,
            'culto' => $t->culto_nome,
        ];

        if ($lider) {
            $dados['vagas'] = $t->vagas;
            $dados['versao'] = $t->versao;
        }

        return $dados;
    }
}
