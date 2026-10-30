import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

  it('oferece a recuperação da senha por e-mail', async () => {
    const { tela } = await montarTela(Login, { logada: false });

    expect(tela.text()).toContain('Esqueceu a senha?');
    expect(tela.text()).not.toContain('Peça um novo convite');
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

describe('login com Google', () => {
  const comGoogle = () => {
    vi.stubEnv('VITE_GOOGLE_CLIENT_ID', 'cliente-123');
    const id = { initialize: vi.fn(), renderButton: vi.fn() };
    window.google = { accounts: { id } };
    return id;
  };

  afterEach(() => {
    vi.unstubAllEnvs();
    delete window.google;
  });

  it('sem o ID do cliente, não mostra nada do Google', async () => {
    const { tela } = await montarTela(Login, { logada: false });

    expect(tela.find('.google').exists()).toBe(false);
  });

  it('com o ID, desenha o botão do Google', async () => {
    const id = comGoogle();
    const { tela } = await montarTela(Login, { logada: false });

    expect(tela.find('.google').exists()).toBe(true);
    expect(id.initialize.mock.calls[0][0].client_id).toBe('cliente-123');
    expect(id.renderButton).toHaveBeenCalledTimes(1);
  });

  it('a credencial do Google vai para a API, guarda a sessão e leva ao app', async () => {
    const id = comGoogle();
    const fetch = simularApi({ 'POST /login/google': { corpo: { token: 'tok-google', pessoa: PESSOA } } });
    const { roteador, sessao } = await montarTela(Login, { logada: false });

    await id.initialize.mock.calls[0][0].callback({ credential: 'jwt-do-google' });
    await esperar();

    expect(fetch.chamadas[0].corpo).toEqual({ credencial: 'jwt-do-google' });
    expect(sessao.token).toBe('tok-google');
    expect(roteador.currentRoute.value.name).toBe('inicio');
  });

  it('e-mail que ninguém cadastrou: mostra o erro e continua no login', async () => {
    const id = comGoogle();
    simularApi({ 'POST /login/google': { status: 422, corpo: { message: 'x', errors: { credencial: ['Este e-mail do Google não está cadastrado na igreja.'] } } } });
    const { tela, roteador, sessao } = await montarTela(Login, { logada: false });

    await id.initialize.mock.calls[0][0].callback({ credential: 'jwt' });
    await esperar();

    expect(tela.find('[role=alert]').text()).toContain('não está cadastrado');
    expect(sessao.autenticada).toBe(false);
    expect(roteador.currentRoute.value.name).toBe('login');
  });

  it('se o Google não carregar, o login por e-mail continua disponível', async () => {
    vi.stubEnv('VITE_GOOGLE_CLIENT_ID', 'cliente-123');
    const erros = vi.spyOn(console, 'error').mockImplementation(() => {});
    const anexar = vi.spyOn(document.head, 'appendChild').mockImplementation((script) => { setTimeout(() => script.onerror?.(), 0); return script; });
    const { tela } = await montarTela(Login, { logada: false });
    await new Promise((r) => setTimeout(r, 10));

    expect(tela.find('#email').exists()).toBe(true);
    expect(tela.find('[role=alert]').exists()).toBe(false);
    expect(erros).not.toHaveBeenCalled();
    anexar.mockRestore();
    erros.mockRestore();
  });
});
