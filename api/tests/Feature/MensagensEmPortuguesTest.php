<?php

namespace Tests\Feature;

use Tests\TestCase;

class MensagensEmPortuguesTest extends TestCase
{
    /** T-181 */
    public function test_erros_de_validacao_vem_em_portugues_com_o_nome_do_campo_legivel(): void
    {
        $this->postJson('/api/convites/aceitar', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Preencha o campo convite.')
            ->assertJsonPath('errors.senha.0', 'Preencha o campo senha.');
    }

    /** T-181 */
    public function test_tamanho_minimo_da_senha_usa_a_mensagem_em_portugues(): void
    {
        $this->postJson('/api/convites/aceitar', ['token' => 'x', 'senha' => '123'])
            ->assertStatus(422)
            ->assertJsonPath('errors.senha.0', 'O campo senha precisa ter pelo menos 8 caracteres.');
    }
}
