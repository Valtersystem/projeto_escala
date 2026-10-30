import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory } from 'vue-router';
import App from '../src/App.vue';
import AdmCultos from '../src/views/AdmCultos.vue';
import AdmMinisterios from '../src/views/AdmMinisterios.vue';
import AdmPessoas from '../src/views/AdmPessoas.vue';
import AdmRelatorio from '../src/views/AdmRelatorio.vue';
import { criarRoteador } from '../src/router.js';
import { useModo } from '../src/stores/modo.js';
import { useSessao } from '../src/stores/sessao.js';
import { ADM, LIDER, botao, botaoExato, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  Object.assign(navigator, { clipboard: { writeText: vi.fn(async () => {}) } });
});

const enviados = (fetch, metodo, caminho) => fetch.chamadas.filter((c) => c.metodo === metodo && c.caminho === caminho);

const pessoa = (id, nome, extra = {}) => ({
  id, nome, email: `${nome.split(' ')[0].toLowerCase()}@exemplo.test`, telefone: null, adm: false, ativo: true, tem_login: true,
  ministerios: [], ...extra,
});

const MINISTERIOS = [
  { id: 1, nome: 'Louvor', lider: { id: 4, nome: 'Marcos Reis' } },
  { id: 2, nome: 'Mídia', lider: null },
];
const PESSOAS = [pessoa(4, 'Marcos Reis', { ministerios: [{ id: 1, nome: 'Louvor' }] }), pessoa(7, 'Helena Costa', { ministerios: [{ id: 1, nome: 'Louvor' }, { id: 2, nome: 'Mídia' }] }), pessoa(8, 'Bruno Souza', { ativo: false })];

const rotasBase = (extra = {}) => ({
  'GET /ministerios': { corpo: { ministerios: MINISTERIOS } },
  'GET /pessoas': { corpo: { pessoas: PESSOAS } },
  'GET /cultos': { corpo: { cultos: [
    { id: 1, nome: 'Culto da Manhã', tipo: 'semanal', dia_semana: 0, ordem: 1, inicio: '08:00', fim: '11:30' },
    { id: 2, nome: 'Santa Ceia', tipo: 'mensal', dia_semana: 0, ordem: 1, inicio: '19:00', fim: '21:00' },
  ] } },
  ...extra,
});

const abrir = async (componente, rotas = {}) => {
  const fetch = simularApi(rotasBase(rotas));
  const montada = await montarTela(componente, { entrada: '/adm/ministerios', pessoa: ADM, modo: 'adm' });
  return { ...montada, fetch };
};

describe('modo Adm: acesso e navegação', () => {
  const comPessoa = (p) => {
    localStorage.setItem('escala.token', 'tok');
    useSessao().pessoa = p;
  };

  beforeEach(async () => {
    const { createPinia, setActivePinia } = await import('pinia');
    setActivePinia(createPinia());
  });

  it('quem não é Adm não abre as telas do Adm', async () => {
    comPessoa(LIDER);
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/adm/cultos');

    expect(roteador.currentRoute.value.name).toBe('inicio');
  });

  it('o Adm abre as telas do Adm e o modo acompanha', async () => {
    comPessoa(ADM);
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/adm/pessoas');

    expect(roteador.currentRoute.value.name).toBe('adm-pessoas');
    expect(useModo().modo).toBe('adm');
  });

  it('o Adm que não serve em ministério nenhum cai direto na área do Adm', async () => {
    comPessoa(ADM);
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/');

    expect(roteador.currentRoute.value.name).toBe('adm-ministerios');
  });

  it('o Adm que também serve num ministério continua começando no Início', async () => {
    comPessoa({ ...ADM, ministerios: [{ id: 2, nome: 'Mídia', lider: false }] });
    const roteador = criarRoteador(createMemoryHistory());

    await roteador.push('/');

    expect(roteador.currentRoute.value.name).toBe('inicio');
  });

  it('o aparelho lembrar "adm" não vale para quem deixou de ser Adm', async () => {
    localStorage.setItem('escala.modo', 'adm');
    comPessoa(LIDER);

    expect(useModo().modo).toBe('voluntario');
  });

  it('o Adm sem ministério não vê seletor de modo: só tem a própria área', async () => {
    simularApi(rotasBase());
    const { tela } = await montarTela(App, { entrada: '/adm/ministerios', pessoa: ADM, modo: 'adm' });

    expect(tela.findAll('nav a').map((a) => a.text())).toEqual(['Ministérios', 'Cultos', 'Pessoas', 'Relatório', 'Perfil']);
    expect(tela.find('[role=tablist]').exists()).toBe(false);
  });

  it('o Adm que também serve num ministério tem o seletor e volta ao voluntário', async () => {
    simularApi(rotasBase());
    const { tela, roteador } = await montarTela(App, { entrada: '/adm/ministerios', pessoa: { ...ADM, ministerios: [{ id: 2, nome: 'Mídia', lider: false }] }, modo: 'adm' });

    expect(botao(tela, 'Adm')).toBeTruthy();

    await botao(tela, 'Voluntário').trigger('click');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('inicio'));
  });
});

describe('Adm: ministérios', () => {
  it('lista os ministérios e destaca os sem líder', async () => {
    const { tela } = await abrir(AdmMinisterios);
    const linhas = tela.findAll('.linha-pessoa');

    expect(linhas[0].text()).toContain('Louvor');
    expect(linhas[0].text()).toContain('Líder: Marcos Reis');
    expect(linhas[1].text()).toContain('Sem líder');
  });

  it('cria o ministério e já define o líder, só entre quem está ativo', async () => {
    const { tela, fetch } = await abrir(AdmMinisterios, {
      'POST /ministerios': { status: 201, corpo: { ministerio: { id: 3, nome: 'Recepção', lider: null } } },
      'PUT /ministerios/3/lider': { corpo: { ministerio: {} } },
    });

    await botao(tela, 'Novo ministério').trigger('click');
    const opcoes = tela.findAll('#min-lider option').map((o) => o.text());
    expect(opcoes).toContain('Helena Costa');
    expect(opcoes).not.toContain('Bruno Souza');

    await tela.find('#min-nome').setValue(' Recepção ');
    await tela.find('#min-lider').setValue('7');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/ministerios')[0].corpo).toEqual({ nome: 'Recepção' });
    expect(enviados(fetch, 'PUT', '/ministerios/3/lider')[0].corpo).toEqual({ pessoa_id: 7 });
  });

  it('editar sem mexer em nada não chama a API de alteração', async () => {
    const { tela, fetch } = await abrir(AdmMinisterios);

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(fetch.chamadas.filter((c) => c.metodo !== 'GET')).toHaveLength(0);
  });

  it('trocar só o líder não renomeia', async () => {
    const { tela, fetch } = await abrir(AdmMinisterios, { 'PUT /ministerios/1/lider': { corpo: { ministerio: {} } } });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await tela.find('#min-lider').setValue('7');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/ministerios/1/lider')[0].corpo).toEqual({ pessoa_id: 7 });
    expect(enviados(fetch, 'PATCH', '/ministerios/1')).toHaveLength(0);
  });

  it('renomear usa PATCH', async () => {
    const { tela, fetch } = await abrir(AdmMinisterios, { 'PATCH /ministerios/2': { corpo: { ministerio: {} } } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await tela.find('#min-nome').setValue('Mídia e Som');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PATCH', '/ministerios/2')[0].corpo).toEqual({ nome: 'Mídia e Som' });
  });

  it('excluir avisa o que apaga e só exclui na segunda vez', async () => {
    const { tela, fetch } = await abrir(AdmMinisterios, { 'DELETE /ministerios/2': { status: 204 } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await botaoExato(tela, 'Excluir').trigger('click');
    expect(tela.text()).toContain('apaga os postos, escalas e turnos');
    expect(enviados(fetch, 'DELETE', '/ministerios/2')).toHaveLength(0);

    await botaoExato(tela, 'Confirmar exclusão').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/ministerios/2')).toHaveLength(1);
  });

  it('nome repetido: o erro da API aparece e a folha continua aberta', async () => {
    const { tela, fetch } = await abrir(AdmMinisterios, { 'POST /ministerios': { status: 422, corpo: { message: 'x', errors: { nome: ['Já existe.'] } } } });

    await botao(tela, 'Novo ministério').trigger('click');
    await tela.find('#min-nome').setValue('Louvor');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(tela.find('#min-nome').exists()).toBe(true);
    expect(enviados(fetch, 'POST', '/ministerios')).toHaveLength(1);
  });
});

describe('Adm: cultos', () => {
  it('descreve quando cada culto acontece', async () => {
    const { tela } = await abrir(AdmCultos);
    const linhas = tela.findAll('.linha-pessoa');

    expect(linhas[0].text()).toContain('Todo domingo · 08:00 às 11:30');
    expect(linhas[1].text()).toContain('1º domingo do mês · 19:00 às 21:00');
  });

  it('cria um culto semanal sem mandar a ordem do mês', async () => {
    const { tela, fetch } = await abrir(AdmCultos, { 'POST /cultos': { status: 201, corpo: { culto: {} } } });

    await botao(tela, 'Novo culto').trigger('click');
    expect(tela.find('#c-ordem').exists()).toBe(false);
    await tela.find('#c-nome').setValue('Culto da Noite');
    await tela.find('#c-dia').setValue('3');
    await tela.find('#c-inicio').setValue('19:30');
    await tela.find('#c-fim').setValue('21:30');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/cultos')[0].corpo).toEqual({ nome: 'Culto da Noite', tipo: 'semanal', dia_semana: 3, inicio: '19:30', fim: '21:30' });
  });

  it('o culto mensal pede qual do mês, inclusive o último', async () => {
    const { tela, fetch } = await abrir(AdmCultos, { 'POST /cultos': { status: 201, corpo: { culto: {} } } });

    await botao(tela, 'Novo culto').trigger('click');
    await tela.find('#c-nome').setValue('Vigília');
    await tela.find('#c-tipo').setValue('mensal');
    await tela.find('#c-ordem').setValue('-1');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/cultos')[0].corpo).toMatchObject({ tipo: 'mensal', ordem: -1 });
  });

  it('editar abre com os dados do culto e salva com PATCH', async () => {
    const { tela, fetch } = await abrir(AdmCultos, { 'PATCH /cultos/2': { corpo: { culto: {} } } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    expect(tela.find('#c-nome').element.value).toBe('Santa Ceia');
    expect(tela.find('#c-tipo').element.value).toBe('mensal');
    await tela.find('#c-inicio').setValue('18:00');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PATCH', '/cultos/2')[0].corpo).toMatchObject({ nome: 'Santa Ceia', inicio: '18:00', ordem: 1 });
  });

  it('não salva sem nome', async () => {
    const { tela, fetch } = await abrir(AdmCultos);

    await botao(tela, 'Novo culto').trigger('click');

    expect(botaoExato(tela, 'Salvar').attributes('disabled')).toBeDefined();
    expect(fetch.chamadas.filter((c) => c.metodo === 'POST')).toHaveLength(0);
  });

  it('excluir pede confirmação', async () => {
    const { tela, fetch } = await abrir(AdmCultos, { 'DELETE /cultos/1': { status: 204 } });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botaoExato(tela, 'Excluir').trigger('click');
    expect(enviados(fetch, 'DELETE', '/cultos/1')).toHaveLength(0);
    await botaoExato(tela, 'Confirmar exclusão').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/cultos/1')).toHaveLength(1);
  });
});

// As folhas ficam todas no DOM nos testes; a da pessoa é a última.
const daFolha = (tela, texto) => tela.findAll('button').filter((b) => b.text() === texto).at(-1);

describe('Adm: pessoas', () => {
  it('lista todos, com ministérios, e marca Adm, inativo e sem acesso', async () => {
    const { tela } = await abrir(AdmPessoas, { 'GET /pessoas': { corpo: { pessoas: [...PESSOAS, pessoa(9, 'Paulo Adm', { adm: true }), pessoa(10, 'Rita Dias', { tem_login: false })] } } });
    const textos = tela.findAll('.linha-pessoa').map((l) => l.text());

    expect(textos[1]).toContain('Louvor · Mídia');
    expect(textos[2]).toContain('Inativa');
    expect(textos[3]).toContain('Adm');
    expect(textos[4]).toContain('Sem acesso');
    expect(textos[4]).toContain('Sem ministério');
  });

  it('a busca filtra pelo nome', async () => {
    const { tela } = await abrir(AdmPessoas);

    await tela.find('#busca').setValue('hel');

    expect(tela.findAll('.linha-pessoa')).toHaveLength(1);
    expect(tela.text()).toContain('Helena Costa');
  });

  it('cadastra com ministérios e o papel de Adm', async () => {
    const { tela, fetch } = await abrir(AdmPessoas, { 'POST /pessoas': { status: 201, corpo: { pessoa: {} } } });

    await botao(tela, 'Nova pessoa').trigger('click');
    await tela.find('#n-nome').setValue('Carla Dias');
    await tela.find('#n-email').setValue('carla@exemplo.test');
    await botaoExato(tela, 'Mídia').trigger('click');
    await tela.findAll('.interruptor').find((b) => b.text().includes('Administrador')).trigger('click');
    await botaoExato(tela, 'Cadastrar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/pessoas')[0].corpo).toEqual({ nome: 'Carla Dias', telefone: null, email: 'carla@exemplo.test', adm: true, ministerio_ids: [2] });
  });

  it('marcar e desmarcar ministério usa a equipe do ministério', async () => {
    const { tela, fetch } = await abrir(AdmPessoas, { 'POST /ministerios/2/equipe': { status: 201 }, 'DELETE /ministerios/1/equipe/8': { status: 204 } });

    await tela.findAll('.linha-pessoa')[2].trigger('click'); // Bruno, sem ministério
    await daFolha(tela, 'Mídia').trigger('click');
    await esperar();
    expect(enviados(fetch, 'POST', '/ministerios/2/equipe')[0].corpo).toEqual({ pessoa_id: 8 });

    await tela.findAll('.linha-pessoa')[1].trigger('click'); // Helena, no Louvor
    await daFolha(tela, 'Louvor').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/ministerios/1/equipe/7')).toHaveLength(1);
  });

  it('tornar Adm e desativar mandam só o campo mudado', async () => {
    const { tela, fetch } = await abrir(AdmPessoas, { 'PATCH /pessoas/7': { corpo: { pessoa: {} } } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await tela.find('[data-teste="adm"]').trigger('click');
    await esperar();
    await tela.find('[data-teste="ativo"]').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PATCH', '/pessoas/7').map((c) => c.corpo)).toEqual([{ adm: true }, { ativo: false }]);
  });

  it('o Adm não tira o próprio acesso nem se exclui', async () => {
    const { tela } = await abrir(AdmPessoas, { 'GET /pessoas': { corpo: { pessoas: [pessoa(9, 'Paulo Adm', { adm: true })] } } });

    await tela.findAll('.linha-pessoa')[0].trigger('click');

    expect(tela.find('[data-teste="adm"]').attributes('disabled')).toBeDefined();
    expect(botao(tela, 'Excluir pessoa')).toBeUndefined();
  });

  it('excluir pessoa pede confirmação', async () => {
    const { tela, fetch } = await abrir(AdmPessoas, { 'DELETE /pessoas/7': { status: 204 } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await botao(tela, 'Excluir pessoa').trigger('click');
    expect(enviados(fetch, 'DELETE', '/pessoas/7')).toHaveLength(0);
    await botao(tela, 'Confirmar: excluir pessoa e login').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/pessoas/7')).toHaveLength(1);
  });

  it('gera o link de acesso pela mesma folha', async () => {
    const link = 'http://app.test/convite/xyz';
    const { tela, fetch } = await abrir(AdmPessoas, { 'POST /pessoas/7/convite': { status: 201, corpo: { token: 'xyz', link } } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await botao(tela, 'Gerar novo link').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/pessoas/7/convite')).toHaveLength(1);
    expect(tela.text()).toContain(link);
  });
});

describe('Adm: relatório geral', () => {
  const GERAL = {
    mes: null,
    totais: { turnos: 5, vagas: 10, vagas_preenchidas_pct: 60, turnos_sem_ninguem: 2, pessoas_ativas: 12, ministerios_sem_lider: 1, pedidos_de_entrada: 2, trocas_abertas: 0 },
    ministerios: [
      { id: 1, nome: 'Louvor', lider: { id: 4, nome: 'Marcos Reis' }, voluntarios_ativos: 8, turnos: 4, vagas: 8, vagas_preenchidas_pct: 75, turnos_sem_ninguem: 0 },
      { id: 2, nome: 'Mídia', lider: null, voluntarios_ativos: 3, turnos: 1, vagas: 2, vagas_preenchidas_pct: 0, turnos_sem_ninguem: 1 },
    ],
    acima_do_limite: null,
  };

  const abrirRelatorio = async (rotas = {}) => {
    const fetch = simularApi({ 'GET /relatorio-geral': { corpo: GERAL }, ...rotas });
    const montada = await montarTela(AdmRelatorio, { entrada: '/adm/relatorio', pessoa: ADM, modo: 'adm' });
    return { ...montada, fetch };
  };

  it('mostra os totais, o que pede atenção e cada ministério', async () => {
    const { tela } = await abrirRelatorio();

    expect(tela.find('.estatisticas').text()).toContain('60%');
    expect(tela.find('.estatisticas').text()).toContain('12');
    expect(tela.find('.atencao').text()).toContain('1 ministério(s) sem líder');
    expect(tela.find('.atencao').text()).toContain('2 pedido(s) de entrada esperando');
    expect(tela.find('.atencao').text()).not.toContain('troca');
    expect(tela.findAll('.atencao .pilula')).toHaveLength(2);

    const linhas = tela.findAll('.ministerio');
    expect(linhas[0].text()).toContain('Louvor');
    expect(linhas[0].text()).toContain('Líder: Marcos Reis');
    expect(linhas[0].text()).toContain('75%');
    expect(linhas[1].text()).toContain('Sem líder');
    expect(linhas[1].text()).toContain('1 vazio(s)');
  });

  it('escolher o mês refaz a consulta e mostra quem passou do limite', async () => {
    const { tela, fetch } = await abrirRelatorio({
      'GET /relatorio-geral?mes=2026-11': { corpo: { ...GERAL, mes: '2026-11', acima_do_limite: [{ pessoa_id: 7, nome: 'Helena Costa', turnos: 4 }] } },
    });

    await tela.find('#mes').setValue('2026-11');
    await esperar();

    expect(fetch.chamadas.some((c) => c.caminho === '/relatorio-geral?mes=2026-11')).toBe(true);
    expect(tela.text()).toContain('Helena Costa');
    expect(tela.text()).toContain('4 turnos');
  });

  it('mês sem ninguém acima do limite diz isso; sem mês, não fala de limite', async () => {
    const { tela } = await abrirRelatorio({ 'GET /relatorio-geral?mes=2026-11': { corpo: { ...GERAL, mes: '2026-11', acima_do_limite: [] } } });
    expect(tela.text()).not.toContain('limite');

    await tela.find('#mes').setValue('2026-11');
    await esperar();
    expect(tela.text()).toContain('Ninguém passou do limite');
  });

  it('sem turnos, mostra "Sem dados" e não os números', async () => {
    const { tela } = await abrirRelatorio({ 'GET /relatorio-geral': { corpo: { ...GERAL, totais: { ...GERAL.totais, turnos: 0 }, ministerios: [] } } });

    expect(tela.text()).toContain('Sem dados');
    expect(tela.find('.estatisticas').exists()).toBe(false);
  });

  it('erro da API mostra o aviso com "Tentar de novo"', async () => {
    const { tela } = await abrirRelatorio({ 'GET /relatorio-geral': { status: 500, corpo: { message: 'Falhou.' } } });

    expect(tela.text()).toContain('Falhou.');
    expect(botao(tela, 'Tentar de novo')).toBeTruthy();
  });
});
