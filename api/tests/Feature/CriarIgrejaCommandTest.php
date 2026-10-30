<?php

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriarIgrejaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_t73_cria_a_igreja_e_o_adm_que_consegue_entrar(): void
    {
        $this->artisan('escala:criar-igreja', [
            'nome' => 'Igreja Central',
            'adm_nome' => 'Valter Santos',
            'adm_email' => 'valter@teste.com',
            '--senha' => 'senha-forte-1',
        ])->assertSuccessful();

        $igreja = Igreja::firstWhere('nome', 'Igreja Central');
        $pessoa = Pessoa::withoutGlobalScopes()->firstWhere('email', 'valter@teste.com');

        $this->assertNotNull($igreja);
        $this->assertSame($igreja->id, $pessoa->igreja_id);
        $this->assertTrue($pessoa->adm);
        $this->assertTrue($pessoa->ativo);

        $this->postJson('/api/login', ['email' => 'valter@teste.com', 'senha' => 'senha-forte-1'])
            ->assertOk()
            ->assertJsonPath('pessoa.adm', true)
            ->assertJsonPath('pessoa.igreja.nome', 'Igreja Central');
    }

    public function test_recusa_senha_curta_sem_criar_nada(): void
    {
        $this->artisan('escala:criar-igreja', [
            'nome' => 'Igreja Central',
            'adm_nome' => 'Valter',
            'adm_email' => 'valter@teste.com',
            '--senha' => '123',
        ])->assertFailed();

        $this->assertSame(0, Igreja::count());
        $this->assertSame(0, User::count());
    }

    public function test_recusa_email_que_ja_tem_login(): void
    {
        User::create(['name' => 'Existente', 'email' => 'valter@teste.com', 'password' => 'senha-forte-1']);

        $this->artisan('escala:criar-igreja', [
            'nome' => 'Outra Igreja',
            'adm_nome' => 'Valter',
            'adm_email' => 'valter@teste.com',
            '--senha' => 'senha-forte-1',
        ])->assertFailed();

        $this->assertSame(0, Igreja::count());
    }

    public function test_a_igreja_atual_nao_fica_definida_depois_do_comando(): void
    {
        $this->artisan('escala:criar-igreja', [
            'nome' => 'Igreja Central',
            'adm_nome' => 'Valter',
            'adm_email' => 'valter@teste.com',
            '--senha' => 'senha-forte-1',
        ])->assertSuccessful();

        $this->assertNull(app(\App\Support\IgrejaAtual::class)->id());
    }
}
