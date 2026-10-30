import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Inicio from '../src/views/Inicio.vue';
import MinhaEscala from '../src/views/MinhaEscala.vue';
import Perfil from '../src/views/Perfil.vue';
import { useMensagens } from '../src/stores/mensagens.js';
import { PESSOA, botao, esperar, montarTela, simularApi } from './ajudantes.js';

const HOJE = '2026-11-10T12:00:00';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  // Só a data é falsa; as promessas e os temporizadores continuam reais.
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date(HOJE));
});

afterEach(() => vi.useRealTimers());

const turno = (id, data, extra = {}) => ({
  id, data, inicio: '08:00', fim: '11:30', culto: 'Culto da Manhã', ministerio: 'Louvor',
  posto: { id: 1, nome: 'Som' }, banda: { id: 2, nome: 'Banda 2' },
  funcoes: [{ id: 1, nome: 'Voz' }], choque: [], troca_aberta_id: null, ...extra,
});

const choque = { turno_id: 900, ministerio: 'Mídia', posto: 'Câmera', data: '2026-11-22', inicio: '09:00', fim: '10:00' };

const MINHA_ESCALA = {
  turnos: [
    turno(1, '2026-11-01'), // já passou
    turno(2, '2026-11-15'),
    turno(3, '2026-11-22', { choque: [choque], troca_aberta_id: 5 }),
    turno(4, '2026-12-06', { funcoes: [], culto: null }),
  ],
};

describe('minha escala', () => {
  const abrir = (extra = {}) => {
    const fetch = simularApi({ 'GET /minha-escala': { corpo: MINHA_ESCALA }, ...extra });
    return montarTela(MinhaEscala, { entrada: '/minha-escala' }).then((r) => ({ ...r, fetch }));
  };

  it('agrupa por mês e esconde os turnos que já passaram', async () => {
    const { tela } = await abrir();

    expect(tela.text()).toContain('novembro de 2026');
    expect(tela.text()).toContain('dezembro de 2026');
    expect(tela.findAll('article.turno')).toHaveLength(3);
    expect(tela.text()).toContain('Ver 1 turno(s) passado(s)');

    await botao(tela, 'Ver 1 turno(s) passado(s)').trigger('click');

    expect(tela.findAll('article.turno')).toHaveLength(4);
    expect(tela.text()).toContain('Esconder turnos passados');
  });

  it('mostra funções, "função a combinar", banda e ministério', async () => {
    const { tela } = await abrir();
    const [primeiro, , ultimo] = tela.findAll('article.turno');

    expect(primeiro.text()).toContain('Culto da Manhã');
    expect(primeiro.text()).toContain('Louvor · 08:00 às 11:30 · Som · Banda 2');
    expect(primeiro.text()).toContain('Voz');
    expect(ultimo.text()).toContain('função a combinar');
    expect(ultimo.text()).toContain('Som'); // sem culto, o título é o posto
  });

  it('avisa o choque com o outro turno', async () => {
    const { tela } = await abrir();
    const segundo = tela.findAll('article.turno')[1];

    expect(segundo.text()).toContain('⚠ Choque de escala em 22 de novembro: Mídia · Câmera 09:00–10:00. Avise o líder.');
  });

  it('nunca mostra vagas', async () => {
    const { tela } = await abrir();

    expect(tela.text()).not.toMatch(/vaga/i);
  });

  it('pede substituição com o motivo e a observação e avisa o líder', async () => {
    const { tela, fetch } = await abrir({ 'POST /turnos/2/trocas': { status: 201, corpo: { troca: { id: 9 } } } });

    await botao(tela, 'Não vou poder').trigger('click');
    expect(tela.text()).toContain('Você continua na escala até o líder resolver');

    await tela.find('#motivo').setValue('Trabalho');
    await tela.find('#obs').setValue('Plantão no hospital');
    await botao(tela, 'Enviar pedido ao líder').trigger('click');
    await esperar();

    const envio = fetch.chamadas.find((c) => c.metodo === 'POST');
    expect(envio.caminho).toBe('/turnos/2/trocas');
    expect(envio.corpo).toEqual({ motivo: 'Trabalho', observacao: 'Plantão no hospital' });
    expect(useMensagens().texto).toContain('Pedido enviado ao líder');
    expect(fetch.chamadas.filter((c) => c.caminho === '/minha-escala')).toHaveLength(2); // recarregou
  });

  it('observação vazia vai como null e erro da API aparece na mensagem', async () => {
    const { tela, fetch } = await abrir({ 'POST /turnos/2/trocas': { status: 422, corpo: { message: 'Você já tem um pedido aberto nesse turno.' } } });

    await botao(tela, 'Não vou poder').trigger('click');
    await botao(tela, 'Enviar pedido ao líder').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'POST').corpo).toEqual({ motivo: 'Viagem', observacao: null });
    expect(useMensagens().texto).toBe('Você já tem um pedido aberto nesse turno.');
    expect(useMensagens().tipo).toBe('erro');
  });

  it('turno com pedido aberto mostra o estado e deixa cancelar', async () => {
    const { tela, fetch } = await abrir({ 'DELETE /trocas/5': { status: 204 } });
    const comPedido = tela.findAll('article.turno')[1];

    expect(comPedido.text()).toContain('Substituição pedida');
    expect(comPedido.text()).not.toContain('Não vou poder');

    await botao(comPedido, 'Cancelar pedido').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'DELETE' && c.caminho === '/trocas/5')).toBe(true);
    expect(useMensagens().texto).toContain('Pedido cancelado');
  });

  it('turno passado não oferece "Não vou poder"', async () => {
    const { tela } = await abrir();

    await botao(tela, 'Ver 1 turno(s) passado(s)').trigger('click');
    const passado = tela.findAll('article.turno')[0];

    expect(passado.text()).toContain('01');
    expect(passado.text()).not.toContain('Não vou poder');
  });

  it('sem turnos mostra a explicação', async () => {
    simularApi({ 'GET /minha-escala': { corpo: { turnos: [] } } });
    const { tela } = await montarTela(MinhaEscala, { entrada: '/minha-escala' });

    expect(tela.text()).toContain('Nenhum turno por enquanto');
  });

  it('falha ao carregar mostra o erro', async () => {
    simularApi({ 'GET /minha-escala': { status: 500, corpo: { message: 'Erro interno.' } } });
    const { tela } = await montarTela(MinhaEscala, { entrada: '/minha-escala' });

    expect(tela.text()).toContain('Erro interno.');
    expect(botao(tela, 'Tentar de novo')).toBeTruthy();
  });
});

describe('início', () => {
  const abertas = {
    escalas: [
      { id: 7, ministerio: { id: 1, nome: 'Louvor' }, mes: '2026-12', prazo: '2026-11-25', prazo_encerrado: false, total_de_turnos: 8, marquei: 0 },
      { id: 8, ministerio: { id: 2, nome: 'Mídia' }, mes: '2026-12', prazo: '2026-11-20', prazo_encerrado: false, total_de_turnos: 4, marquei: 3 },
      { id: 9, ministerio: { id: 3, nome: 'Recepção' }, mes: '2026-12', prazo: '2026-11-05', prazo_encerrado: true, total_de_turnos: 4, marquei: 0 },
    ],
  };

  const avisos = {
    nao_lidos: 1,
    avisos: [
      { id: 2, texto: 'Escala publicada: 15/11 às 08:00 — você fica em Voz.', lido: false, criado_em: '2026-11-09T10:00:00Z' },
      { id: 1, texto: 'Aviso antigo.', lido: true, criado_em: '2026-11-01T10:00:00Z' },
    ],
  };

  const abrir = (extra = {}) => {
    const fetch = simularApi({
      'GET /minha-escala': { corpo: { turnos: MINHA_ESCALA.turnos } },
      'GET /avisos': { corpo: avisos },
      'GET /escalas/abertas': { corpo: abertas },
      ...extra,
    });
    return montarTela(Inicio, { entrada: '/' }).then((r) => ({ ...r, fetch }));
  };

  it('cumprimenta pelo nome e lista os ministérios', async () => {
    const { tela } = await abrir();

    expect(tela.text()).toContain('Olá, Helena');
    expect(tela.text()).toContain('Louvor · Mídia');
  });

  it('mostra o que falta responder, com o estado de cada escala', async () => {
    const { tela } = await abrir();
    const cartoes = tela.findAll('a.escala');

    expect(cartoes).toHaveLength(3);
    expect(cartoes[0].text()).toContain('Louvor · dezembro de 2026');
    expect(cartoes[0].text()).toContain('Falta responder');
    expect(cartoes[0].text()).toContain('Responder até 25 de novembro');
    expect(cartoes[1].text()).toContain('3 marcado(s)');
    expect(cartoes[2].text()).toContain('Prazo encerrado');
    expect(cartoes[0].attributes('href')).toBe('/disponibilidade/7');
  });

  it('lista só os próximos 3 turnos, sem os que já passaram', async () => {
    const { tela } = await abrir();
    const turnos = tela.findAll('.turno');

    expect(turnos).toHaveLength(3); // o de 01/11 já passou
    expect(turnos[0].text()).toContain('15');
    expect(turnos[1].text()).toContain('Choque'); // o de 22/11 tem choque
  });

  it('destaca o aviso novo e marca todos como lidos', async () => {
    const { tela, fetch } = await abrir({ 'POST /avisos/lidos': { corpo: { nao_lidos: 0 } } });

    expect(tela.findAll('.aviso.novo')).toHaveLength(1);

    await botao(tela, 'Marcar como lidos').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'POST' && c.caminho === '/avisos/lidos')).toBe(true);
    expect(tela.findAll('.aviso.novo')).toHaveLength(0);
    expect(botao(tela, 'Marcar como lidos')).toBeUndefined();
  });

  it('sem nada para responder nem turnos explica o que esperar', async () => {
    simularApi({
      'GET /minha-escala': { corpo: { turnos: [] } },
      'GET /avisos': { corpo: { nao_lidos: 0, avisos: [] } },
      'GET /escalas/abertas': { corpo: { escalas: [] } },
    });
    const { tela } = await montarTela(Inicio, { entrada: '/' });

    expect(tela.text()).toContain('Nada para responder agora');
    expect(tela.text()).toContain('Nenhum turno confirmado');
    expect(tela.text()).toContain('Sem avisos por enquanto');
  });

  it('se alguma chamada falha, mostra o erro e deixa tentar de novo', async () => {
    simularApi({
      'GET /minha-escala': { corpo: { turnos: [] } },
      'GET /avisos': { corpo: { nao_lidos: 0, avisos: [] } },
      'GET /escalas/abertas': { status: 500, corpo: { message: 'Erro interno.' } },
    });
    const { tela } = await montarTela(Inicio, { entrada: '/' });

    expect(tela.text()).toContain('Erro interno.');
    expect(botao(tela, 'Tentar de novo')).toBeTruthy();
  });
});

describe('perfil', () => {
  it('mostra os dados e quem é líder de quê', async () => {
    const { tela } = await montarTela(Perfil, { entrada: '/perfil' });

    expect(tela.text()).toContain('Helena Costa');
    expect(tela.text()).toContain('helena@exemplo.test');
    expect(tela.text()).toContain('Igreja Exemplo');
    expect(tela.text()).not.toContain('Líder');
  });

  it('marca o ministério em que a pessoa é líder', async () => {
    const { tela, sessao } = await montarTela(Perfil, { entrada: '/perfil' });
    sessao.pessoa = { ...PESSOA, ministerios: [{ id: 1, nome: 'Louvor', lider: true }] };
    await esperar();

    expect(tela.text()).toContain('Líder');
  });

  it('sair encerra a sessão e volta ao login', async () => {
    const fetch = simularApi({ 'POST /logout': { corpo: { message: 'Sessão encerrada.' } } });
    const { tela, roteador, sessao } = await montarTela(Perfil, { entrada: '/perfil' });

    await botao(tela, 'Sair').trigger('click');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('login'));

    expect(fetch.chamadas.some((c) => c.caminho === '/logout')).toBe(true);
    expect(sessao.autenticada).toBe(false);
    expect(localStorage.getItem('escala.token')).toBeNull();
  });
});
