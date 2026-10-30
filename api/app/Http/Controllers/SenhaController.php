<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/** RN-126 e RN-127: recuperação de senha por e-mail. */
class SenhaController extends Controller
{
    /**
     * A resposta é sempre a mesma, exista o e-mail ou não, para ninguém descobrir quem tem login.
     */
    public function esqueci(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $dados['email'])->first();

        if ($user && $user->pessoa?->ativo) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json(['mensagem' => 'Se este e-mail tem cadastro, enviamos um link para escolher uma nova senha.']);
    }

    public function redefinir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'senha' => ['required', 'string', 'min:8'],
        ]);

        $status = Password::reset(
            ['email' => $dados['email'], 'token' => $dados['token'], 'password' => $dados['senha']],
            function (User $user, string $senha) {
                $user->forceFill(['password' => $senha])->save();
                // A senha mudou: encerra as sessões anteriores (RN-75).
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['Link inválido ou expirado. Peça um novo.']]);
        }

        return response()->json(['mensagem' => 'Senha alterada. Entre com a nova senha.']);
    }
}
