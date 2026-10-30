import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';
import App from '../src/App.vue';
import { criarRoteador } from '../src/router.js';
import { useModo } from '../src/stores/modo.js';
import { useSessao } from '../src/stores/sessao.js';
import { LIDER, LIDER_DE_DOIS, PESSOA, botao, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  setActivePinia(createPinia());
});

const comPessoa = (pessoa) => {
  localStorage.setItem('escala.token', 'tok');
  useSessao().pessoa = pessoa;
};

describe('modo (voluntário ou líder)', () => {
  it('quem não lidera ninguém nunca fica no modo líder, mesmo que o aparelho lembre', () => {
    localStorage.setItem('escala.modo', 'lider');
    comPessoa(PESSOA);

    const modo = useModo();

    expect(modo.ehLider).toBe(false);
    expect(modo.modo).toBe('voluntario');
    expect(modo.ministerio).toBeNull();
  });

  it('quem lidera pode alternar e o aparelho lembra', () => {
    comPessoa(LIDER);
    const modo = useModo();

    expect(modo.modo).toBe('voluntario'); // começa como voluntário

    modo.usar('lider');

    expect(modo.modo).toBe('lider');
    expect(localStorage.getItem('escala.modo')).toBe('lider');
    expect(modo.ministerio.nome).toBe('Louvor');
  });

  it('com dois ministérios usa o primeiro e deixa escolher o outro', () => {
    comPessoa(LIDER_DE_DOIS);
    const modo = useModo();

    expect(modo.ministerio.id).toBe(1);

    modo.escolherMinisterio(2);

    expect(modo.ministerio.nome).toBe('Mídia');
    expect(localStorage.getItem('escala.ministerio')).toBe('2');
  });

  it('um ministério lembrado que a pessoa já não lidera é ignorado', () => {
    localStorage.setItem('escala.ministerio', '99');
    comPessoa(LIDER);

    expect(useModo().ministerio.id).toBe(1);
  });

  it('o número de trocas vem da API e falha em silêncio', async () => {
    comPessoa(LIDER);
    simularApi({ 'GET /ministerios/1/trocas': { corpo: { total_abertas: 3, trocas: [] } } });
    const modo = useModo();

    await modo.atualizarTrocas();
    expect(modo.trocasAbertas).toBe(3);

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    await modo.atualizarTrocas();
    expect(modo.trocasAbertas).toBe(3); // mantém o último número
  });
});

describe('rotas do líder', () => {
  it('quem não lidera não abre a área do líder', async () => {
    comPessoa(PESSOA);
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/lider');

    expect(roteador.currentRoute.value.name).toBe('inicio');
  });

  it('o líder abre direto pelo endereço e o modo acompanha a tela', async () => {
    comPessoa(LIDER);
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/lider/trocas');
    expect(roteador.currentRoute.value.name).toBe('lider-trocas');
    expect(useModo().modo).toBe('lider');

    await roteador.push('/minha-escala');
    expect(useModo().modo).toBe('voluntario');
  });

  it('o perfil serve aos dois modos e não muda o modo', async () => {
    comPessoa(LIDER);
    useModo().usar('lider');
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/perfil');

    expect(useModo().modo).toBe('lider');
  });
});

describe('casca com o modo', () => {
  const rotas = { 'GET /ministerios/1/trocas': { corpo: { total_abertas: 2, trocas: [] } }, 'GET /ministerios/1/escalas': { corpo: { escalas: [] } } };

  it('quem não lidera não vê o seletor de modo', async () => {
    simularApi(rotas);
    const { tela } = await montarTela(App, { entrada: '/' });

    expect(tela.find('[role=tablist]').exists()).toBe(false);
    expect(tela.findAll('nav a').map((a) => a.text())).toEqual(['Início', 'Disponibilidade', 'Minha escala', 'Perfil']);
  });

  it('o líder vê o seletor e as abas de cada modo, com o número de trocas', async () => {
    simularApi(rotas);
    const { tela, roteador } = await montarTela(App, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    expect(tela.find('[role=tablist]').exists()).toBe(true);
    expect(tela.findAll('nav a').map((a) => a.text().replace(/\d+$/, ''))).toEqual(['Escalas', 'Equipe', 'Trocas', 'Relatório', 'Perfil']);
    expect(tela.find('.selo').text()).toBe('2');

    await botao(tela, 'Voluntário').trigger('click');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('inicio'));
    await esperar();

    expect(tela.findAll('nav a').map((a) => a.text())).toEqual(['Início', 'Disponibilidade', 'Minha escala', 'Perfil']);
    expect(tela.find('.selo').exists()).toBe(false);
  });

  it('no modo voluntário, o seletor leva de volta ao líder', async () => {
    simularApi(rotas);
    const { tela, roteador } = await montarTela(App, { entrada: '/', pessoa: LIDER });

    await botao(tela, 'Líder').trigger('click');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('lider-escalas'));
  });
});
