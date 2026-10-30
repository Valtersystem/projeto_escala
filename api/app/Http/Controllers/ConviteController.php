<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Exceptions\RegraDeNegocio;
use App\Models\Convite;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Primeiro acesso e troca de senha por convite (RN-73 a RN-77). Na v1 não há
 * recuperação de senha por e-mail: quem esquece a senha recebe um novo convite do líder.
 */
class ConviteController extends Controller
{
    private const VALIDADE_EM_DIAS = 7;

    /** O líder (ou Adm) gera o link. O token só aparece nesta resposta. */
    public function gerar(Request $request, Pessoa $pessoa): JsonResponse
    {
        Gate::authorize('gerir-pessoa', $pessoa);

        if (! $pessoa->email) {
            throw new RegraDeNegocio('Cadastre o e-mail da pessoa antes de convidar.');
        }

        if (! $pessoa->ativo) {
            throw new RegraDeNegocio('Esta pessoa está inativa. Reative antes de convidar.');
        }

        Registro::feito('convite.gerado', "Gerou um link de acesso para {$pessoa->nome}", null, $pessoa->id);

        $token = Str::random(48);

        $convite = DB::transaction(function () use ($pessoa, $request, $token) {
            // Um convite novo invalida os anteriores ainda não usados.
            Convite::where('pessoa_id', $pessoa->id)->whereNull('usado_em')->delete();

            return Convite::create([
                'pessoa_id' => $pessoa->id,
                'criado_por' => $request->attributes->get('pessoa')->id,
                'token_hash' => Convite::hashDe($token),
                'expira_em' => now()->addDays(self::VALIDADE_EM_DIAS),
            ]);
        });

        return response()->json([
            'token' => $token,
            'link' => rtrim((string) config('app.frontend_url'), '/').'/convite/'.$token,
            'expira_em' => $convite->expira_em->toIso8601String(),
        ], 201);
    }

    /**
     * Pública: a pessoa abre o link e escolhe a senha. Se ela ainda não tem login,
     * o login é criado com o e-mail cadastrado. Se já tem, a senha é trocada.
     */
    public function aceitar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'senha' => ['required', 'string', 'min:8'],
        ]);

        $convite = Convite::where('token_hash', Convite::hashDe($dados['token']))->first();
        $pessoa = $convite?->pessoa;

        if (! $convite || ! $convite->vigente() || ! $pessoa || ! $pessoa->ativo || ! $pessoa->email) {
            throw ValidationException::withMessages(['token' => ['Convite inválido ou expirado. Peça um novo ao líder.']]);
        }

        $user = DB::transaction(function () use ($convite, $pessoa, $dados) {
            if ($pessoa->user_id) {
                $user = User::findOrFail($pessoa->user_id);
                $user->update(['password' => $dados['senha']]);
                // A senha mudou: encerra as sessões anteriores.
                $user->tokens()->delete();
            } else {
                if (User::where('email', $pessoa->email)->exists()) {
                    throw ValidationException::withMessages(['token' => ['Já existe um login com esse e-mail. Fale com o líder.']]);
                }

                $user = User::create(['name' => $pessoa->nome, 'email' => $pessoa->email, 'password' => $dados['senha']]);
                $pessoa->update(['user_id' => $user->id]);
            }

            $convite->update(['usado_em' => now()]);

            return $user;
        });

        return response()->json([
            'token' => $user->createToken('app')->plainTextToken,
            'pessoa' => EuController::resumo($pessoa->refresh()),
        ]);
    }
}
