import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';
import { criarRoteador } from '../src/router.js';
import { PESSOA, simularApi } from './ajudantes.js';

describe('rotas e proteção de acesso', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('quem não entrou vai para o login e a tela pedida fica guardada', async () => {
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/minha-escala');

    expect(roteador.currentRoute.value.name).toBe('login');
    expect(roteador.currentRoute.value.query.volta).toBe('/minha-escala');
  });

  it('na página inicial não guarda "volta" à toa', async () => {
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/');

    expect(roteador.currentRoute.value.name).toBe('login');
    expect(roteador.currentRoute.value.query.volta).toBeUndefined();
  });

  it('o convite abre sem login', async () => {
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/convite/abc123');

    expect(roteador.currentRoute.value.name).toBe('convite');
    expect(roteador.currentRoute.value.params.token).toBe('abc123');
  });

  it('quem já entrou confere o token e vai direto para a tela pedida', async () => {
    localStorage.setItem('escala.token', 'tok');
    const fetch = simularApi({ 'GET /eu': { corpo: { pessoa: PESSOA } } });
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/minha-escala');

    expect(roteador.currentRoute.value.name).toBe('minha-escala');
    expect(fetch.chamadas.filter((c) => c.caminho === '/eu')).toHaveLength(1);
  });

  it('quem já entrou não vê mais o login', async () => {
    localStorage.setItem('escala.token', 'tok');
    simularApi({ 'GET /eu': { corpo: { pessoa: PESSOA } } });
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/login');

    expect(roteador.currentRoute.value.name).toBe('inicio');
  });

  it('token vencido leva ao login', async () => {
    localStorage.setItem('escala.token', 'tok-vencido');
    simularApi({ 'GET /eu': { status: 401, corpo: { message: 'Unauthenticated.' } } });
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/disponibilidade');

    expect(roteador.currentRoute.value.name).toBe('login');
  });

  it('sem rede, quem tem token continua e a tela mostra o erro', async () => {
    localStorage.setItem('escala.token', 'tok');
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/minha-escala');

    expect(roteador.currentRoute.value.name).toBe('minha-escala');
  });

  it('endereço desconhecido volta para o início (e daí para o login se for o caso)', async () => {
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/qualquer/coisa');

    expect(roteador.currentRoute.value.name).toBe('login');
  });
});
