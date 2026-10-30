<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login por e-mail e senha (D-16). A mensagem é a mesma para e-mail
     * inexistente, senha errada e login só com Google, para não revelar quais
     * e-mails existem.
     */
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $user = User::where('email', $dados['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($dados['senha'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['E-mail ou senha incorretos.']]);
        }

        $pessoa = $user->pessoa;

        if (! $pessoa || ! $pessoa->ativo) {
            throw ValidationException::withMessages(['email' => ['Este acesso está desativado. Fale com o líder.']]);
        }

        return response()->json([
            'token' => $user->createToken('app')->plainTextToken,
            'pessoa' => EuController::resumo($pessoa),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
