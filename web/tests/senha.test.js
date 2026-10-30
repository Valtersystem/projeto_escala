import { beforeEach, describe, expect, it, vi } from 'vitest';
import EsqueciSenha from '../src/views/EsqueciSenha.vue';
import RedefinirSenha from '../src/views/RedefinirSenha.vue';
import Login from '../src/views/Login.vue';
import { botao, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

describe('esqueci a senha', () => {
  it('o login tem o caminho para a recuperação', async () => {
    simularApi();
    const { tela } = await montarTela(Login, { entrada: '/login', logada: false });

    expect(tela.find('.esqueceu a').attributes('href')).toBe('/esqueci-senha');
  });

  it('pede o link e mostra a mesma confirmação sem dizer se o e-mail existe', async () => {
    const fetch = simularApi({ 'POST /esqueci-senha': { corpo: { mensagem: 'ok' } } });
    const { tela } = await montarTela(EsqueciSenha, { entrada: '/esqueci-senha', logada: false });

    expect(tela.find('button[type=submit]').attributes('disabled')).toBeDefined();
    await tela.find('#email').setValue('  helena@exemplo.test ');
    await tela.find('form').trigger('submit');
    await esperar();

    expect(fetch.chamadas[0].corpo).toEqual({ email: 'helena@exemplo.test' });
    expect(tela.text()).toContain('Se este e-mail tem cadastro');
    expect(tela.find('form').exists()).toBe(false);
  });

  it('erro de rede ou limite mostra o aviso e deixa tentar de novo', async () => {
    simularApi({ 'POST /esqueci-senha': { status: 429, corpo: { message: 'Muitas tentativas.' } } });
    const { tela } = await montarTela(EsqueciSenha, { entrada: '/esqueci-senha', logada: false });

    await tela.find('#email').setValue('helena@exemplo.test');
    await tela.find('form').trigger('submit');
    await esperar();

    expect(tela.find('[role=alert]').text()).toBe('Muitas tentativas.');
    expect(tela.find('form').exists()).toBe(true);
  });
});

describe('redefinir a senha', () => {
  const abrir = (rotas = {}) => {
    const fetch = simularApi(rotas);
    return montarTela(RedefinirSenha, { entrada: '/redefinir-senha/tok123?email=helena%40exemplo.test', logada: false }).then((m) => ({ ...m, fetch }));
  };

  it('manda o e-mail do link, o token e a senha nova, e não entra sozinho', async () => {
    const { tela, fetch } = await abrir({ 'POST /redefinir-senha': { corpo: { mensagem: 'ok' } } });

    expect(tela.text()).toContain('helena@exemplo.test');
    await tela.find('#senha').setValue('nova-senha-123');
    await tela.find('#confirmacao').setValue('nova-senha-123');
    await tela.find('form').trigger('submit');
    await esperar();

    expect(fetch.chamadas[0].corpo).toEqual({ email: 'helena@exemplo.test', token: 'tok123', senha: 'nova-senha-123' });
    expect(tela.text()).toContain('Senha alterada');
    expect(localStorage.getItem('escala.token')).toBeNull();
  });

  it('só habilita com 8+ caracteres e senhas iguais', async () => {
    const { tela } = await abrir();
    const enviar = () => tela.find('button[type=submit]');

    await tela.find('#senha').setValue('1234567');
    await tela.find('#confirmacao').setValue('1234567');
    expect(enviar().attributes('disabled')).toBeDefined();
    expect(tela.text()).toContain('pelo menos 8 caracteres');

    await tela.find('#senha').setValue('12345678');
    expect(enviar().attributes('disabled')).toBeDefined();
    expect(tela.text()).toContain('As senhas não são iguais');

    await tela.find('#confirmacao').setValue('12345678');
    expect(enviar().attributes('disabled')).toBeUndefined();
  });

  it('link vencido mostra o erro da API e oferece pedir outro', async () => {
    const { tela } = await abrir({ 'POST /redefinir-senha': { status: 422, corpo: { message: 'x', errors: { token: ['Link inválido ou expirado. Peça um novo.'] } } } });

    await tela.find('#senha').setValue('nova-senha-123');
    await tela.find('#confirmacao').setValue('nova-senha-123');
    await tela.find('form').trigger('submit');
    await esperar();

    expect(tela.find('[role=alert]').text()).toContain('Link inválido ou expirado');
    expect(tela.find('.esqueceu a').attributes('href')).toBe('/esqueci-senha');
    expect(botao(tela, 'Salvar nova senha')).toBeTruthy();
  });
});
