import { beforeEach, describe, expect, it, vi } from 'vitest';
import LiderRelatorio from '../src/views/LiderRelatorio.vue';
import LiderTrocas from '../src/views/LiderTrocas.vue';
import { useMensagens } from '../src/stores/mensagens.js';
import { useModo } from '../src/stores/modo.js';
import { LIDER, botao, botaoExato, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

const turno = (extra = {}) => ({ id: 10, data: '2026-11-08', inicio: '08:00', fim: '11:30', posto: { id: 1, nome: 'Som' }, banda: null, culto: 'Culto da Manhã', vagas: 2, versao: 1, ...extra });

const troca = (id, nome, extra = {}) => ({
  id, estado: 'aberto', resolucao: null, motivo: 'Viagem', observacao: 'Viajo no sábado e só volto na segunda.',
  pessoa: { id: id * 10, nome }, turno: turno(), marcaram: 2, ...extra,
});

const candidato = (id, nome, extra = {}) => ({ pessoa_id: id, nome, marcou: true, turnos_no_total: 1, fora_da_banda: false, choques: [], ...extra });

/** API de trocas com estado: resolver tira o pedido da lista de abertos. */
function apiDeTrocas({ trocas = [troca(1, 'Helena Costa')], candidatos = [candidato(2, 'Bruno Souza')], extra = {} } = {}) {
  const estado = { trocas: [...trocas] };
  const resolver = (id) => () => { estado.trocas = estado.trocas.filter((t) => t.id !== id); return { corpo: { troca: {} } }; };

  const rotas = {
    'GET /ministerios/1/trocas': () => ({ corpo: { total_abertas: estado.trocas.length, trocas: estado.trocas } }),
    'GET /trocas/1/candidatos': { corpo: { candidatos } },
    'POST /trocas/1/manter': resolver(1),
    'POST /trocas/1/vaga-aberta': resolver(1),
    'POST /trocas/1/substituir': resolver(1),
    ...extra,
  };

  return { estado, rotas };
}

const abrirTrocas = async (opcoes) => {
  const { estado, rotas } = apiDeTrocas(opcoes);
  const fetch = simularApi(rotas);
  const montada = await montarTela(LiderTrocas, { entrada: '/lider/trocas', pessoa: LIDER, modo: 'lider' });
  return { ...montada, fetch, estado };
};

describe('pedidos de troca do líder', () => {
  it('lista cada pedido com quem pediu, o turno, o motivo e quantos marcaram', async () => {
    const { tela } = await abrirTrocas({ trocas: [troca(1, 'Helena Costa'), troca(2, 'Bruno Souza', { observacao: null, motivo: 'Trabalho' })] });
    const cartoes = tela.findAll('article.troca');

    expect(cartoes).toHaveLength(2);
    expect(cartoes[0].text()).toContain('Helena Costa não vai poder');
    expect(cartoes[0].text()).toContain('Culto da Manhã · 08:00 às 11:30');
    expect(cartoes[0].text()).toContain('dom, 08 de novembro');
    expect(cartoes[0].text()).toContain('Viagem');
    expect(cartoes[0].text()).toContain('“Viajo no sábado e só volto na segunda.”');
    expect(cartoes[0].text()).toContain('2 pessoa(s) marcaram disponibilidade neste turno');
    expect(cartoes[1].text()).toContain('Trabalho');
    expect(cartoes[1].text()).not.toContain('“');
  });

  it('atualiza o número da aba com o total de pedidos abertos', async () => {
    await abrirTrocas({ trocas: [troca(1, 'Helena Costa'), troca(2, 'Bruno Souza')] });

    expect(useModo().trocasAbertas).toBe(2);
  });

  it('sem pedidos mostra que não há nada pendente', async () => {
    const { tela } = await abrirTrocas({ trocas: [] });

    expect(tela.text()).toContain('Nada pendente');
    expect(useModo().trocasAbertas).toBe(0);
  });

  it('manter: a pessoa continua e o pedido some da lista', async () => {
    const { tela, fetch } = await abrirTrocas();

    await botao(tela, 'Manter').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'POST' && c.caminho === '/trocas/1/manter')).toBe(true);
    expect(useMensagens().texto).toBe('Helena segue no turno.');
    expect(tela.findAll('article.troca')).toHaveLength(0);
    expect(useModo().trocasAbertas).toBe(0);
  });

  it('resolver abre os candidatos, com o que marcou, a carga e o aviso de choque', async () => {
    const { tela } = await abrirTrocas({
      candidatos: [
        candidato(2, 'Bruno Souza'),
        candidato(3, 'Carla Dias', { marcou: false, turnos_no_total: 4, fora_da_banda: true, choques: [{ turno_id: 90, ministerio: 'Mídia', posto: 'Câmera', data: '2026-11-08', inicio: '09:00', fim: '10:00' }] }),
      ],
    });

    await botao(tela, 'Resolver').trigger('click');
    await esperar();

    const linhas = tela.findAll('.candidato');
    expect(linhas).toHaveLength(2);
    expect(linhas[0].text()).toContain('marcou disponibilidade neste turno');
    expect(linhas[0].text()).toContain('Livre');
    expect(linhas[1].text()).toContain('não marcou este dia · 4 turno(s) no total · fora da banda fixa');
    expect(linhas[1].text()).toContain('⚠ já escalado em Mídia · Câmera 09:00–10:00');
    expect(botao(linhas[1], 'Escolher mesmo assim')).toBeTruthy();
  });

  it('escolher passa a vaga para a pessoa e avisa quem entrou no lugar de quem', async () => {
    const { tela, fetch } = await abrirTrocas();

    await botao(tela, 'Resolver').trigger('click');
    await esperar();
    await botaoExato(tela, 'Escolher').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.caminho === '/trocas/1/substituir').corpo).toEqual({ pessoa_id: 2 });
    expect(useMensagens().texto).toBe('Bruno entrou no lugar de Helena.');
    expect(tela.findAll('article.troca')).toHaveLength(0);
  });

  it('dá para deixar a vaga em aberto', async () => {
    const { tela, fetch } = await abrirTrocas();

    await botao(tela, 'Resolver').trigger('click');
    await esperar();
    await botao(tela, 'Deixar a vaga em aberto').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'POST' && c.caminho === '/trocas/1/vaga-aberta')).toBe(true);
    expect(useMensagens().texto).toBe('Vaga aberta de novo nesse turno.');
  });

  it('sem candidatos explica e deixa só a vaga em aberto', async () => {
    const { tela } = await abrirTrocas({ candidatos: [] });

    await botao(tela, 'Resolver').trigger('click');
    await esperar();

    expect(tela.text()).toContain('Ninguém disponível');
    expect(botao(tela, 'Deixar a vaga em aberto')).toBeTruthy();
  });

  it('pedido já resolvido por outra pessoa vira mensagem e a lista recarrega', async () => {
    const { tela, fetch } = await abrirTrocas({ extra: { 'POST /trocas/1/manter': { status: 422, corpo: { message: 'Este pedido já foi resolvido.' } } } });
    const antes = fetch.chamadas.filter((c) => c.caminho === '/ministerios/1/trocas').length;

    await botao(tela, 'Manter').trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('Este pedido já foi resolvido.');
    expect(fetch.chamadas.filter((c) => c.caminho === '/ministerios/1/trocas').length).toBe(antes + 1);
  });

  it('falha ao carregar mostra o erro', async () => {
    simularApi({ 'GET /ministerios/1/trocas': { status: 500, corpo: { message: 'Erro interno.' } } });
    const { tela } = await montarTela(LiderTrocas, { entrada: '/lider/trocas', pessoa: LIDER, modo: 'lider' });

    expect(tela.text()).toContain('Erro interno.');
  });
});

describe('relatório do líder', () => {
  const relatorio = {
    mes: null, total_de_turnos: 10, total_de_vagas: 20, vagas_preenchidas_pct: 83, turnos_sem_ninguem: 2, voluntarios_ativos: 7,
    turnos_por_pessoa: [
      { pessoa_id: 1, nome: 'Helena Costa', turnos: 4, acima_do_limite: true },
      { pessoa_id: 2, nome: 'Bruno Souza', turnos: 2, acima_do_limite: false },
      { pessoa_id: 3, nome: 'Carla Dias', turnos: 0, acima_do_limite: false },
    ],
    onde_aperta: [1, 2, 3, 4, 5, 6].map((i) => turno({ id: 100 + i, data: `2026-11-${String(i).padStart(2, '0')}` })),
  };

  const abrir = (extra = {}) => {
    const fetch = simularApi({
      'GET /ministerios/1/relatorio': { corpo: relatorio },
      'GET /ministerios/1/escalas': { corpo: { escalas: [{ mes: '2026-12', status: 'aberta' }, { mes: '2026-11', status: 'publicada' }, { mes: '2027-01', status: 'rascunho' }] } },
      ...extra,
    });
    return montarTela(LiderRelatorio, { entrada: '/lider/relatorio', pessoa: LIDER, modo: 'lider' }).then((r) => ({ ...r, fetch }));
  };

  it('mostra o resumo e os turnos por pessoa', async () => {
    const { tela } = await abrir();

    const numeros = tela.findAll('.estatistica').map((e) => e.text());
    expect(numeros[0]).toContain('83%');
    expect(numeros[1]).toContain('2');
    expect(numeros[2]).toContain('7');
    expect(tela.findAll('.barra').map((b) => b.text())).toEqual(['Helena4', 'Bruno2', 'Carla0']);
  });

  it('destaca quem passou do limite e explica a cor', async () => {
    const { tela } = await abrir();

    expect(tela.findAll('.trilho i')[0].classes()).toContain('acima');
    expect(tela.findAll('.trilho i')[1].classes()).not.toContain('acima');
    expect(tela.text()).toContain('acima do limite de turnos por mês');
  });

  it('"onde aperta" mostra só os cinco primeiros', async () => {
    const { tela } = await abrir();

    expect(tela.findAll('.aperta')).toHaveLength(5);
    expect(tela.text()).toContain('ninguém marcou disponibilidade');
  });

  it('o período só oferece meses que já saíram do rascunho e filtra pela API', async () => {
    const { tela, fetch } = await abrir();

    expect(tela.findAll('#mes option').map((o) => o.text())).toEqual(['Todos os meses', 'novembro de 2026', 'dezembro de 2026']);

    await tela.find('#mes').setValue('2026-11');
    await esperar();

    expect(fetch.chamadas.some((c) => c.caminho === '/ministerios/1/relatorio?mes=2026-11')).toBe(true);
  });

  it('sem turnos explica o que falta', async () => {
    const { tela } = await abrir({ 'GET /ministerios/1/relatorio': { corpo: { ...relatorio, total_de_turnos: 0, onde_aperta: [] } } });

    expect(tela.text()).toContain('Sem dados ainda');
    expect(tela.findAll('.estatistica')).toHaveLength(0);
  });

  it('falha ao carregar mostra o erro', async () => {
    simularApi({ 'GET /ministerios/1/relatorio': { status: 500, corpo: { message: 'Erro interno.' } }, 'GET /ministerios/1/escalas': { corpo: { escalas: [] } } });
    const { tela } = await montarTela(LiderRelatorio, { entrada: '/lider/relatorio', pessoa: LIDER, modo: 'lider' });

    expect(tela.text()).toContain('Erro interno.');
  });
});
