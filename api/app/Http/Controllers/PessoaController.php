<?php

namespace App\Http\Controllers;

use App\Models\Banda;
use App\Models\Funcao;
use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\Posto;
use App\Models\User;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PessoaController extends Controller
{
    /** Adm vê todas; o líder vê as equipes dos ministérios que lidera; o voluntário não lista. */
    public function index(Request $request): JsonResponse
    {
        $ator = $request->attributes->get('pessoa');

        if ($ator->adm) {
            $consulta = Pessoa::query();
        } elseif ($ator->ministeriosQueLidera()->exists()) {
            $consulta = Pessoa::whereHas('ministerios', fn ($m) => $m->where('lider_id', $ator->id));
        } else {
            abort(403);
        }

        if ($ministerio = $request->query('ministerio_id')) {
            $consulta->whereHas('ministerios', fn ($m) => $m->whereKey($ministerio));
        }

        $pessoas = $consulta->with('ministerios:id,nome')->orderBy('nome');

        // Com o ministério na consulta, vêm também os postos, as funções e as bandas dele que cada pessoa tem.
        $doMinisterio = null;

        if ($ministerio) {
            $doMinisterio = [
                'postos' => Posto::where('ministerio_id', $ministerio)->pluck('id')->all(),
                'funcoes' => Funcao::where('ministerio_id', $ministerio)->pluck('id')->all(),
                'bandas' => Banda::where('ministerio_id', $ministerio)->pluck('id')->all(),
            ];
            $pessoas->with(['postos:id', 'funcoes:id', 'bandas:id']);
        }

        return response()->json([
            'pessoas' => $pessoas->get()->map(fn (Pessoa $p) => $this->resumo($p) + ($doMinisterio ? [
                'posto_ids' => $p->postos->pluck('id')->intersect($doMinisterio['postos'])->values(),
                'funcao_ids' => $p->funcoes->pluck('id')->intersect($doMinisterio['funcoes'])->values(),
                'banda_ids' => $p->bandas->pluck('id')->intersect($doMinisterio['bandas'])->values(),
            ] : []))->values(),
        ]);
    }

    /** RN-09. O líder só cadastra dentro dos ministérios que lidera. */
    public function store(Request $request): JsonResponse
    {
        $ator = $request->attributes->get('pessoa');
        $igreja = app(IgrejaAtual::class)->id();

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', Rule::unique('pessoas', 'email')->where('igreja_id', $igreja)],
            'telefone' => ['nullable', 'string', 'max:32'],
            'adm' => ['sometimes', 'boolean'],
            'ministerio_ids' => [$ator->adm ? 'sometimes' : 'required', 'array', $ator->adm ? 'min:0' : 'min:1'],
            'ministerio_ids.*' => ['integer', Rule::exists('ministerios', 'id')->where('igreja_id', $igreja)],
        ]);

        $ministerios = $dados['ministerio_ids'] ?? [];

        if (! $ator->adm) {
            abort_if($request->boolean('adm'), 403);

            $lideres = $ator->ministeriosQueLidera()->pluck('id')->all();
            abort_if(array_diff($ministerios, $lideres) !== [], 403);
        }

        $pessoa = DB::transaction(function () use ($dados, $ministerios, $ator) {
            $pessoa = Pessoa::create([
                'nome' => $dados['nome'],
                'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'] ?? null,
                'adm' => $ator->adm ? (bool) ($dados['adm'] ?? false) : false,
            ]);

            $pessoa->ministerios()->sync($ministerios);

            return $pessoa;
        });

        return response()->json(['pessoa' => $this->resumo($pessoa->load('ministerios:id,nome'))], 201);
    }

    public function update(Request $request, Pessoa $pessoa): JsonResponse
    {
        Gate::authorize('gerir-pessoa', $pessoa);

        $ator = $request->attributes->get('pessoa');

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('pessoas', 'email')
                ->where('igreja_id', app(IgrejaAtual::class)->id())->ignore($pessoa->id)],
            'telefone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'ativo' => ['sometimes', 'boolean'],
            'adm' => ['sometimes', 'boolean'],
        ]);

        // Só o Adm define quem é Adm (seção 2).
        abort_if(array_key_exists('adm', $dados) && ! $ator->adm, 403);

        // O e-mail é o login: com login já criado, trocar o e-mail deixaria a pessoa sem acesso.
        if (array_key_exists('email', $dados) && $pessoa->user_id !== null && $dados['email'] !== $pessoa->email) {
            throw ValidationException::withMessages(['email' => ['Esta pessoa já tem login; o e-mail não pode ser trocado.']]);
        }

        $pessoa->update($dados);

        return response()->json(['pessoa' => $this->resumo($pessoa->load('ministerios:id,nome'))]);
    }

    /** RN-12: exclui a pessoa (disponibilidades e confirmações saem por cascata) e o login dela. */
    public function destroy(Request $request, Pessoa $pessoa): Response
    {
        Gate::authorize('adm');

        if ($request->attributes->get('pessoa')->is($pessoa)) {
            throw ValidationException::withMessages(['pessoa' => ['Você não pode excluir a si mesmo.']]);
        }

        DB::transaction(function () use ($pessoa) {
            $user = $pessoa->user_id ? User::find($pessoa->user_id) : null;
            $pessoa->delete();

            if ($user) {
                $user->tokens()->delete();
                $user->delete();
            }
        });

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function resumo(Pessoa $p): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->nome,
            'email' => $p->email,
            'telefone' => $p->telefone,
            'adm' => $p->adm,
            'ativo' => $p->ativo,
            'tem_login' => $p->user_id !== null,
            'ministerios' => $p->ministerios->map(fn (Ministerio $m) => ['id' => $m->id, 'nome' => $m->nome])->values(),
        ];
    }
}
