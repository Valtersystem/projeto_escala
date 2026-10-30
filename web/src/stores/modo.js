import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { api } from '../api/cliente.js';
import { useSessao } from './sessao.js';

const ler = (chave) => {
  try {
    return localStorage.getItem(chave);
  } catch {
    return null;
  }
};

const gravar = (chave, valor) => {
  try {
    if (valor === null || valor === undefined) localStorage.removeItem(chave);
    else localStorage.setItem(chave, String(valor));
  } catch {
    /* sem armazenamento: vale só enquanto o app estiver aberto */
  }
};

/**
 * Quem lidera um ministério usa o app de dois jeitos: como voluntário (marca disponibilidade,
 * vê a própria escala) e como líder (monta a escala). Aqui fica qual dos dois está ativo e,
 * para quem lidera mais de um ministério, qual deles.
 */
export const useModo = defineStore('modo', () => {
  const sessao = useSessao();

  const escolhido = ref(ler('escala.modo') === 'lider' ? 'lider' : 'voluntario');
  const ministerioEscolhido = ref(Number(ler('escala.ministerio')) || null);
  const trocasAbertas = ref(0);

  const ministeriosDoLider = computed(() => sessao.ministerios.filter((m) => m.lider));
  const ehLider = computed(() => ministeriosDoLider.value.length > 0);

  /** Quem não lidera nada nunca está no modo líder, mesmo que o aparelho lembre disso. */
  const modo = computed(() => (ehLider.value && escolhido.value === 'lider' ? 'lider' : 'voluntario'));

  const ministerio = computed(
    () => ministeriosDoLider.value.find((m) => m.id === ministerioEscolhido.value) ?? ministeriosDoLider.value[0] ?? null,
  );

  function usar(novoModo) {
    escolhido.value = novoModo === 'lider' ? 'lider' : 'voluntario';
    gravar('escala.modo', escolhido.value);
  }

  function escolherMinisterio(id) {
    ministerioEscolhido.value = id;
    gravar('escala.ministerio', id);
    trocasAbertas.value = 0;
  }

  /** O número da aba "Trocas": pedidos de substituição esperando o líder. */
  async function atualizarTrocas() {
    if (!ministerio.value) {
      trocasAbertas.value = 0;
      return;
    }

    try {
      trocasAbertas.value = (await api.get(`/ministerios/${ministerio.value.id}/trocas`)).total_abertas;
    } catch {
      /* o número é um enfeite: se falhar, a tela de trocas mostra o erro de verdade */
    }
  }

  return { modo, ehLider, ministeriosDoLider, ministerio, trocasAbertas, usar, escolherMinisterio, atualizarTrocas };
});
