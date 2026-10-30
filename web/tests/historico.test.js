import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdmHistorico from '../src/views/AdmHistorico.vue';
import LiderRelatorio from '../src/views/LiderRelatorio.vue';
import { ADM, LIDER, botao, esperar, montarTela, simularApi } from './ajudantes.js';

beforeEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
});

const reg = (id, descricao, quem = 'Marcos Reis') => ({ id, quando: '2026-10-31T14:05:00Z', quem: quem && { id: 4, nome: quem }, acao: 'x.y', descricao, antes: null, depois: null });

describe('histórico de alterações', () => {
  it('o Adm vê a igreja toda, do mais novo ao mais antigo, com quem fez', async () => {
    const fetch = simularApi({ 'GET /auditoria?pagina=1': { corpo: { registros: [reg(2, 'Publicou a escala de 2026-11'), reg(1, 'Cadastrou Carla', null)], pagina: 1, tem_mais: false } } });
    const { tela } = await montarTela(AdmHistorico, { entrada: '/adm/historico', pessoa: ADM, modo: 'adm' });

    const linhas = tela.findAll('.registro');
    expect(linhas).toHaveLength(2);
    expect(linhas[0].text()).toContain('Publicou a escala de 2026-11');
    expect(linhas[0].text()).toContain('Marcos Reis');
    expect(linhas[1].text()).toContain('Sistema');
    expect(botao(tela, 'Ver mais antigos')).toBeUndefined();
    expect(fetch.chamadas[0].caminho).toBe('/auditoria?pagina=1');
  });

  it('"Ver mais antigos" busca a próxima página e junta com as que já estão', async () => {
    const fetch = simularApi({
      'GET /auditoria?pagina=1': { corpo: { registros: [reg(2, 'Segunda')], pagina: 1, tem_mais: true } },
      'GET /auditoria?pagina=2': { corpo: { registros: [reg(1, 'Primeira')], pagina: 2, tem_mais: false } },
    });
    const { tela } = await montarTela(AdmHistorico, { entrada: '/adm/historico', pessoa: ADM, modo: 'adm' });

    await botao(tela, 'Ver mais antigos').trigger('click');
    await esperar();

    expect(tela.findAll('.registro').map((l) => l.find('strong').text())).toEqual(['Segunda', 'Primeira']);
    expect(botao(tela, 'Ver mais antigos')).toBeUndefined();
    expect(fetch.chamadas.map((c) => c.caminho)).toEqual(['/auditoria?pagina=1', '/auditoria?pagina=2']);
  });

  it('sem registros, explica o que vai aparecer', async () => {
    simularApi({ 'GET /auditoria?pagina=1': { corpo: { registros: [], pagina: 1, tem_mais: false } } });
    const { tela } = await montarTela(AdmHistorico, { entrada: '/adm/historico', pessoa: ADM, modo: 'adm' });

    expect(tela.text()).toContain('Nada registrado ainda');
  });

  it('erro mostra o aviso e "Tentar de novo" refaz a mesma página', async () => {
    let falhou = false;
    const fetch = simularApi({
      'GET /auditoria?pagina=1': () => {
        if (!falhou) { falhou = true; return { status: 500, corpo: { message: 'Falhou.' } }; }
        return { corpo: { registros: [reg(1, 'Voltou')], pagina: 1, tem_mais: false } };
      },
    });
    const { tela } = await montarTela(AdmHistorico, { entrada: '/adm/historico', pessoa: ADM, modo: 'adm' });

    expect(tela.text()).toContain('Falhou.');
    await botao(tela, 'Tentar de novo').trigger('click');
    await esperar();

    expect(tela.text()).toContain('Voltou');
    expect(fetch.chamadas).toHaveLength(2);
  });

  it('se falhar numa página mais antiga, tentar de novo refaz essa página e mantém as de antes', async () => {
    let falhou = false;
    const fetch = simularApi({
      'GET /auditoria?pagina=1': { corpo: { registros: [reg(2, 'Segunda')], pagina: 1, tem_mais: true } },
      'GET /auditoria?pagina=2': () => {
        if (!falhou) { falhou = true; return { status: 500, corpo: { message: 'Falhou.' } }; }
        return { corpo: { registros: [reg(1, 'Primeira')], pagina: 2, tem_mais: false } };
      },
    });
    const { tela } = await montarTela(AdmHistorico, { entrada: '/adm/historico', pessoa: ADM, modo: 'adm' });

    await botao(tela, 'Ver mais antigos').trigger('click');
    await esperar();
    expect(tela.text()).toContain('Falhou.');

    await botao(tela, 'Tentar de novo').trigger('click');
    await esperar();

    expect(fetch.chamadas.map((c) => c.caminho)).toEqual(['/auditoria?pagina=1', '/auditoria?pagina=2', '/auditoria?pagina=2']);
  });

  it('o líder abre o histórico pelo relatório e só pede o ministério dele', async () => {
    const fetch = simularApi({
      'GET /ministerios/1/relatorio': { corpo: { mes: null, total_de_turnos: 0, total_de_vagas: 0, vagas_preenchidas_pct: 0, turnos_sem_ninguem: 0, voluntarios_ativos: 0, turnos_por_pessoa: [], onde_aperta: [] } },
      'GET /ministerios/1/escalas': { corpo: { escalas: [] } },
      'GET /auditoria?pagina=1&ministerio_id=1': { corpo: { registros: [reg(3, 'Mudou o prazo da escala de 2026-11')], pagina: 1, tem_mais: false } },
    });
    const { tela } = await montarTela(LiderRelatorio, { entrada: '/lider/relatorio', pessoa: LIDER, modo: 'lider' });

    expect(fetch.chamadas.some((c) => c.caminho.startsWith('/auditoria'))).toBe(false);
    await botao(tela, 'Histórico de alterações').trigger('click');
    await esperar();

    expect(tela.text()).toContain('Mudou o prazo da escala de 2026-11');
    expect(fetch.chamadas.find((c) => c.caminho.startsWith('/auditoria')).caminho).toBe('/auditoria?pagina=1&ministerio_id=1');
  });
});
