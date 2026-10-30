<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * RN-128 a RN-130: login com Google. O Google só prova de quem é o e-mail; quem não foi cadastrado
 * pelo líder (ou pelo pedido de entrada) não ganha acesso.
 */
class GoogleController extends Controller
{
    private const NAO_CADASTRADO = 'Este e-mail do Google não está cadastrado na igreja. Peça ao seu líder ou use o link de entrada.';

    public function entrar(Request $request): JsonResponse
    {
        $dados = $request->validate(['credencial' => ['required', 'string']]);

        $clientId = config('services.google.client_id');
        abort_if(! $clientId, 404);

        $perfil = $this->verificar($dados['credencial'], $clientId);

        $user = $this->usuarioPara($perfil['sub'], mb_strtolower($perfil['email']));
        $pessoa = $user->pessoa;

        if (! $pessoa || ! $pessoa->ativo) {
            throw ValidationException::withMessages(['credencial' => ['Este acesso está desativado. Fale com o líder.']]);
        }

        return response()->json([
            'token' => $user->createToken('app')->plainTextToken,
            'pessoa' => EuController::resumo($pessoa),
        ]);
    }

    /**
     * Pergunta ao Google se a credencial é verdadeira e para o nosso app.
     *
     * @return array{sub: string, email: string}
     */
    private function verificar(string $credencial, string $clientId): array
    {
        $resposta = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credencial]);
        $perfil = $resposta->successful() ? $resposta->json() : null;

        $valido = is_array($perfil)
            && ($perfil['aud'] ?? null) === $clientId
            && filter_var($perfil['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($perfil['sub'])
            && ! empty($perfil['email']);

        if (! $valido) {
            throw ValidationException::withMessages(['credencial' => ['Não foi possível confirmar o login com o Google. Tente de novo.']]);
        }

        return ['sub' => (string) $perfil['sub'], 'email' => (string) $perfil['email']];
    }

    /** Já vinculado pelo Google; senão, o login com esse e-mail; senão, a pessoa cadastrada com ele. */
    private function usuarioPara(string $googleId, string $email): User
    {
        $vinculado = User::where('google_id', $googleId)->first();

        if ($vinculado) {
            return $vinculado;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if ($user->google_id && $user->google_id !== $googleId) {
                throw ValidationException::withMessages(['credencial' => [self::NAO_CADASTRADO]]);
            }

            $user->forceFill(['google_id' => $googleId])->save();

            return $user;
        }

        // Convidada por e-mail, mas ainda sem login: o Google prova o e-mail e o login nasce aqui.
        $candidatas = Pessoa::withoutGlobalScopes()->where('email', $email)->whereNull('user_id')->get();

        if ($candidatas->count() !== 1) {
            throw ValidationException::withMessages(['credencial' => [self::NAO_CADASTRADO]]);
        }

        return DB::transaction(function () use ($candidatas, $googleId, $email) {
            $pessoa = $candidatas->first();
            $user = User::create(['name' => $pessoa->nome, 'email' => $email, 'password' => null]);
            $user->forceFill(['google_id' => $googleId])->save();
            $pessoa->update(['user_id' => $user->id]);

            return $user;
        });
    }
}
