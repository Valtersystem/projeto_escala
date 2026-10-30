import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useSessao } from '../src/stores/sessao.js';
import { PESSOA, simularApi } from './ajudantes.js';

describe('sessão', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('entrar guarda o token e a pessoa, e lembra no aparelho', async () => {
    simularApi({ 'POST /login': { corpo: { token: 'tok-1', pessoa: PESSOA } } });
    const sessao = useSessao();

    await sessao.entrar('helena@exemplo.test', 'segredo123');

    expect(sessao.autenticada).toBe(true);
    expect(sessao.pessoa.nome).toBe('Helena Costa');
    expect(sessao.nomeCurto).toBe('Helena');
    expect(sessao.iniciais).toBe('HC');
    expect(localStorage.getItem('escala.token')).toBe('tok-1');
  });

  it('login errado não cria sessão e repassa a mensagem', async () => {
    simularApi({ 'POST /login': { status: 422, corpo: { errors: { email: ['E-mail ou senha incorretos.'] } } } });
    const sessao = useSessao();

    await expect(sessao.entrar('a@b.c', 'x')).rejects.toThrow('E-mail ou senha incorretos.');

    expect(sessao.autenticada).toBe(false);
    expect(localStorage.getItem('escala.token')).toBeNull();
  });

  it('quem aceita o convite já fica logado', async () => {
    const fetch = simularApi({ 'POST /convites/aceitar': { corpo: { token: 'tok-2', pessoa: PESSOA } } });
    const sessao = useSessao();

    await sessao.aceitarConvite('convite-abc', 'minha-senha-1');

    expect(fetch.chamadas[0].corpo).toEqual({ token: 'convite-abc', senha: 'minha-senha-1' });
    expect(sessao.autenticada).toBe(true);
  });

  it('retoma a sessão guardada e confere com a API', async () => {
    localStorage.setItem('escala.token', 'tok-guardado');
    simularApi({ 'GET /eu': { corpo: { pessoa: PESSOA } } });
    const sessao = useSessao();

    expect(sessao.autenticada).toBe(true);
    expect(sessao.pessoa).toBeNull();

    await sessao.carregar();

    expect(sessao.pessoa.email).toBe('helena@exemplo.test');
  });

  it('token vencido derruba a sessão', async () => {
    localStorage.setItem('escala.token', 'tok-vencido');
    simularApi({ 'GET /eu': { status: 401, corpo: { message: 'Unauthenticated.' } } });
    const sessao = useSessao();

    await sessao.carregar();

    expect(sessao.autenticada).toBe(false);
    expect(localStorage.getItem('escala.token')).toBeNull();
  });

  it('acesso desativado (403) também derruba a sessão', async () => {
    localStorage.setItem('escala.token', 'tok');
    simularApi({ 'GET /eu': { status: 403, corpo: { message: 'Este acesso está desativado.' } } });
    const sessao = useSessao();

    await sessao.carregar();

    expect(sessao.autenticada).toBe(false);
  });

  it('sem rede, a sessão guardada não é perdida', async () => {
    localStorage.setItem('escala.token', 'tok');
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    const sessao = useSessao();

    await expect(sessao.carregar()).rejects.toThrow(/sem conexão/i);

    expect(sessao.autenticada).toBe(true);
  });

  it('sair funciona mesmo sem rede', async () => {
    simularApi({ 'POST /login': { corpo: { token: 'tok-1', pessoa: PESSOA } } });
    const sessao = useSessao();
    await sessao.entrar('a@b.c', 'x');

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    await sessao.sair();

    expect(sessao.autenticada).toBe(false);
    expect(sessao.pessoa).toBeNull();
    expect(localStorage.getItem('escala.token')).toBeNull();
  });
});
