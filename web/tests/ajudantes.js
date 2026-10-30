import { vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';

/**
 * Troca o fetch por uma API de mentira. `rotas` mapeia "MÉTODO /caminho" para a resposta:
 * um objeto { status, corpo } ou uma função (corpoDaRequisicao) => { status, corpo }.
 * Devolve o fetch simulado, com `chamadas` para conferir o que foi enviado.
 */
export function simularApi(rotas = {}) {
  const chamadas = [];

  const simulado = vi.fn(async (url, opcoes = {}) => {
    const caminho = String(url).replace(/^https?:\/\/[^/]+\/api/, '');
    const metodo = opcoes.method ?? 'GET';
    const corpo = opcoes.body ? JSON.parse(opcoes.body) : undefined;
    chamadas.push({ metodo, caminho, corpo, cabecalhos: opcoes.headers });

    const rota = rotas[`${metodo} ${caminho}`];

    if (!rota) {
      return { ok: false, status: 404, json: async () => ({ message: `Rota não simulada: ${metodo} ${caminho}` }) };
    }

    const { status = 200, corpo: resposta = {} } = typeof rota === 'function' ? rota(corpo) : rota;

    // Cópia, como o JSON que chega pela rede: nunca o mesmo objeto de uma resposta para a outra.
    return { ok: status >= 200 && status < 300, status, json: async () => structuredClone(resposta) };
  });

  simulado.chamadas = chamadas;
  vi.stubGlobal('fetch', simulado);

  return simulado;
}

export const esperar = () => flushPromises();

export const PESSOA = {
  id: 1, nome: 'Helena Costa', email: 'helena@exemplo.test', adm: false,
  igreja: { id: 1, nome: 'Igreja Exemplo' },
  ministerios: [{ id: 1, nome: 'Louvor', lider: false }, { id: 2, nome: 'Mídia', lider: false }],
};

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';
import { criarRoteador } from '../src/router.js';
import { useSessao } from '../src/stores/sessao.js';

/**
 * Monta uma tela com Pinia e roteador de verdade. `entrada` é o caminho aberto antes de montar
 * (útil para o `?volta=` do login); `logada` deixa a sessão pronta, como depois do login.
 */
export async function montarTela(componente, { props = {}, entrada = '/login', logada = true } = {}) {
  const pinia = createPinia();
  setActivePinia(pinia);

  if (logada) {
    localStorage.setItem('escala.token', 'tok-teste');
    const sessao = useSessao();
    sessao.pessoa = PESSOA;
  }

  const roteador = criarRoteador(createMemoryHistory());
  await roteador.push(entrada);
  await roteador.isReady();

  const tela = mount(componente, { props, global: { plugins: [pinia, roteador], stubs: { teleport: true } } });
  await esperar();

  return { tela, roteador, sessao: useSessao() };
}

/** O botão de uma tela que tem este texto. */
export const botao = (tela, texto) => tela.findAll('button').find((b) => b.text().includes(texto));
