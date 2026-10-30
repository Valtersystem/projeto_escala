import { beforeEach, describe, expect, it, vi } from 'vitest';
import DisponibilidadeEscala from '../src/views/DisponibilidadeEscala.vue';
import { useMensagens } from '../src/stores/mensagens.js';
import { botao, esperar, montarTela, simularApi } from './ajudantes.js';

const turno = (id, extra = {}) => ({
  id, escala_id: 7, data: '2026-11-08', inicio: '08:00', fim: '11:30', culto: 'Culto da Manhã',
  posto: { id: 1, nome: 'Som' }, banda: { id: 1, nome: 'Banda 1' },
  marquei: false, do_meu_perfil: true, choques: [], mesmo_dia: [], ...extra,
});

const choque = { turno_id: 900, ministerio: 'Mídia', posto: 'Câmera', data: '2026-11-08', inicio: '08:00', fim: '11:30' };

/** API de mentira da tela: o PUT altera o estado e o GET devolve o estado atual, como a API de verdade. */
function api({ turnos, encerrado = false, aoMarcar } = {}) {
  const estado = { turnos };

  return {
    estado,
    rotas: {
      'GET /escalas/7/disponibilidade': () => ({
        corpo: {
          escala: { id: 7, mes: '2026-11', prazo: '2026-11-25', prazo_encerrado: encerrado },
          total_marcados: estado.turnos.filter((t) => t.marquei).length,
          turnos: estado.turnos,
        },
      }),
      ...Object.fromEntries(turnos.map((t) => [`PUT /turnos/${t.id}/disponibilidade`, (corpo) => {
        if (aoMarcar) return aoMarcar(t, corpo, estado);
        t.marquei = corpo.marcado;
        return { corpo: { marcado: corpo.marcado, choques: [] } };
      }])),
    },
  };
}

const abrir = (rotas) => {
  simularApi(rotas);
  return montarTela(DisponibilidadeEscala, { props: { escalaId: '7' }, entrada: '/disponibilidade/7' });
};

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

describe('marcar disponibilidade', () => {
  it('mostra cada turno com data, culto, horário, posto e banda', async () => {
    const { rotas } = api({ turnos: [turno(101), turno(102, { data: '2026-11-15', do_meu_perfil: false })] });
    const { tela } = await abrir(rotas);

    const linhas = tela.findAll('button.turno');
    expect(linhas).toHaveLength(2);
    expect(linhas[0].text()).toContain('dom, 08 de novembro · Culto da Manhã');
    expect(linhas[0].text()).toContain('08:00 às 11:30 · Som · Banda 1');
    expect(linhas[0].text()).not.toContain('fora do seu perfil');
    expect(linhas[1].text()).toContain('fora do seu perfil');
    expect(tela.text()).toContain('novembro de 2026');
    expect(tela.text()).toContain('Responder até 25 de novembro');
  });

  it('nunca mostra a quantidade de vagas', async () => {
    const { rotas } = api({ turnos: [turno(101)] });
    const { tela } = await abrir(rotas);

    expect(tela.text()).not.toMatch(/vaga/i);
  });

  it('tocar marca o turno, avisa a API e atualiza a contagem', async () => {
    const { rotas } = api({ turnos: [turno(101), turno(102, { data: '2026-11-15' })] });
    const fetch = simularApi(rotas);
    const { tela } = await montarTela(DisponibilidadeEscala, { props: { escalaId: '7' }, entrada: '/disponibilidade/7' });

    expect(tela.text()).toContain('Nenhum turno marcado ainda');

    await tela.findAll('button.turno')[0].trigger('click');
    await esperar();

    expect(fetch.chamadas.find((c) => c.metodo === 'PUT').corpo).toEqual({ marcado: true });
    expect(tela.findAll('button.turno')[0].attributes('aria-pressed')).toBe('true');
    expect(tela.text()).toContain('1 turno(s) marcado(s)');

    await tela.findAll('button.turno')[0].trigger('click');
    await esperar();

    expect(fetch.chamadas.filter((c) => c.metodo === 'PUT').at(-1).corpo).toEqual({ marcado: false });
    expect(tela.text()).toContain('Nenhum turno marcado ainda');
  });

  it('avisa o choque na hora e mostra com qual turno', async () => {
    const { rotas } = api({
      turnos: [turno(101), turno(102)],
      aoMarcar: (t, corpo, estado) => {
        t.marquei = corpo.marcado;
        // Marcar o 101 faz o 102 passar a chocar, e a resposta já traz o choque.
        estado.turnos[1].choques = corpo.marcado ? [choque] : [];
        return { corpo: { marcado: corpo.marcado, choques: t.id === 102 ? [choque] : [] } };
      },
    });
    const { tela } = await abrir(rotas);

    await tela.findAll('button.turno')[0].trigger('click');
    await esperar();

    expect(tela.findAll('button.turno')[1].text()).toContain('⚠ Choca com Mídia · Câmera 08:00–11:30');

    await tela.findAll('button.turno')[1].trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('Atenção: esse horário choca com outra escala sua.');
    expect(tela.text()).toContain('1 com choque');
  });

  it('mesmo dia sem sobreposição é só um aviso leve', async () => {
    const { rotas } = api({ turnos: [turno(101, { mesmo_dia: [{ ...choque, inicio: '19:00', fim: '21:00' }] })] });
    const { tela } = await abrir(rotas);

    expect(tela.find('button.turno').text()).toContain('Mesmo dia de Mídia · Câmera 19:00–21:00');
    expect(tela.find('button.turno').text()).not.toContain('Choca');
  });

  it('prazo encerrado trava tudo e explica', async () => {
    const { rotas } = api({ turnos: [turno(101)], encerrado: true });
    const fetch = simularApi(rotas);
    const { tela } = await montarTela(DisponibilidadeEscala, { props: { escalaId: '7' }, entrada: '/disponibilidade/7' });

    expect(tela.text()).toContain('O prazo terminou');
    expect(tela.find('button.turno').attributes('disabled')).toBeDefined();

    await tela.find('button.turno').trigger('click');
    expect(fetch.chamadas.filter((c) => c.metodo === 'PUT')).toHaveLength(0);
  });

  it('erro da API ao marcar vira mensagem e a tela recarrega', async () => {
    const { rotas } = api({
      turnos: [turno(101)],
      aoMarcar: () => ({ status: 422, corpo: { message: 'O prazo para responder esta escala terminou. Fale com o líder.' } }),
    });
    const fetch = simularApi(rotas);
    const { tela } = await montarTela(DisponibilidadeEscala, { props: { escalaId: '7' }, entrada: '/disponibilidade/7' });
    const antes = fetch.chamadas.filter((c) => c.metodo === 'GET').length;

    await tela.find('button.turno').trigger('click');
    await esperar();

    expect(useMensagens().texto).toBe('O prazo para responder esta escala terminou. Fale com o líder.');
    expect(useMensagens().tipo).toBe('erro');
    expect(fetch.chamadas.filter((c) => c.metodo === 'GET').length).toBe(antes + 1);
  });

  it('falha ao carregar mostra o erro com "tentar de novo"', async () => {
    simularApi({ 'GET /escalas/7/disponibilidade': { status: 403, corpo: { message: 'Você não está na equipe deste ministério.' } } });
    const { tela } = await montarTela(DisponibilidadeEscala, { props: { escalaId: '7' }, entrada: '/disponibilidade/7' });

    expect(tela.text()).toContain('Você não está na equipe deste ministério.');
    expect(botao(tela, 'Tentar de novo')).toBeTruthy();
  });
});

describe('enviar disponibilidade', () => {
  it('só libera com ao menos um turno marcado', async () => {
    const { rotas } = api({ turnos: [turno(101)] });
    const { tela } = await abrir(rotas);

    expect(botao(tela, 'Enviar disponibilidade').attributes('disabled')).toBeDefined();

    await tela.find('button.turno').trigger('click');
    await esperar();

    expect(botao(tela, 'Enviar disponibilidade').attributes('disabled')).toBeUndefined();
  });

  it('mostra o resumo com o aviso de choque e leva ao início', async () => {
    const { rotas } = api({ turnos: [turno(101, { marquei: true })] });
    simularApi({ ...rotas, 'POST /escalas/7/disponibilidade/enviar': { corpo: { total: 2, com_choque: 1 } } });
    const { tela, roteador } = await montarTela(DisponibilidadeEscala, { props: { escalaId: '7' }, entrada: '/disponibilidade/7' });

    await botao(tela, 'Enviar disponibilidade').trigger('click');
    await esperar();

    expect(tela.text()).toContain('Disponibilidade enviada');
    expect(tela.text()).toContain('Você marcou 2 turno(s)');
    expect(tela.text()).toContain('1 turno(s) chocam com outro horário seu');

    await botao(tela, 'Entendi').trigger('click');
    await vi.waitFor(() => expect(roteador.currentRoute.value.name).toBe('inicio'));
  });
});
