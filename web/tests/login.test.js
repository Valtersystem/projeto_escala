import { beforeEach, describe, expect, it, vi } from 'vitest';
import Login from '../src/views/Login.vue';
import Convite from '../src/views/Convite.vue';
import { PESSOA, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

async function preencher(tela, campos) {
  for (const [id, valor] of Object.entries(campos)) await tela.find(`#${id}`).setValue(valor);
}

describe('tela de login', () => {
  it('o botão só libera com e-mail e senha', async () => {
    const { tela } = await montarTela(Login, { logada: false });

    expect(tela.find('button[type=submit]').attributes('disabled')).toBeDefined();

    await preencher(tela, { email: 'helena@exemplo.test', senha: 'segredo123' });

    expect(tela.find('button[type=submit]').attributes('disabled')).toBeUndefined();
  });

  it('entra, manda e-mail e senha para a API e vai para o início', async () => {
    const fetch = simularApi({ 'POST /login': { corpo: { token: 'tok-1', pessoa: PESSOA } } });
    const { tela, roteador } = await montarTela(Login, { logada: false });

    await preencher(tela, { email: '  helena@exemplo.test ', senha: 'segredo123' });
    await tela.find('form').trigger('submit');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('inicio'));

    expect(fetch.chamadas[0].corpo).toEqual({ email: 'helena@exemplo.test', senha: 'segredo123' });
    expect(localStorage.getItem('escala.token')).toBe('tok-1');
  });

  it('mostra o erro da API e continua na tela', async () => {
    simularApi({ 'POST /login': { status: 422, corpo: { errors: { email: ['E-mail ou senha incorretos.'] } } } });
    const { tela, roteador } = await montarTela(Login, { logada: false });

    await preencher(tela, { email: 'a@b.c', senha: 'errada123' });
    await tela.find('form').trigger('submit');
    await esperar();

    expect(tela.find('[role=alert]').text()).toBe('E-mail ou senha incorretos.');
    expect(roteador.currentRoute.value.name).toBe('login');
    expect(tela.find('button[type=submit]').text()).toBe('Entrar');
  });

  it('volta para a tela que a pessoa tentava abrir', async () => {
    simularApi({ 'POST /login': { corpo: { token: 'tok-1', pessoa: PESSOA } } });
    const { tela, roteador } = await montarTela(Login, { entrada: '/login?volta=/minha-escala', logada: false });

    await preencher(tela, { email: 'a@b.c', senha: 'segredo123' });
    await tela.find('form').trigger('submit');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('minha-escala'));
  });

  it.each(['//malicioso.com', 'https://malicioso.com', 'javascript:alert(1)'])('ignora destino de fora do app: %s', async (volta) => {
    simularApi({ 'POST /login': { corpo: { token: 'tok-1', pessoa: PESSOA } } });
    const { tela, roteador } = await montarTela(Login, { entrada: `/login?volta=${encodeURIComponent(volta)}`, logada: false });
    const trocar = vi.spyOn(roteador, 'replace');

    await preencher(tela, { email: 'a@b.c', senha: 'segredo123' });
    await tela.find('form').trigger('submit');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('inicio'));

    // O destino de fora não chega a ser usado: o login manda direto para o início.
    expect(trocar).toHaveBeenCalledWith({ name: 'inicio' });
  });

  it('explica que a senha esquecida se resolve com o líder', async () => {
    const { tela } = await montarTela(Login, { logada: false });

    expect(tela.text()).toContain('Peça um novo convite ao seu líder');
  });
});

describe('tela de convite', () => {
  const abrir = () => montarTela(Convite, { entrada: '/convite/token-do-convite', logada: false });

  it('só libera com senha de 8 caracteres ou mais e as duas iguais', async () => {
    const { tela } = await abrir();
    const botao = () => tela.find('button[type=submit]');

    await preencher(tela, { senha: 'curta', confirmacao: 'curta' });
    expect(tela.text()).toContain('Use pelo menos 8 caracteres.');
    expect(botao().attributes('disabled')).toBeDefined();

    await preencher(tela, { senha: 'senha-boa-123', confirmacao: 'senha-diferente' });
    expect(tela.text()).toContain('As senhas não são iguais.');
    expect(botao().attributes('disabled')).toBeDefined();

    await preencher(tela, { confirmacao: 'senha-boa-123' });
    expect(botao().attributes('disabled')).toBeUndefined();
  });

  it('cria a senha com o token do link e entra', async () => {
    const fetch = simularApi({ 'POST /convites/aceitar': { corpo: { token: 'tok-9', pessoa: PESSOA } } });
    const { tela, roteador, sessao } = await abrir();

    await preencher(tela, { senha: 'senha-boa-123', confirmacao: 'senha-boa-123' });
    await tela.find('form').trigger('submit');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('inicio'));

    expect(fetch.chamadas[0].corpo).toEqual({ token: 'token-do-convite', senha: 'senha-boa-123' });
    expect(sessao.autenticada).toBe(true);
  });

  it('convite inválido ou vencido mostra a mensagem da API', async () => {
    simularApi({ 'POST /convites/aceitar': { status: 422, corpo: { errors: { token: ['Convite inválido ou expirado. Peça um novo ao líder.'] } } } });
    const { tela, roteador } = await abrir();

    await preencher(tela, { senha: 'senha-boa-123', confirmacao: 'senha-boa-123' });
    await tela.find('form').trigger('submit');
    await esperar();

    expect(tela.find('[role=alert]').text()).toBe('Convite inválido ou expirado. Peça um novo ao líder.');
    expect(roteador.currentRoute.value.name).toBe('convite');
  });
});
