<?php

namespace App\Console\Commands;

use App\Models\Igreja;
use App\Models\Pessoa;
use App\Models\User;
use App\Support\IgrejaAtual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CriarIgreja extends Command
{
    protected $signature = 'escala:criar-igreja
        {nome : Nome da igreja}
        {adm_nome : Nome da pessoa que será Adm}
        {adm_email : E-mail de login do Adm}
        {--senha= : Senha do Adm (se omitida, será pedida)}';

    protected $description = 'Cria uma igreja e o primeiro Adm dela';

    public function handle(IgrejaAtual $igrejaAtual): int
    {
        $senha = $this->option('senha') ?: $this->secret('Senha do Adm (mínimo 8 caracteres)');

        $validador = Validator::make(
            ['adm_email' => $this->argument('adm_email'), 'senha' => $senha],
            ['adm_email' => ['required', 'email', 'unique:users,email'], 'senha' => ['required', 'string', 'min:8']],
            ['adm_email.unique' => 'Já existe um login com esse e-mail.', 'senha.min' => 'A senha precisa ter pelo menos 8 caracteres.'],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $igreja = DB::transaction(function () use ($igrejaAtual, $senha) {
            $igreja = Igreja::create(['nome' => $this->argument('nome')]);

            $user = User::create([
                'name' => $this->argument('adm_nome'),
                'email' => $this->argument('adm_email'),
                'password' => $senha,
            ]);

            // A pessoa nasce dentro da igreja recém-criada.
            $igrejaAtual->definir($igreja->id);

            try {
                Pessoa::create([
                    'user_id' => $user->id,
                    'nome' => $this->argument('adm_nome'),
                    'email' => $this->argument('adm_email'),
                    'adm' => true,
                ]);
            } finally {
                $igrejaAtual->definir(null);
            }

            return $igreja;
        });

        $this->info("Igreja \"{$igreja->nome}\" criada, com {$this->argument('adm_nome')} como Adm.");

        return self::SUCCESS;
    }
}
