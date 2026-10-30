import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ErroDaApi, api, configurarCliente } from '../src/api/cliente.js';
import { simularApi } from './ajudantes.js';

describe('cliente da API', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
    configurarCliente({ token: () => null, naoAutorizado: () => {} });
  });

  it('manda o token no cabeçalho e o corpo em JSON', async () => {
    configurarCliente({ token: () => 'abc123' });
    const fetch = simularApi({ 'POST /login': { corpo: { ok: true } } });

    await api.post('/login', { email: 'a@b.c' });

    const { cabecalhos, corpo } = fetch.chamadas[0];
    expect(cabecalhos.Authorization).toBe('Bearer abc123');
    expect(cabecalhos['Content-Type']).toBe('application/json');
    expect(corpo).toEqual({ email: 'a@b.c' });
  });

  it('não manda Authorization quando não há token', async () => {
    const fetch = simularApi({ 'GET /eu': { corpo: {} } });

    await api.get('/eu');

    expect(fetch.chamadas[0].cabecalhos.Authorization).toBeUndefined();
  });

  it('resposta 204 volta como null', async () => {
    simularApi({ 'DELETE /postos/1': { status: 204 } });

    expect(await api.delete('/postos/1')).toBeNull();
  });

  it('erro de validação traz a primeira mensagem e os erros por campo', async () => {
    simularApi({
      'POST /login': { status: 422, corpo: { message: 'The given data was invalid.', errors: { email: ['E-mail ou senha incorretos.'] } } },
    });

    const erro = await api.post('/login', {}).catch((e) => e);

    expect(erro).toBeInstanceOf(ErroDaApi);
    expect(erro.status).toBe(422);
    expect(erro.message).toBe('E-mail ou senha incorretos.');
    expect(erro.doCampo('email')).toBe('E-mail ou senha incorretos.');
  });

  it('erro de regra usa a mensagem da API', async () => {
    simularApi({ 'PUT /turnos/1/disponibilidade': { status: 422, corpo: { message: 'O prazo para responder esta escala terminou. Fale com o líder.' } } });

    const erro = await api.put('/turnos/1/disponibilidade', { marcado: true }).catch((e) => e);

    expect(erro.message).toBe('O prazo para responder esta escala terminou. Fale com o líder.');
  });

  it('401 avisa a sessão; 422 de login errado não', async () => {
    const naoAutorizado = vi.fn();
    configurarCliente({ naoAutorizado });
    simularApi({
      'GET /eu': { status: 401, corpo: { message: 'Unauthenticated.' } },
      'POST /login': { status: 422, corpo: { errors: { email: ['x'] } } },
    });

    await api.get('/eu').catch(() => {});
    expect(naoAutorizado).toHaveBeenCalledTimes(1);

    await api.post('/login', {}).catch(() => {});
    expect(naoAutorizado).toHaveBeenCalledTimes(1);
  });

  it('sem conexão vira uma mensagem que a pessoa entende', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

    const erro = await api.get('/eu').catch((e) => e);

    expect(erro.status).toBe(0);
    expect(erro.message).toMatch(/sem conexão/i);
  });

  it('resposta que não é JSON não derruba o cliente', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500, json: async () => { throw new Error('html'); } }));

    const erro = await api.get('/eu').catch((e) => e);

    expect(erro.status).toBe(500);
    expect(erro.message).toBe('Não foi possível completar a ação.');
  });
});
