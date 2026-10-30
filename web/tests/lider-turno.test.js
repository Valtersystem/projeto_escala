import { beforeEach, describe, expect, it, vi } from 'vitest';
import TurnoFolha from '../src/components/TurnoFolha.vue';
import { useMensagens } from '../src/stores/mensagens.js';
import { LIDER, botao, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

const ESCALA = { id: 5, ministerio_id: 1, mes: '2026-11', status: 'aberta' };

const helena = { pessoa_id: 1, nome: 'Helena Costa', funcoes: [{ id: 1, nome: 'Voz' }], fora_da_banda: false };
const bruno = { pessoa_id: 2, nome: 'Bruno Souza', turnos_no_mes: 1, carga: false, choques: [], fora_da_banda: false };
const carla = {
  pessoa_id: 3, nome: 'Carla Dias', turnos_no_mes: 4, carga: true, fora_da_banda: true,
  choques: [{ turno_id: 90, ministerio: 'Mídia', posto: 'Câmera', data: '2026-11-08', inicio: '09:00', fim: '10:00' }],
};

/** API da folha com estado: confirmar passa a pessoa da lista de disponíveis para a de confirmados. */
function apiDoTurno({ confirmados = [helena], disponiveis = [bruno, carla], vagas = 2, funcoes = [{ id: 1, nome: 'Voz' }, { id: 2, nome: 'Guitarra' }], extra = {} } = {}) {
  const estado = { confirmados: [...confirmados], disponiveis: [...disponiveis], vagas };

  const rotas = {
    'GET /turnos/10/pessoas': () => ({
      corpo: {
        turno: { id: 10, data: '2026-11-08', inicio: '08:00', fim: '11:30', posto: { id: 1, nome: 'Som' }, banda: { id: 2, nome: 'Banda 2' }, culto: 'Culto da Manhã', vagas: estado.vagas, versao: 3 },
        lotado: estado.confirmados.length >= estado.vagas,
        confirmados: estado.confirmados,
        disponiveis: estado.disponiveis,
      },
    }),
    'GET /ministerios/1/funcoes': { corpo: { funcoes } },
    'POST /turnos/10/confirmacoes': (corpo) => {
      const pessoa = estado.disponiveis.find((p) => p.pessoa_id === corpo.pessoa_id);
      estado.disponiveis = estado.disponiveis.filter((p) => p !== pessoa);
      estado.confirmados.push({ pessoa_id: pessoa.pessoa_id, nome: pessoa.nome, funcoes: [], fora_da_banda: pessoa.fora_da_banda });
      return { status: 201, corpo: { confirmacao: {}, choques: pessoa.choques } };
    },
    'DELETE /turnos/10/confirmacoes/1': () => { estado.confirmados = []; return { status: 204 }; },
    'PUT /turnos/10/confirmacoes/1/funcoes': (corpo) => {
      estado.confirmados[0].funcoes = corpo.funcao_ids.map((id) => ({ id, nome: id === 1 ? 'Voz' : 'Guitarra' }));
      return { corpo: {} };
    },
    'PUT /turnos/10/confirmacoes/2/funcoes': (corpo) => {
      const alvo = estado.confirmados.find((c) => c.pessoa_id === 2);
      alvo.funcoes = corpo.funcao_ids.map((id) => ({ id, nome: id === 1 ? 'Voz' : 'Guitarra' }));
      return { corpo: {} };
    },
    'PATCH /turnos/10': (corpo) => { estado.vagas = corpo.vagas; return { corpo: { turno: {} } }; },
    ...extra,
  };

  return { estado, rotas };
}

const abrir = async (opcoes = {}, escala = ESCALA) => {
  const { estado, rotas } = apiDoTurno(opcoes);
  const fetch = simularApi(rotas);
  const montada = await montarTela(TurnoFolha, { props: { turnoId: 10, escala }, entrada: '/lider', pessoa: LIDER, modo: 'lider' });
  return { ...montada, fetch, estado };
};

describe('folha do turno', () => {
  it('mostra quem está confirmado (com função) e quem marcou disponibilidade', async () => {
    const { tela } = await abrir();

    expect(tela.text()).toContain('Culto da Manhã · dom, 08 de novembro');
    expect(tela.text()).toContain('08:00 às 11:30 · Som · Banda 2');
    expect(tela.text()).toContain('Confirmados (1/2)');
    expect(tela.text()).toContain('Helena Costa');
    expect(tela.text()).toContain('Voz');
    expect(tela.text()).toContain('Marcaram disponibilidade (2)');
    expect(tela.text()).toContain('4 turno(s) no mês');
  });

  it('avisa carga, choque e "fora da banda fixa" de quem marcou', async () => {
    const { tela } = await abrir();

    expect(tela.text()).toContain('Choca');
    expect(tela.text()).toContain('⚠ já escalado em Mídia · Câmera 09:00–10:00');
    expect(tela.text()).toContain('fora da banda fixa');
    expect(botao(tela, 'Confirmar mesmo assim')).toBeTruthy();
  });

  it('quem não tem função aparece como "sem função definida"', async () => {
    const { tela } = await abrir({ confirmados: [{ ...helena, funcoes: [] }] });

    expect(tela.text()).toContain('sem função definida');
  });

  it('confirmar manda a pessoa, avisa e já abre a escolha de funções', async () => {
    const { tela, fetch } = await abrir();

    await botao(tela, 'Confirmar').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'POST').corpo).toEqual({ pessoa_id: 2 });
    expect(useMensagens().texto).toBe('Bruno Souza confirmado.');
    expect(tela.text()).toContain('Confirmados (2/2)');
    // A tela de trás é avisada para recarregar os números do turno.
    expect(tela.emitted('mudou')).toHaveLength(1);

    // As funções do ministério aparecem para a pessoa recém-confirmada.
    const chips = tela.findAll('.chips .chip').map((c) => c.text());
    expect(chips).toEqual(['Voz', 'Guitarra']);

    await tela.findAll('.chips .chip')[1].trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'PUT' && c.caminho.endsWith('/2/funcoes')).corpo).toEqual({ funcao_ids: [2] });
    expect(tela.text()).toContain('Guitarra');
  });

  it('confirmar quem tem choque ainda funciona, mas avisa', async () => {
    const { tela } = await abrir({ disponiveis: [carla] });

    await botao(tela, 'Confirmar mesmo assim').trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('Carla Dias confirmado, mas choca com outro turno.');
  });

  it('trocar as funções de quem já está confirmado', async () => {
    const { tela, fetch } = await abrir();

    await botao(tela, 'Funções').trigger('click');
    // Voz já marcada; marca Guitarra também.
    await tela.findAll('.chips .chip')[1].trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'PUT').corpo).toEqual({ funcao_ids: [1, 2] });

    // Desmarca a Voz.
    await tela.findAll('.chips .chip')[0].trigger('click');
    await esperar();

    expect(fetch.chamadas.filter((c) => c.metodo === 'PUT').at(-1).corpo).toEqual({ funcao_ids: [2] });
  });

  it('tirar a pessoa do turno', async () => {
    const { tela, fetch } = await abrir();

    await botao(tela, 'Tirar').trigger('click');
    await esperar();

    expect(fetch.chamadas.some((c) => c.metodo === 'DELETE' && c.caminho === '/turnos/10/confirmacoes/1')).toBe(true);
    expect(useMensagens().texto).toBe('Helena Costa saiu do turno.');
    expect(tela.text()).toContain('Ninguém confirmado ainda');
  });

  it('turno lotado: não deixa confirmar e oferece aumentar uma vaga', async () => {
    const { tela, fetch } = await abrir({ confirmados: [helena, { ...helena, pessoa_id: 7, nome: 'Marcos Reis' }] });

    expect(tela.text()).toContain('Turno lotado');
    expect(tela.findAll('button').filter((b) => b.text() === 'Confirmar').every((b) => b.attributes('disabled') !== undefined)).toBe(true);

    await botao(tela, 'Aumentar uma vaga').trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'PATCH').corpo).toEqual({ versao: 3, vagas: 3 });
    expect(tela.text()).not.toContain('Turno lotado');
  });

  it('escala publicada: lotado manda reabrir a escala em vez de aumentar a vaga', async () => {
    const { tela } = await abrir({ confirmados: [helena, { ...helena, pessoa_id: 7, nome: 'Marcos Reis' }] }, { ...ESCALA, status: 'publicada' });

    expect(tela.text()).toContain('Reabra a escala para aumentar as vagas');
    expect(botao(tela, 'Aumentar uma vaga')).toBeUndefined();
  });

  it('ministério sem funções cadastradas: nada de funções', async () => {
    const { tela } = await abrir({ funcoes: [] });

    expect(botao(tela, 'Funções')).toBeUndefined();
    expect(tela.text()).not.toContain('sem função definida');

    await botao(tela, 'Confirmar').trigger('click');
    await esperar();

    expect(tela.findAll('.chips .chip')).toHaveLength(0);
  });

  it('escala em rascunho ainda não confirma ninguém', async () => {
    const { tela } = await abrir({}, { ...ESCALA, status: 'rascunho' });

    expect(botao(tela, 'Confirmar')).toBeUndefined();
  });

  it('erro da API ao confirmar vira mensagem e a folha recarrega', async () => {
    const { tela, fetch } = await abrir({ extra: { 'POST /turnos/10/confirmacoes': { status: 422, corpo: { message: 'Turno lotado. Aumente as vagas para confirmar mais gente.' } } } });
    const antes = fetch.chamadas.filter((c) => c.caminho === '/turnos/10/pessoas').length;

    await botao(tela, 'Confirmar').trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('Turno lotado. Aumente as vagas para confirmar mais gente.');
    expect(useMensagens().tipo).toBe('erro');
    expect(fetch.chamadas.filter((c) => c.caminho === '/turnos/10/pessoas').length).toBe(antes + 1);
  });

  it('turnoId nulo mantém a folha fechada e sem chamar a API', async () => {
    const fetch = simularApi({});
    await montarTela(TurnoFolha, { props: { turnoId: null, escala: ESCALA }, entrada: '/lider', pessoa: LIDER, modo: 'lider' });

    expect(fetch.chamadas.filter((c) => c.caminho.includes('/pessoas'))).toHaveLength(0);
  });
});
