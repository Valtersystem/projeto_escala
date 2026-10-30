<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Models\Igreja;
use App\Models\Ministerio;
use App\Models\Pessoa;
use App\Models\PedidoEntrada;
use App\Models\User;
use App\Support\Avisos;
use App\Support\IgrejaAtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * D-15: o voluntário pede para entrar pelo link público da igreja; o login só existe depois que
 * o líder do ministério aprova.
 */
class PedidoEntradaController extends Controller
{
    /** Público: nome da igreja e os ministérios que o voluntário pode escolher. */
    public function igreja(string $codigo): JsonResponse
    {
        $igreja = $this->igrejaPeloCodigo($codigo);

        app(IgrejaAtual::class)->definir($igreja->id);

        return response()->json([
            'igreja' => ['nome' => $igreja->nome],
            'ministerios' => Ministerio::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /** Público: registra o pedido. Nada de login ainda. */
    public function store(Request $request, string $codigo): JsonResponse
    {
        $igreja = $this->igrejaPeloCodigo($codigo);
        app(IgrejaAtual::class)->definir($igreja->id);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:32'],
            'senha' => ['required', 'string', 'min:8'],
            'ministerio_id' => ['required', 'integer', Rule::exists('ministerios', 'id')->where('igreja_id', $igreja->id)],
        ]);

        $email = mb_strtolower(trim($dados['email']));

        if (Pessoa::where('email', $email)->exists() || User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['Este e-mail já está cadastrado. Peça um novo link de acesso ao líder.']]);
        }

        if (PedidoEntrada::where('email', $email)->where('estado', 'pendente')->exists()) {
            throw ValidationException::withMessages(['email' => ['Já existe um pedido com este e-mail esperando o líder.']]);
        }

        $pedido = PedidoEntrada::create([
            'ministerio_id' => $dados['ministerio_id'],
            'nome' => trim($dados['nome']),
            'email' => $email,
            'telefone' => $dados['telefone'] ?? null,
            'senha_hash' => Hash::make($dados['senha']),
        ]);

        $lider = Ministerio::find($dados['ministerio_id'])?->lider_id;

        if ($lider) {
            Avisos::para($lider, Avisos::pedidoDeEntrada($pedido->nome, Ministerio::find($dados['ministerio_id'])->nome));
        }

        return response()->json(['mensagem' => 'Pedido enviado. Quando o líder aprovar, entre com o e-mail e a senha que você escolheu.'], 201);
    }

    /** Pedidos esperando o líder (ou o Adm). */
    public function index(Ministerio $ministerio): JsonResponse
    {
        Gate::authorize('gerir-equipe', $ministerio);

        $pedidos = PedidoEntrada::where('ministerio_id', $ministerio->id)->where('estado', 'pendente')->orderBy('id')->get();

        return response()->json([
            'total' => $pedidos->count(),
            'pedidos' => $pedidos->map(fn (PedidoEntrada $p) => [
                'id' => $p->id, 'nome' => $p->nome, 'email' => $p->email, 'telefone' => $p->telefone, 'pedido_em' => $p->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Cria o login e a pessoa e já coloca na equipe do ministério. */
    public function aprovar(Request $request, PedidoEntrada $pedido): JsonResponse
    {
        Gate::authorize('gerir-equipe', $pedido->ministerio);
        $this->garantirPendente($pedido);

        $ator = $request->attributes->get('pessoa');

        $pessoa = DB::transaction(function () use ($pedido, $ator) {
            if (Pessoa::where('email', $pedido->email)->exists() || User::where('email', $pedido->email)->exists()) {
                throw ValidationException::withMessages(['email' => ['Este e-mail já foi cadastrado por outro caminho. Recuse o pedido e use o cadastro da equipe.']]);
            }

            // O hash já veio pronto do pedido; o cast "hashed" do User não o refaz.
            $user = User::create(['name' => $pedido->nome, 'email' => $pedido->email, 'password' => $pedido->senha_hash]);
            $pessoa = Pessoa::create(['user_id' => $user->id, 'nome' => $pedido->nome, 'email' => $pedido->email, 'telefone' => $pedido->telefone]);
            $pessoa->ministerios()->attach($pedido->ministerio_id);

            $pedido->forceFill(['estado' => 'aprovado', 'resolvido_por' => $ator->id, 'resolvido_em' => now()])->save();

            return $pessoa;
        });

        Registro::feito('pedido.aprovado', "Aprovou a entrada de {$pessoa->nome}", $pedido->ministerio_id, $pedido->id);

        return response()->json(['pessoa' => ['id' => $pessoa->id, 'nome' => $pessoa->nome]], 201);
    }

    public function recusar(Request $request, PedidoEntrada $pedido): JsonResponse
    {
        Gate::authorize('gerir-equipe', $pedido->ministerio);
        $this->garantirPendente($pedido);

        $pedido->forceFill(['estado' => 'recusado', 'resolvido_por' => $request->attributes->get('pessoa')->id, 'resolvido_em' => now()])->save();

        Registro::feito('pedido.recusado', "Recusou a entrada de {$pedido->nome}", $pedido->ministerio_id, $pedido->id);

        return response()->json(['ok' => true]);
    }

    private function garantirPendente(PedidoEntrada $pedido): void
    {
        if ($pedido->estado !== 'pendente') {
            throw ValidationException::withMessages(['pedido' => ['Este pedido já foi resolvido.']]);
        }
    }

    private function igrejaPeloCodigo(string $codigo): Igreja
    {
        return Igreja::where('codigo', $codigo)->firstOrFail();
    }
}
