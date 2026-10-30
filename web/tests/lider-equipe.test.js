import { beforeEach, describe, expect, it, vi } from 'vitest';
import LiderEquipe from '../src/views/LiderEquipe.vue';
import { useMensagens } from '../src/stores/mensagens.js';
import { LIDER, botao, botaoExato, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  Object.assign(navigator, { clipboard: { writeText: vi.fn(async () => {}) } });
});

const pessoa = (id, nome, extra = {}) => ({
  id, nome, email: `${nome.split(' ')[0].toLowerCase()}@exemplo.test`, telefone: '11 98888-7777', adm: false, ativo: true, tem_login: true,
  ministerios: [{ id: 1, nome: 'Louvor' }], posto_ids: [], funcao_ids: [], banda_ids: [], ...extra,
});

const BANDAS_LIGADO = { chave: 'bandas', nome: 'Bandas', descricao: 'Grupos fixos com rodízio.', ativo: true };

const POSTOS = [{ id: 1, nome: 'Voz', descricao: null, vagas: 2 }, { id: 2, nome: 'Bateria', descricao: 'Palco', vagas: 1 }];
const FUNCOES = [{ id: 1, nome: 'Guitarra' }];

function rotasBase(extra = {}) {
  return {
    'GET /pessoas?ministerio_id=1': { corpo: { pessoas: [pessoa(7, 'Helena Costa', { posto_ids: [1] }), pessoa(8, 'Bruno Souza', { tem_login: false })] } },
    'GET /ministerios/1/bandas': { corpo: { bandas: [
      { id: 1, nome: 'Banda 1', ordem: 1, membros: [{ id: 7, nome: 'Helena Costa' }] },
      { id: 2, nome: 'Banda 2', ordem: 2, membros: [] },
    ] } },
    'GET /ministerios/1/postos': { corpo: { postos: POSTOS } },
    'GET /ministerios/1/funcoes': { corpo: { funcoes: FUNCOES } },
    'GET /ministerios/1/pedidos-de-entrada': { corpo: { total: 0, pedidos: [] } },
    'GET /ministerios/1/modulos': { corpo: { modulos: [BANDAS_LIGADO] } },
    ...extra,
  };
}

const abrir = async (rotas = {}, entrada = '/lider/equipe') => {
  const fetch = simularApi(rotasBase(rotas));
  const montada = await montarTela(LiderEquipe, { entrada, pessoa: LIDER, modo: 'lider' });
  return { ...montada, fetch };
};

const enviados = (fetch, metodo, caminho) => fetch.chamadas.filter((c) => c.metodo === metodo && c.caminho === caminho);

describe('equipe do líder: pessoas', () => {
  it('lista a equipe com os postos de cada um e marca quem ainda não tem acesso', async () => {
    const { tela } = await abrir();
    const linhas = tela.findAll('.linha-pessoa');

    expect(linhas).toHaveLength(2);
    expect(linhas[0].text()).toContain('Helena Costa');
    expect(linhas[0].text()).toContain('Voz');
    expect(linhas[0].text()).not.toContain('Sem acesso');
    expect(linhas[1].text()).toContain('Sem posto');
    expect(linhas[1].text()).toContain('Sem acesso');
  });

  it('marcar um posto manda a lista inteira de postos da pessoa', async () => {
    const { tela, fetch } = await abrir({ 'PUT /ministerios/1/equipe/7/postos': (c) => ({ corpo: { posto_ids: c.posto_ids } }) });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botaoExato(tela, 'Bateria').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/ministerios/1/equipe/7/postos')[0].corpo).toEqual({ posto_ids: [1, 2] });
  });

  it('desmarcar um posto tira só ele', async () => {
    const { tela, fetch } = await abrir({ 'PUT /ministerios/1/equipe/7/postos': (c) => ({ corpo: { posto_ids: c.posto_ids } }) });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botaoExato(tela, 'Voz').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/ministerios/1/equipe/7/postos')[0].corpo).toEqual({ posto_ids: [] });
  });

  it('marcar uma função usa a rota de funções da pessoa', async () => {
    const { tela, fetch } = await abrir({ 'PUT /ministerios/1/equipe/7/funcoes': (c) => ({ corpo: { funcao_ids: c.funcao_ids } }) });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botaoExato(tela, 'Guitarra').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/ministerios/1/equipe/7/funcoes')[0].corpo).toEqual({ funcao_ids: [1] });
  });

  it('gera o link de acesso e oferece copiar e mandar pelo WhatsApp com o telefone da pessoa', async () => {
    const link = 'http://app.test/convite/abc123';
    const { tela, fetch } = await abrir({ 'POST /pessoas/8/convite': { status: 201, corpo: { token: 'abc123', link, expira_em: '2026-11-06T00:00:00Z' } } });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await botao(tela, 'Gerar link de acesso').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/pessoas/8/convite')).toHaveLength(1);
    expect(tela.text()).toContain(link);

    const zap = tela.find('a[href^="https://wa.me/"]');
    expect(zap.attributes('href')).toContain('wa.me/5511988887777');
    expect(decodeURIComponent(zap.attributes('href'))).toContain(link);

    await botao(tela, 'Copiar link').trigger('click');
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(link);
  });

  it('sem e-mail e sem login, o link de acesso fica travado até preencher o e-mail', async () => {
    const { tela, fetch } = await abrir({
      'GET /pessoas?ministerio_id=1': { corpo: { pessoas: [pessoa(8, 'Bruno Souza', { tem_login: false, email: null })] } },
    });

    await tela.findAll('.linha-pessoa')[0].trigger('click');

    expect(botao(tela, 'Gerar link de acesso').attributes('disabled')).toBeDefined();
    expect(tela.text()).toContain('preencha abaixo antes');
    expect(enviados(fetch, 'POST', '/pessoas/8/convite')).toHaveLength(0);
  });

  it('quem já tem login só pode pedir um novo link, e o e-mail dele fica travado', async () => {
    const { tela } = await abrir();

    await tela.findAll('.linha-pessoa')[0].trigger('click');

    expect(botao(tela, 'Gerar novo link')).toBeTruthy();
    expect(tela.find('#p-email').attributes('disabled')).toBeDefined();
  });

  it('salvar os dados de quem não tem login manda o e-mail; com login, não manda', async () => {
    const { tela, fetch } = await abrir({
      'PATCH /pessoas/8': { corpo: { pessoa: {} } },
      'PATCH /pessoas/7': { corpo: { pessoa: {} } },
    });

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    await tela.find('#p-nome').setValue('Bruno S. Souza');
    await botao(tela, 'Salvar dados').trigger('click');
    await esperar();
    expect(enviados(fetch, 'PATCH', '/pessoas/8')[0].corpo).toEqual({ nome: 'Bruno S. Souza', telefone: '11 98888-7777', email: 'bruno@exemplo.test' });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botao(tela, 'Salvar dados').trigger('click');
    await esperar();
    expect(enviados(fetch, 'PATCH', '/pessoas/7')[0].corpo).not.toHaveProperty('email');
  });

  it('desativar manda ativo falso', async () => {
    const { tela, fetch } = await abrir({ 'PATCH /pessoas/7': { corpo: { pessoa: {} } } });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await tela.find('.interruptor').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PATCH', '/pessoas/7')[0].corpo).toEqual({ ativo: false });
  });

  it('tirar da equipe pede confirmação antes de apagar', async () => {
    const { tela, fetch } = await abrir({ 'DELETE /ministerios/1/equipe/7': { status: 204 } });

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botao(tela, 'Tirar da equipe').trigger('click');
    expect(enviados(fetch, 'DELETE', '/ministerios/1/equipe/7')).toHaveLength(0);

    await botao(tela, 'Confirmar: tirar da equipe').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/ministerios/1/equipe/7')).toHaveLength(1);
  });

  it('cadastra uma pessoa nova já na equipe deste ministério', async () => {
    const { tela, fetch } = await abrir({ 'POST /pessoas': { status: 201, corpo: { pessoa: pessoa(9, 'Carla Dias') } }, 'GET /ministerios/1/pessoas-de-fora': { corpo: { pessoas: [] } } });

    await botao(tela, 'Adicionar pessoa').trigger('click');
    await esperar();
    await tela.find('#np-nome').setValue('  Carla Dias ');
    await tela.find('#np-email').setValue('carla@exemplo.test');
    await botaoExato(tela, 'Cadastrar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/pessoas')[0].corpo).toEqual({ nome: 'Carla Dias', telefone: null, email: 'carla@exemplo.test', ministerio_ids: [1] });
  });

  it('não cadastra sem nome', async () => {
    const { tela, fetch } = await abrir({ 'GET /ministerios/1/pessoas-de-fora': { corpo: { pessoas: [] } } });

    await botao(tela, 'Adicionar pessoa').trigger('click');
    await esperar();
    await tela.find('#np-nome').setValue('   ');

    expect(botaoExato(tela, 'Cadastrar').attributes('disabled')).toBeDefined();
    expect(enviados(fetch, 'POST', '/pessoas')).toHaveLength(0);
  });

  it('inclui quem já está na igreja, mostrando onde a pessoa já serve', async () => {
    const { tela, fetch } = await abrir({
      'GET /ministerios/1/pessoas-de-fora': { corpo: { pessoas: [{ id: 20, nome: 'Rafael Lima', ministerios: ['Mídia'] }] } },
      'POST /ministerios/1/equipe': { status: 201 },
    });

    await botao(tela, 'Adicionar pessoa').trigger('click');
    await esperar();
    await botao(tela, 'Já está na igreja').trigger('click');

    expect(tela.text()).toContain('Rafael Lima');
    expect(tela.text()).toContain('Mídia');

    await botaoExato(tela, 'Incluir').trigger('click');
    await esperar();
    expect(enviados(fetch, 'POST', '/ministerios/1/equipe')[0].corpo).toEqual({ pessoa_id: 20 });
  });
});

describe('equipe do líder: bandas', () => {
  const abrirBandas = (rotas) => abrir(rotas, '/lider/equipe?aba=bandas');

  it('mostra a fila do rodízio com os membros e trava subir a primeira e descer a última', async () => {
    const { tela } = await abrirBandas();
    const linhas = tela.findAll('.linha-pessoa');

    expect(linhas[0].text()).toContain('Banda 1');
    expect(linhas[0].text()).toContain('Helena');
    expect(linhas[1].text()).toContain('Sem membros');
    expect(tela.find('[aria-label="Subir Banda 1"]').attributes('disabled')).toBeDefined();
    expect(tela.find('[aria-label="Descer Banda 2"]').attributes('disabled')).toBeDefined();
  });

  it('descer troca a ordem das duas bandas', async () => {
    const { tela, fetch } = await abrirBandas({ 'PATCH /bandas/1': { corpo: { banda: {} } }, 'PATCH /bandas/2': { corpo: { banda: {} } } });

    await tela.find('[aria-label="Descer Banda 1"]').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PATCH', '/bandas/1')[0].corpo).toEqual({ ordem: 2 });
    expect(enviados(fetch, 'PATCH', '/bandas/2')[0].corpo).toEqual({ ordem: 1 });
  });

  it('cria uma banda e define os membros', async () => {
    const { tela, fetch } = await abrirBandas({
      'POST /ministerios/1/bandas': { status: 201, corpo: { banda: { id: 3, nome: 'Banda 3', ordem: 3, membros: [] } } },
      'PUT /bandas/3/membros': { corpo: {} },
    });

    await botao(tela, 'Nova banda').trigger('click');
    await tela.find('#banda-nome').setValue('Banda 3');
    await botaoExato(tela, 'Bruno Souza').trigger('click');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/ministerios/1/bandas')[0].corpo).toEqual({ nome: 'Banda 3' });
    expect(enviados(fetch, 'PUT', '/bandas/3/membros')[0].corpo).toEqual({ pessoa_ids: [8] });
  });

  it('editar uma banda parte dos membros atuais', async () => {
    const { tela, fetch } = await abrirBandas({ 'PATCH /bandas/1': { corpo: { banda: {} } }, 'PUT /bandas/1/membros': { corpo: {} } });

    await tela.findAll('.linha-pessoa')[0].find('.aberto').trigger('click');
    await botaoExato(tela, 'Helena Costa').trigger('click'); // tira
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/bandas/1/membros')[0].corpo).toEqual({ pessoa_ids: [] });
  });

  it('excluir pede confirmação', async () => {
    const { tela, fetch } = await abrirBandas({ 'DELETE /bandas/2': { status: 204 } });

    await tela.findAll('.linha-pessoa')[1].find('.aberto').trigger('click');
    await botaoExato(tela, 'Excluir').trigger('click');
    expect(enviados(fetch, 'DELETE', '/bandas/2')).toHaveLength(0);

    await botaoExato(tela, 'Confirmar exclusão').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/bandas/2')).toHaveLength(1);
  });

  it('só quem está ativo pode ser escolhido para a banda', async () => {
    const { tela } = await abrirBandas({
      'GET /pessoas?ministerio_id=1': { corpo: { pessoas: [pessoa(7, 'Helena Costa'), pessoa(8, 'Bruno Souza', { ativo: false })] } },
    });

    await botao(tela, 'Nova banda').trigger('click');
    expect(botaoExato(tela, 'Helena Costa')).toBeTruthy();
    expect(botaoExato(tela, 'Bruno Souza')).toBeUndefined();
  });
});

describe('equipe do líder: postos e funções', () => {
  it('cria um posto com descrição e vagas', async () => {
    const { tela, fetch } = await abrir({ 'POST /ministerios/1/postos': { status: 201, corpo: { posto: {} } } }, '/lider/equipe?aba=postos');

    expect(tela.text()).toContain('2 vaga(s) por turno');
    await botao(tela, 'Novo posto').trigger('click');
    await tela.find('#eq-nome').setValue('Teclado');
    await tela.find('#eq-desc').setValue('Palco');
    await tela.find('#eq-vagas').setValue('3');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/ministerios/1/postos')[0].corpo).toEqual({ nome: 'Teclado', descricao: 'Palco', vagas: 3 });
  });

  it('edita um posto existente', async () => {
    const { tela, fetch } = await abrir({ 'PATCH /postos/2': { corpo: { posto: {} } } }, '/lider/equipe?aba=postos');

    await tela.findAll('.linha-pessoa')[1].trigger('click');
    expect(tela.find('#eq-nome').element.value).toBe('Bateria');
    await tela.find('#eq-nome').setValue('Bateria 2');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PATCH', '/postos/2')[0].corpo).toMatchObject({ nome: 'Bateria 2', vagas: 1 });
  });

  it('função só tem nome e usa a rota de funções', async () => {
    const { tela, fetch } = await abrir({ 'POST /ministerios/1/funcoes': { status: 201, corpo: { funcao: {} } } }, '/lider/equipe?aba=funcoes');

    await botao(tela, 'Nova função').trigger('click');
    expect(tela.find('#eq-vagas').exists()).toBe(false);
    await tela.find('#eq-nome').setValue('Baixo');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/ministerios/1/funcoes')[0].corpo).toEqual({ nome: 'Baixo' });
  });

  it('excluir um posto pede confirmação', async () => {
    const { tela, fetch } = await abrir({ 'DELETE /postos/1': { status: 204 } }, '/lider/equipe?aba=postos');

    await tela.findAll('.linha-pessoa')[0].trigger('click');
    await botaoExato(tela, 'Excluir').trigger('click');
    expect(enviados(fetch, 'DELETE', '/postos/1')).toHaveLength(0);
    await botaoExato(tela, 'Confirmar exclusão').trigger('click');
    await esperar();
    expect(enviados(fetch, 'DELETE', '/postos/1')).toHaveLength(1);
  });

  it('erro da API aparece como aviso e a folha continua aberta', async () => {
    const { tela } = await abrir({ 'POST /ministerios/1/funcoes': { status: 422, corpo: { message: 'x', errors: { nome: ['Já existe.'] } } } }, '/lider/equipe?aba=funcoes');

    await botao(tela, 'Nova função').trigger('click');
    await tela.find('#eq-nome').setValue('Voz');
    await botaoExato(tela, 'Salvar').trigger('click');
    await esperar();

    expect(tela.find('#eq-nome').exists()).toBe(true);
  });
});

describe('equipe do líder: navegação', () => {
  it('com o módulo Bandas ligado, mostra a aba Bandas e a de Módulos', async () => {
    const { tela } = await abrir();
    expect(tela.find('[role="tablist"]').findAll('[role="tab"]').map((t) => t.text())).toEqual(['Pessoas', 'Bandas', 'Pedidos', 'Postos', 'Funções', 'Módulos']);
  });

  it('sem o módulo Bandas ligado, a aba Bandas não aparece', async () => {
    const midia = { ...LIDER, ministerios: [{ id: 2, nome: 'Mídia', lider: true }] };
    simularApi({
      'GET /pessoas?ministerio_id=2': { corpo: { pessoas: [] } },
      'GET /ministerios/2/bandas': { corpo: { bandas: [] } },
      'GET /ministerios/2/postos': { corpo: { postos: [] } },
      'GET /ministerios/2/funcoes': { corpo: { funcoes: [] } },
      'GET /ministerios/2/pedidos-de-entrada': { corpo: { total: 0, pedidos: [] } },
      'GET /ministerios/2/modulos': { corpo: { modulos: [{ ...BANDAS_LIGADO, ativo: false }] } },
    });
    const { tela } = await montarTela(LiderEquipe, { entrada: '/lider/equipe', pessoa: midia, modo: 'lider' });

    expect(tela.find('[role="tablist"]').findAll('[role="tab"]').map((t) => t.text())).toEqual(['Pessoas', 'Pedidos', 'Postos', 'Funções', 'Módulos']);
  });

  it('falha ao carregar mostra o erro com "Tentar de novo"', async () => {
    const { tela } = await abrir({ 'GET /ministerios/1/postos': { status: 500, corpo: { message: 'Falhou.' } } });
    expect(tela.text()).toContain('Falhou.');
    expect(botao(tela, 'Tentar de novo')).toBeTruthy();
  });
});

describe('equipe do líder: pedidos de entrada', () => {
  const PEDIDOS = [
    { id: 5, nome: 'Carla Dias', email: 'carla@exemplo.test', telefone: '11 97777-6666', pedido_em: '2026-10-31T10:00:00Z' },
    { id: 6, nome: 'Rita Souza', email: 'rita@exemplo.test', telefone: null, pedido_em: '2026-10-31T11:00:00Z' },
  ];
  const abrirPedidos = (rotas = {}) => abrir({ 'GET /ministerios/1/pedidos-de-entrada': { corpo: { total: 2, pedidos: PEDIDOS } }, ...rotas }, '/lider/equipe?aba=pedidos');

  it('mostra a quantidade na aba e lista quem pediu', async () => {
    const { tela } = await abrirPedidos();

    expect(tela.find('[role="tablist"]').text()).toContain('Pedidos (2)');
    const cartoes = tela.findAll('article.pedido');
    expect(cartoes).toHaveLength(2);
    expect(cartoes[0].text()).toContain('Carla Dias');
    expect(cartoes[0].text()).toContain('carla@exemplo.test · 11 97777-6666');
    expect(cartoes[1].text()).not.toContain('·');
  });

  it('aprovar manda o pedido certo e recarrega a lista', async () => {
    const { tela, fetch } = await abrirPedidos({ 'POST /pedidos-de-entrada/5/aprovar': { status: 201, corpo: { pessoa: {} } } });

    await tela.findAll('article.pedido')[0].findAll('button').find((b) => b.text() === 'Aprovar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/pedidos-de-entrada/5/aprovar')).toHaveLength(1);
    expect(fetch.chamadas.filter((c) => c.caminho === '/ministerios/1/pedidos-de-entrada')).toHaveLength(2);
  });

  it('recusar usa a rota de recusa', async () => {
    const { tela, fetch } = await abrirPedidos({ 'POST /pedidos-de-entrada/6/recusar': { corpo: { ok: true } } });

    await tela.findAll('article.pedido')[1].findAll('button').find((b) => b.text() === 'Recusar').trigger('click');
    await esperar();

    expect(enviados(fetch, 'POST', '/pedidos-de-entrada/6/recusar')).toHaveLength(1);
    expect(enviados(fetch, 'POST', '/pedidos-de-entrada/6/aprovar')).toHaveLength(0);
  });

  it('sem pedidos, avisa; e o link público usa o código da igreja', async () => {
    const lider = { ...LIDER, igreja: { id: 1, nome: 'Igreja Exemplo', codigo: 'igreja-exemplo' } };
    simularApi(rotasBase());
    const { tela } = await montarTela(LiderEquipe, { entrada: '/lider/equipe?aba=pedidos', pessoa: lider, modo: 'lider' });

    expect(tela.text()).toContain('Nenhum pedido');
    expect(tela.find('.divulgar code').text()).toBe(`${window.location.origin}/entrar/igreja-exemplo`);
    expect(decodeURIComponent(tela.find('.divulgar a').attributes('href'))).toContain('/entrar/igreja-exemplo');
  });

  it('sem código da igreja, não mostra o link', async () => {
    const { tela } = await abrirPedidos();
    expect(tela.find('.divulgar').exists()).toBe(false);
  });
});

describe('equipe do líder: módulos', () => {
  const abas = (tela) => tela.find('[role="tablist"]').findAll('[role="tab"]').map((t) => t.text());

  /** Uma API com estado: o PUT muda o que o GET seguinte devolve. */
  const comEstado = (ativo) => {
    const estado = { ativo };
    return {
      'GET /ministerios/1/modulos': () => ({ corpo: { modulos: [{ ...BANDAS_LIGADO, ativo: estado.ativo }] } }),
      'PUT /ministerios/1/modulos': (c) => {
        estado.ativo = c.ativos.includes('bandas');
        return { corpo: { modulos: [{ ...BANDAS_LIGADO, ativo: estado.ativo }] } };
      },
    };
  };

  const abrirModulos = (ativo) => abrir(comEstado(ativo), '/lider/equipe?aba=modulos');

  it('lista cada módulo com a descrição e o interruptor no estado certo', async () => {
    const { tela } = await abrirModulos(true);
    const interruptor = tela.find('.interruptor');

    expect(interruptor.text()).toContain('Bandas');
    expect(interruptor.text()).toContain('Grupos fixos com rodízio.');
    expect(interruptor.attributes('aria-pressed')).toBe('true');
    expect(tela.text()).toContain('Desligar esconde o módulo, mas guarda tudo');
  });

  it('ligar manda a lista de ativos, avisa e faz a aba Bandas aparecer', async () => {
    const { tela, fetch } = await abrirModulos(false);
    expect(abas(tela)).not.toContain('Bandas');

    await tela.find('.interruptor').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/ministerios/1/modulos')[0].corpo).toEqual({ ativos: ['bandas'] });
    expect(useMensagens().texto).toBe('Módulo Bandas ativado.');
    expect(abas(tela)).toContain('Bandas');
    expect(tela.find('.interruptor').attributes('aria-pressed')).toBe('true');
  });

  it('desligar manda a lista vazia, diz que nada foi apagado e a aba Bandas some', async () => {
    const { tela, fetch } = await abrirModulos(true);

    await tela.find('.interruptor').trigger('click');
    await esperar();

    expect(enviados(fetch, 'PUT', '/ministerios/1/modulos')[0].corpo).toEqual({ ativos: [] });
    expect(useMensagens().texto).toBe('Módulo Bandas desativado. Nada foi apagado.');
    expect(abas(tela)).not.toContain('Bandas');
    expect(tela.find('.interruptor').attributes('aria-pressed')).toBe('false');
  });

  it('erro da API aparece e o interruptor continua como estava', async () => {
    const { tela } = await abrirModulos(true);
    simularApi(rotasBase({
      'GET /ministerios/1/modulos': { corpo: { modulos: [BANDAS_LIGADO] } },
      'PUT /ministerios/1/modulos': { status: 403, corpo: { message: 'Sem permissão.' } },
    }));

    await tela.find('.interruptor').trigger('click');
    await esperar();

    expect(useMensagens().tipo).toBe('erro');
    expect(tela.find('.interruptor').attributes('aria-pressed')).toBe('true');
  });

  it('com o módulo desligado, abrir ?aba=bandas cai em Pessoas, mesmo no Louvor', async () => {
    const { tela } = await abrir(comEstado(false), '/lider/equipe?aba=bandas');

    expect(tela.findAll('.linha-pessoa')).toHaveLength(2);
    expect(tela.find('[role="tab"][aria-selected="true"]').text()).toBe('Pessoas');
  });

  it('o subtítulo só fala em banda com o módulo ligado', async () => {
    const ligado = await abrir(comEstado(true));
    expect(ligado.tela.find('.saudacao').text()).toContain('em qual banda');

    const desligado = await abrir(comEstado(false));
    expect(desligado.tela.find('.saudacao').text()).not.toContain('banda');
  });
});

describe('equipe do líder: equipe grande', () => {
  const GRANDE = Array.from({ length: 12 }, (_, i) => pessoa(20 + i, `Voluntário ${String.fromCharCode(65 + i)}`));
  const abrirGrande = (n) => abrir({ 'GET /pessoas?ministerio_id=1': { corpo: { pessoas: GRANDE.slice(0, n) } } });

  it('com 8 pessoas ou menos não tem busca nem contagem', async () => {
    const { tela } = await abrirGrande(8);

    expect(tela.find('#busca-equipe').exists()).toBe(false);
    expect(tela.find('.contagem').exists()).toBe(false);
  });

  it('com mais de 8, mostra a contagem e filtra pelo nome', async () => {
    const { tela } = await abrirGrande(12);

    expect(tela.find('.contagem').text()).toBe('12 pessoa(s)');
    expect(tela.findAll('.linha-pessoa')).toHaveLength(12);

    await tela.find('#busca-equipe').setValue('voluntário c');
    expect(tela.findAll('.linha-pessoa')).toHaveLength(1);
    expect(tela.find('.contagem').text()).toBe('1 de 12 pessoa(s)');

    await tela.find('#busca-equipe').setValue('nada');
    expect(tela.text()).toContain('Ninguém com esse nome');
    expect(tela.find('.linha-pessoa').exists()).toBe(false);
  });

  it('resume os postos da pessoa', async () => {
    const postos = [1, 2, 3, 4].map((id) => ({ id, nome: `Posto ${id}`, descricao: null, vagas: 1 }));
    const { tela } = await abrir({
      'GET /ministerios/1/postos': { corpo: { postos } },
      'GET /pessoas?ministerio_id=1': { corpo: { pessoas: [pessoa(7, 'Helena Costa', { posto_ids: [1, 2, 3, 4] })] } },
    });

    expect(tela.find('.linha-pessoa').text()).toContain('Posto 1 · Posto 2 · +2');
  });
});
