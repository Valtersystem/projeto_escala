import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LiderEscalas from '../src/views/LiderEscalas.vue';
import LiderEscala from '../src/views/LiderEscala.vue';
import { useMensagens } from '../src/stores/mensagens.js';
import { LIDER, LIDER_DE_DOIS, botao, botaoExato, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date('2026-11-10T12:00:00'));
});

afterEach(() => vi.useRealTimers());

const escala = (extra = {}) => ({ id: 5, ministerio_id: 1, mes: '2026-11', status: 'rascunho', prazo: '2026-11-13', versao: 1, total_de_turnos: 2, ...extra });

const turno = (id, data, extra = {}) => ({
  id, escala_id: 5, data, inicio: '08:00', fim: '11:30', posto: { id: 1, nome: 'Som' }, banda: { id: 2, nome: 'Banda 2' },
  culto: 'Culto da Manhã', vagas: 2, versao: 1, confirmados_total: 0, marcaram_total: 0, confirmados: [], ...extra,
});

describe('lista de escalas do líder', () => {
  const baseApi = (extra = {}) => simularApi({
    'GET /ministerios/1/trocas': { corpo: { total_abertas: 0, trocas: [] } },
    'GET /ministerios/1/escalas': { corpo: { escalas: [escala({ id: 5, mes: '2026-11', status: 'aberta' }), escala({ id: 4, mes: '2026-10', status: 'publicada', total_de_turnos: 8 })] } },
    ...extra,
  });

  it('mostra cada mês com o estado, os turnos e o prazo (só da que está recolhendo)', async () => {
    baseApi();
    const { tela } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });
    let cartoes = tela.findAll('a.escala');

    expect(cartoes).toHaveLength(1); // outubro já passou e está publicada: fica em "anteriores"
    expect(cartoes[0].text()).toContain('novembro de 2026');
    expect(cartoes[0].text()).toContain('Recolhendo');
    expect(cartoes[0].text()).toContain('responder até 13 de novembro');
    expect(cartoes[0].attributes('href')).toBe('/lider/escalas/5');

    await botao(tela, 'Ver escalas anteriores (1)').trigger('click');
    cartoes = tela.findAll('a.escala');

    expect(cartoes).toHaveLength(2);
    expect(cartoes[1].text()).toContain('Publicada');
    expect(cartoes[1].text()).not.toContain('responder até');
  });

  it('avisa quando há pedidos de substituição esperando', async () => {
    baseApi({ 'GET /ministerios/1/trocas': { corpo: { total_abertas: 2, trocas: [] } } });
    const { tela } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    expect(tela.find('a.trocas').text()).toContain('2 pedido(s) de substituição');
  });

  it('com dois ministérios, deixa trocar e recarrega a lista do escolhido', async () => {
    const fetch = baseApi({
      'GET /ministerios/2/escalas': { corpo: { escalas: [] } },
      'GET /ministerios/2/trocas': { corpo: { total_abertas: 0, trocas: [] } },
    });
    const { tela } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER_DE_DOIS, modo: 'lider' });

    expect(tela.findAll('.chip')).toHaveLength(2);

    await botao(tela, 'Mídia').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.caminho === '/ministerios/2/escalas')).toBe(true);
    expect(tela.text()).toContain('Nenhuma escala ainda');
  });

  it('um ministério só: sem seletor', async () => {
    baseApi();
    const { tela } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    expect(tela.findAll('.chip')).toHaveLength(0);
  });

  it('cria a escala de um mês livre e vai para ela', async () => {
    const fetch = baseApi({ 'POST /ministerios/1/escalas': { status: 201, corpo: { escala: escala({ id: 9, mes: '2026-12' }) } } });
    const { tela, roteador } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    await botao(tela, 'Abrir escala de um mês').trigger('click');

    // Novembro e outubro já têm escala; o primeiro mês livre a partir de hoje é dezembro.
    const opcoes = tela.findAll('#mes option').map((o) => o.text());
    expect(opcoes[0]).toBe('dezembro de 2026');
    expect(opcoes).not.toContain('novembro de 2026');

    await botao(tela, 'Criar escala').trigger('click');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('lider-escala'));

    expect(fetch.chamadas.find((c) => c.metodo === 'POST').corpo).toEqual({ mes: '2026-12', prazo: null });
    expect(roteador.currentRoute.value.params.escalaId).toBe('9');
  });

  it('manda o prazo quando o líder escolhe um', async () => {
    const fetch = baseApi({ 'POST /ministerios/1/escalas': { status: 201, corpo: { escala: escala({ id: 9 }) } } });
    const { tela } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    await botao(tela, 'Abrir escala de um mês').trigger('click');
    await tela.find('#prazo').setValue('2026-11-25');
    await botao(tela, 'Criar escala').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'POST').corpo.prazo).toBe('2026-11-25');
  });

  it('erro da API ao criar vira mensagem e a folha continua aberta', async () => {
    baseApi({ 'POST /ministerios/1/escalas': { status: 422, corpo: { message: 'Esse mês já tem escala.' } } });
    const { tela, roteador } = await montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    await botao(tela, 'Abrir escala de um mês').trigger('click');
    await botao(tela, 'Criar escala').trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('Esse mês já tem escala.');
    expect(roteador.currentRoute.value.name).not.toBe('lider-escala');
  });
});

/** A API de mentira de uma escala: as ações mudam o estado, como a de verdade. */
function apiDaEscala({ status = 'rascunho', turnos = [turno(10, '2026-11-08'), turno(11, '2026-11-15')], extra = {} } = {}) {
  const estado = { status, turnos, prazo: '2026-11-13', versao: 1 };
  const trocarStatus = (novo) => () => { estado.status = novo; estado.versao += 1; return { corpo: { escala: {} } }; };

  const rotas = {
    'GET /escalas/5': () => ({ corpo: { escala: escala({ status: estado.status, prazo: estado.prazo, versao: estado.versao, total_de_turnos: estado.turnos.length }), turnos: estado.turnos } }),
    'POST /escalas/5/liberar': trocarStatus('aberta'),
    'POST /escalas/5/publicar': trocarStatus('publicada'),
    'POST /escalas/5/reabrir': trocarStatus('aberta'),
    'PATCH /escalas/5': (corpo) => { estado.prazo = corpo.prazo; estado.versao += 1; return { corpo: { escala: {} } }; },
    'GET /escalas/5/publicacao': { corpo: { total_de_turnos: 2, incompletos: 0, sem_funcao: 0, choques: [] } },
    'GET /ministerios/1/postos': { corpo: { postos: [{ id: 1, nome: 'Som', vagas: 2 }] } },
    'GET /ministerios/1/bandas': { corpo: { bandas: [{ id: 2, nome: 'Banda 2' }] } },
    'GET /ministerios/1/funcoes': { corpo: { funcoes: [{ id: 1, nome: 'Voz' }, { id: 2, nome: 'Guitarra' }] } },
    ...extra,
  };

  return { estado, rotas };
}

const abrirEscala = async (opcoes) => {
  const { estado, rotas } = apiDaEscala(opcoes);
  const fetch = simularApi(rotas);
  const montada = await montarTela(LiderEscala, { props: { escalaId: '5' }, entrada: '/lider/escalas/5', pessoa: LIDER, modo: 'lider' });
  return { ...montada, fetch, estado, rotas };
};

describe('escala do líder: ações por estado', () => {
  it('rascunho: gerar, avulso e liberar; mostra só as vagas de cada turno', async () => {
    const { tela } = await abrirEscala();

    expect(botao(tela, 'Gerar dos cultos')).toBeTruthy();
    expect(botao(tela, 'Turno avulso')).toBeTruthy();
    expect(tela.text()).toContain('Rascunho');
    expect(tela.text()).toContain('2 vaga(s)');
    expect(tela.text()).not.toContain('marcaram');
    expect(botao(tela, 'Liberar para a equipe').attributes('disabled')).toBeUndefined();
  });

  it('sem turnos não deixa liberar e explica', async () => {
    const { tela } = await abrirEscala({ turnos: [] });

    expect(tela.text()).toContain('Nenhum turno ainda');
    expect(botao(tela, 'Liberar para a equipe').attributes('disabled')).toBeDefined();
  });

  it('liberar chama a API e a tela passa a mostrar a escala recolhendo', async () => {
    const { tela, fetch } = await abrirEscala();

    await botao(tela, 'Liberar para a equipe').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'POST' && c.caminho === '/escalas/5/liberar')).toBe(true);
    expect(tela.text()).toContain('Recolhendo');
    expect(botao(tela, 'Publicar escala')).toBeTruthy();
    expect(useMensagens().texto).toContain('Escala liberada');
  });

  it('aberta: mostra quantos marcaram, quantos estão confirmados e quem', async () => {
    const turnos = [
      turno(10, '2026-11-08', { marcaram_total: 3, confirmados_total: 1, confirmados: [{ pessoa_id: 1, nome: 'Helena Costa', sem_funcao: false }, { pessoa_id: 2, nome: 'Bruno Souza', sem_funcao: true }] }),
      turno(11, '2026-11-15'),
    ];
    const { tela } = await abrirEscala({ status: 'aberta', turnos });
    const linhas = tela.findAll('button.turno');

    expect(linhas[0].text()).toContain('3 marcaram · 1 de 2 confirmados');
    expect(linhas[0].findAll('.inicial').map((i) => i.text())).toEqual(['HC', 'BS']);
    expect(linhas[0].findAll('.inicial')[1].classes()).toContain('mudo'); // sem função
    expect(linhas[1].text()).toContain('Vazio');
    expect(tela.text()).toContain('Responder até 13 de novembro');
  });

  it('publicar mostra a prévia com o que está torto e só então publica', async () => {
    const { tela, fetch } = await abrirEscala({
      status: 'aberta',
      extra: {
        'GET /escalas/5/publicacao': {
          corpo: {
            total_de_turnos: 2, incompletos: 1, sem_funcao: 2,
            choques: [{ pessoa: { id: 1, nome: 'Helena' }, turno: { ministerio: 'Louvor', posto: 'Som', inicio: '08:00', fim: '11:30' }, com: { ministerio: 'Mídia', posto: 'Câmera', inicio: '09:00', fim: '10:00' } }],
          },
        },
      },
    });

    await botao(tela, 'Publicar escala').trigger('click');
    await esperar();

    expect(tela.text()).toContain('2 pessoa(s) vão receber "função a combinar"');
    expect(tela.text()).toContain('Helena: Louvor · Som 08:00–11:30 e Mídia · Câmera 09:00–10:00');
    expect(tela.text()).toContain('1 turno(s) ainda sem todas as vagas preenchidas');
    expect(fetch.chamadas.some((c) => c.caminho === '/escalas/5/publicar')).toBe(false);

    await botaoExato(tela, 'Publicar').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'POST' && c.caminho === '/escalas/5/publicar')).toBe(true);
    expect(tela.text()).toContain('Publicada');
    expect(useMensagens().texto).toContain('Escala publicada');
  });

  it('prévia sem problemas diz que está tudo certo', async () => {
    const { tela } = await abrirEscala({ status: 'aberta' });

    await botao(tela, 'Publicar escala').trigger('click');
    await esperar();

    expect(tela.text()).toContain('Tudo certo');
  });

  it('publicada: só dá para reabrir, e não oferece gerar nem turno avulso', async () => {
    const { tela, fetch } = await abrirEscala({ status: 'publicada' });

    expect(botao(tela, 'Gerar dos cultos')).toBeUndefined();
    expect(botao(tela, 'Turno avulso')).toBeUndefined();
    expect(tela.text()).not.toContain('Mudar'); // prazo de escala publicada não importa

    await botao(tela, 'Reabrir para ajustes').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.caminho === '/escalas/5/reabrir')).toBe(true);
    expect(tela.text()).toContain('Recolhendo');
  });

  it('muda o prazo mandando a versão que viu', async () => {
    const { tela, fetch } = await abrirEscala({ status: 'aberta' });

    await botao(tela, 'Mudar').trigger('click');
    await tela.find('#novo-prazo').setValue('2026-11-20');
    await botao(tela, 'Salvar prazo').trigger('click');
    await esperar();

    const envio = fetch.chamadas.find((c) => c.metodo === 'PATCH');
    expect(envio.corpo).toEqual({ prazo: '2026-11-20', versao: 1 });
    expect(tela.text()).toContain('Responder até 20 de novembro');
  });

  it('conflito de versão ao mudar o prazo vira mensagem e a tela recarrega', async () => {
    const { tela, fetch } = await abrirEscala({
      status: 'aberta',
      extra: { 'PATCH /escalas/5': { status: 409, corpo: { message: 'Este registro foi alterado por outra pessoa. Recarregue a tela e tente de novo.' } } },
    });
    const antes = fetch.chamadas.filter((c) => c.metodo === 'GET' && c.caminho === '/escalas/5').length;

    await botao(tela, 'Mudar').trigger('click');
    await tela.find('#novo-prazo').setValue('2026-11-20');
    await botao(tela, 'Salvar prazo').trigger('click');
    await esperar();

    expect(useMensagens().texto).toContain('alterado por outra pessoa');
    expect(fetch.chamadas.filter((c) => c.metodo === 'GET' && c.caminho === '/escalas/5').length).toBe(antes + 1);
  });
});

describe('gerar turnos dos cultos', () => {
  const cultos = {
    'GET /cultos': { corpo: { cultos: [
      { id: 1, nome: 'Culto da Manhã', inicio: '08:00', fim: '11:30', dia_semana: 0 },
      { id: 2, nome: 'Culto Fé', inicio: '20:00', fim: '21:30', dia_semana: 3 },
      { id: 3, nome: 'Quinta quarta', inicio: '20:00', fim: '21:30', dia_semana: 3 },
    ] } },
    'GET /cultos/1/ocorrencias?mes=2026-11': { corpo: { datas: ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'] } },
    'GET /cultos/2/ocorrencias?mes=2026-11': { corpo: { datas: ['2026-11-04', '2026-11-11'] } },
    'GET /cultos/3/ocorrencias?mes=2026-11': { corpo: { datas: [] } },
  };

  it('só oferece cultos que caem no mês, com quantas datas', async () => {
    const { tela } = await abrirEscala({ extra: cultos });

    await botao(tela, 'Gerar dos cultos').trigger('click');
    await esperar();

    const chips = tela.findAll('.chip').map((c) => c.text());
    expect(chips).toContain('Culto da Manhã · 08:00 (5)');
    expect(chips).toContain('Culto Fé · 20:00 (2)');
    expect(chips.some((c) => c.includes('Quinta quarta'))).toBe(false);
  });

  it('com um posto só, ele já vem escolhido; sem escolher culto não gera', async () => {
    const { tela } = await abrirEscala({ extra: cultos });

    await botao(tela, 'Gerar dos cultos').trigger('click');
    await esperar();

    expect(tela.findAll('.chip').find((c) => c.text() === 'Som').attributes('aria-pressed')).toBe('true');
    expect(botao(tela, 'Gerar turnos').attributes('disabled')).toBeDefined();
  });

  it('gera mandando os cultos e postos escolhidos e recarrega a escala', async () => {
    const { tela, fetch } = await abrirEscala({ extra: { ...cultos, 'POST /escalas/5/turnos/gerar': { status: 201, corpo: { total: 5, turnos: [] } } } });

    await botao(tela, 'Gerar dos cultos').trigger('click');
    await esperar();
    await tela.findAll('.chip').find((c) => c.text().startsWith('Culto da Manhã')).trigger('click');
    await botao(tela, 'Gerar turnos').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.caminho === '/escalas/5/turnos/gerar').corpo).toEqual({ culto_ids: [1], posto_ids: [1] });
    expect(useMensagens().texto).toBe('5 turno(s) gerado(s).');
  });

  it('erro da API (ex.: tudo já existe) aparece e a folha fica aberta', async () => {
    const { tela } = await abrirEscala({ extra: { ...cultos, 'POST /escalas/5/turnos/gerar': { status: 422, corpo: { message: 'Esses turnos já existem nesta escala.' } } } });

    await botao(tela, 'Gerar dos cultos').trigger('click');
    await esperar();
    await tela.findAll('.chip').find((c) => c.text().startsWith('Culto da Manhã')).trigger('click');
    await botao(tela, 'Gerar turnos').trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('Esses turnos já existem nesta escala.');
  });
});

describe('turno avulso e edição', () => {
  it('cria um turno avulso com posto, banda, data, horário e vagas', async () => {
    const { tela, fetch } = await abrirEscala({ extra: { 'POST /escalas/5/turnos': { status: 201, corpo: { turno: {} } } } });

    await botao(tela, 'Turno avulso').trigger('click');
    await esperar();

    await tela.find('#banda').setValue('2');
    await tela.find('#data').setValue('2026-11-18');
    await tela.find('#inicio').setValue('19:30');
    await tela.find('#fim').setValue('21:00');
    await tela.find('#vagas').setValue('3');
    await botao(tela, 'Salvar').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'POST' && c.caminho === '/escalas/5/turnos').corpo).toEqual({
      posto_id: 1, banda_id: 2, data: '2026-11-18', inicio: '19:30', fim: '21:00', vagas: 3,
    });
    expect(useMensagens().texto).toBe('Turno adicionado.');
  });

  it('em rascunho, tocar no turno abre a edição com a versão que a tela viu', async () => {
    const { tela, fetch } = await abrirEscala({ extra: { 'PATCH /turnos/10': { corpo: { turno: {} } } } });

    await tela.findAll('button.turno')[0].trigger('click');
    await esperar();

    expect(tela.find('#data').element.value).toBe('2026-11-08');
    expect(tela.text()).toContain('Editar turno');

    await tela.find('#vagas').setValue('4');
    await botao(tela, 'Salvar').trigger('click');
    await esperar();

    const envio = fetch.chamadas.find((c) => c.metodo === 'PATCH');
    expect(envio.caminho).toBe('/turnos/10');
    expect(envio.corpo).toMatchObject({ versao: 1, vagas: 4, posto_id: 1, banda_id: 2, data: '2026-11-08' });
  });

  it('exclui o turno em rascunho', async () => {
    const { tela, fetch } = await abrirEscala({ extra: { 'DELETE /turnos/10': { status: 204 } } });

    await tela.findAll('button.turno')[0].trigger('click');
    await esperar();
    await botao(tela, 'Excluir').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'DELETE' && c.caminho === '/turnos/10')).toBe(true);
    expect(useMensagens().texto).toBe('Turno excluído.');
  });
});

describe('escalas do líder: anos de histórico', () => {
  const mes = (ano, m) => `${ano}-${String(m).padStart(2, '0')}`;

  // 30 meses publicados (de 2026-10 para trás, sem 2026-08) + rascunho de agosto, que ninguém publicou, + novembro e dezembro.
  const publicadas = [];
  for (let k = 0, ano = 2026, m = 10; publicadas.length < 30; k++, m--) {
    if (m === 0) { ano--; m = 12; }
    if (ano === 2026 && m === 8) continue;
    publicadas.push(escala({ id: 100 + k, mes: mes(ano, m), status: 'publicada', total_de_turnos: 8 }));
  }
  const ESCALAS = [
    escala({ id: 3, mes: '2026-12', status: 'rascunho' }),
    escala({ id: 2, mes: '2026-11', status: 'aberta' }),
    escala({ id: 4, mes: '2026-08', status: 'rascunho' }),
    ...publicadas,
  ];

  const abrir = async (escalas = ESCALAS) => {
    simularApi({
      'GET /ministerios/1/trocas': { corpo: { total_abertas: 0, trocas: [] } },
      'GET /ministerios/1/escalas': { corpo: { escalas } },
    });
    return montarTela(LiderEscalas, { entrada: '/lider', pessoa: LIDER, modo: 'lider' });
  };

  it('abre só com o que está em dia: mês atual em diante e o que ainda não foi publicado', async () => {
    const { tela } = await abrir();

    expect(tela.findAll('a.escala').map((c) => c.text().split('\n')[0].trim())).toHaveLength(3);
    expect(tela.text()).toContain('dezembro de 2026');
    expect(tela.text()).toContain('novembro de 2026');
    expect(tela.text()).toContain('agosto de 2026'); // rascunho esquecido continua à vista
    expect(tela.text()).not.toContain('outubro de 2026');
    expect(botao(tela, 'Ver escalas anteriores (30)')).toBeTruthy();
  });

  it('as anteriores vêm por ano, 12 de cada vez, e dá para esconder de novo', async () => {
    const { tela } = await abrir();

    await botao(tela, 'Ver escalas anteriores (30)').trigger('click');
    expect(tela.findAll('a.escala')).toHaveLength(15);
    expect(tela.findAll('.rotulo').map((r) => r.text())).toEqual(['2026', '2025']);
    expect(tela.text()).toContain('outubro de 2026');
    expect(botao(tela, 'Mostrar mais (18 restantes)')).toBeTruthy();

    await botao(tela, 'Mostrar mais (18 restantes)').trigger('click');
    expect(tela.findAll('a.escala')).toHaveLength(27);
    expect(tela.findAll('.rotulo').map((r) => r.text())).toEqual(['2026', '2025', '2024']);

    await botao(tela, 'Mostrar mais (6 restantes)').trigger('click');
    expect(tela.findAll('a.escala')).toHaveLength(33);
    expect(tela.text()).not.toContain('Mostrar mais');

    await botao(tela, 'Esconder escalas anteriores').trigger('click');
    expect(tela.findAll('a.escala')).toHaveLength(3);
    expect(tela.findAll('.rotulo')).toHaveLength(0);
  });

  it('a escala publicada do mês atual ainda é do mês: só o passado vai para anteriores', async () => {
    const { tela } = await abrir([escala({ id: 2, mes: '2026-11', status: 'publicada' }), escala({ id: 1, mes: '2026-10', status: 'publicada' })]);

    expect(tela.findAll('a.escala')).toHaveLength(1);
    expect(tela.text()).toContain('novembro de 2026');
    expect(botao(tela, 'Ver escalas anteriores (1)')).toBeTruthy();
  });

  it('sem escalas anteriores, não aparece o botão; e o mês livre continua considerando todas', async () => {
    const { tela } = await abrir([escala({ id: 2, mes: '2026-11', status: 'aberta' })]);

    expect(tela.text()).not.toContain('escalas anteriores');
    expect(tela.findAll('a.escala')).toHaveLength(1);
  });

  it('o seletor de "novo mês" não oferece mês que já tem escala antiga', async () => {
    const { tela } = await abrir([escala({ id: 2, mes: '2026-11', status: 'aberta' }), escala({ id: 1, mes: '2026-09', status: 'publicada' })]);

    await botao(tela, 'Abrir escala de um mês').trigger('click');

    expect([...tela.find('#mes').element.options].map((o) => o.value)).not.toContain('2026-11');
  });
});

describe('escala do líder: escala grande', () => {
  // 40 turnos em 10 dias (4 por dia), uma metade com a vaga preenchida e a outra sem ninguém.
  const turnos = Array.from({ length: 40 }, (_, i) => turno(200 + i, `2026-11-${String(1 + Math.floor(i / 4)).padStart(2, '0')}`, {
    inicio: `0${i % 4 + 6}:00`, confirmados_total: i % 2 ? 2 : 0, marcaram_total: 3, vagas: 2,
    confirmados: i % 2 ? [{ pessoa_id: 1, nome: 'Helena Costa', sem_funcao: false }] : [],
  }));

  it('agrupa por dia com o título do dia e mostra 30 turnos de cada vez', async () => {
    const { tela } = await abrirEscala({ status: 'aberta', turnos });

    expect(tela.findAll('button.turno')).toHaveLength(30);
    const titulos = tela.findAll('.dia-titulo').map((r) => r.text());
    expect(titulos).toHaveLength(8); // 30 turnos = 7 dias inteiros + 2 do oitavo
    expect(titulos[0]).toBe('dom, 01 de novembro');
    expect(botao(tela, 'Mostrar mais (10 restantes)')).toBeTruthy();

    await botao(tela, 'Mostrar mais (10 restantes)').trigger('click');
    expect(tela.findAll('button.turno')).toHaveLength(40);
    expect(tela.findAll('.dia-titulo')).toHaveLength(10);
    expect(tela.text()).not.toContain('Mostrar mais');
  });

  it('o filtro "Faltam pessoas" mostra só os turnos com vaga em aberto', async () => {
    const { tela } = await abrirEscala({ status: 'aberta', turnos });

    expect(botao(tela, 'Todos (40)')).toBeTruthy();
    await botao(tela, 'Faltam pessoas (20)').trigger('click');

    expect(tela.findAll('button.turno')).toHaveLength(20);
    expect(tela.findAll('button.turno').every((t) => t.text().includes('0 de 2 confirmados'))).toBe(true);

    await botao(tela, 'Todos (40)').trigger('click');
    expect(tela.findAll('button.turno')).toHaveLength(30);
  });

  it('trocar de filtro volta ao primeiro bloco', async () => {
    const { tela } = await abrirEscala({ status: 'aberta', turnos });
    await botao(tela, 'Mostrar mais (10 restantes)').trigger('click');
    expect(tela.findAll('button.turno')).toHaveLength(40);

    await botao(tela, 'Faltam pessoas (20)').trigger('click');
    await botao(tela, 'Todos (40)').trigger('click');

    expect(tela.findAll('button.turno')).toHaveLength(30);
  });

  it('quando todas as vagas têm gente, o filtro avisa em vez de ficar em branco', async () => {
    const cheios = turnos.map((t) => ({ ...t, confirmados_total: 2 }));
    const { tela } = await abrirEscala({ status: 'aberta', turnos: cheios });

    await botao(tela, 'Faltam pessoas (0)').trigger('click');

    expect(tela.text()).toContain('Nenhum turno com vaga em aberto');
    expect(tela.findAll('button.turno')).toHaveLength(0);
  });

  it('escala pequena ou em rascunho não ganha filtro', async () => {
    const pequena = await abrirEscala({ status: 'aberta' });
    expect(pequena.tela.find('[role="tablist"]').exists()).toBe(false);

    const rascunho = await abrirEscala({ status: 'rascunho', turnos });
    expect(rascunho.tela.find('[role="tablist"]').exists()).toBe(false);
  });

  it('turnos do mesmo dia ficam sob o mesmo título', async () => {
    const { tela } = await abrirEscala({ status: 'aberta', turnos: turnos.slice(0, 4) });

    expect(tela.findAll('.dia-titulo')).toHaveLength(1);
    expect(tela.findAll('button.turno')).toHaveLength(4);
  });
});
