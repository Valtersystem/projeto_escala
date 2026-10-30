import { beforeEach, describe, expect, it, vi } from 'vitest';
import Entrar from '../src/views/Entrar.vue';
import { botao, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

const IGREJA = { igreja: { nome: 'Igreja Exemplo' }, ministerios: [{ id: 1, nome: 'Louvor' }, { id: 2, nome: 'Mídia' }] };

const abrir = async (rotas = {}) => {
  const fetch = simularApi({ 'GET /entrada/igreja-exemplo': { corpo: IGREJA }, ...rotas });
  const montada = await montarTela(Entrar, { entrada: '/entrar/igreja-exemplo', logada: false });
  return { ...montada, fetch };
};

const preencher = async (tela, extra = {}) => {
  await tela.find('#e-nome').setValue('  Carla Dias ');
  await tela.find('#e-email').setValue('carla@exemplo.test');
  await tela.find('#e-tel').setValue(extra.telefone ?? '');
  await tela.find('#e-min').setValue(String(extra.ministerio ?? 2));
  await tela.find('#e-senha').setValue(extra.senha ?? 'minha-senha-1');
};

describe('pedido de entrada do voluntário', () => {
  it('abre sem login, mostra a igreja e os ministérios', async () => {
    const { tela } = await abrir();

    expect(tela.text()).toContain('Igreja Exemplo');
    expect(tela.findAll('#e-min option').map((o) => o.text())).toEqual(['Escolha um ministério', 'Louvor', 'Mídia']);
  });

  it('envia o pedido com os dados limpos e mostra a confirmação sem entrar no app', async () => {
    const { tela, fetch } = await abrir({ 'POST /entrada/igreja-exemplo': { status: 201, corpo: { mensagem: 'ok' } } });

    await preencher(tela);
    await tela.find('form').trigger('submit');
    await esperar();

    const enviado = fetch.chamadas.find((c) => c.metodo === 'POST');
    expect(enviado.corpo).toEqual({ nome: 'Carla Dias', email: 'carla@exemplo.test', telefone: null, senha: 'minha-senha-1', ministerio_id: 2 });
    expect(tela.text()).toContain('Pedido enviado');
    expect(localStorage.getItem('escala.token')).toBeNull();
  });

  it('só habilita o botão com tudo preenchido e senha de 8 caracteres', async () => {
    const { tela } = await abrir();

    expect(botao(tela, 'Pedir para entrar').attributes('disabled')).toBeDefined();
    await preencher(tela, { senha: '1234567' });
    expect(botao(tela, 'Pedir para entrar').attributes('disabled')).toBeDefined();
    expect(tela.text()).toContain('pelo menos 8 caracteres');
    await tela.find('#e-senha').setValue('12345678');
    expect(botao(tela, 'Pedir para entrar').attributes('disabled')).toBeUndefined();
  });

  it('e-mail repetido: mostra o erro da API e deixa corrigir', async () => {
    const { tela } = await abrir({ 'POST /entrada/igreja-exemplo': { status: 422, corpo: { message: 'x', errors: { email: ['Este e-mail já está cadastrado. Peça um novo link de acesso ao líder.'] } } } });

    await preencher(tela);
    await tela.find('form').trigger('submit');
    await esperar();

    expect(tela.find('[role="alert"]').text()).toContain('já está cadastrado');
    expect(tela.find('#e-nome').exists()).toBe(true);
  });

  it('com um ministério só, ele já vem escolhido', async () => {
    const { tela } = await abrir({ 'GET /entrada/igreja-exemplo': { corpo: { ...IGREJA, ministerios: [{ id: 7, nome: 'Louvor' }] } } });

    expect(tela.find('#e-min').element.value).toBe('7');
  });

  it('link de igreja que não existe mostra "Link inválido"', async () => {
    simularApi({ 'GET /entrada/errada': { status: 404, corpo: { message: 'Não encontrado.' } } });
    const { tela } = await montarTela(Entrar, { entrada: '/entrar/errada', logada: false });

    expect(tela.text()).toContain('Link inválido');
    expect(tela.find('form').exists()).toBe(false);
  });
});
