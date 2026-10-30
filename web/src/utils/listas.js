import { computed, ref } from 'vue';

/** "Louvor · Mídia · +3": os primeiros nomes e quantos ficaram de fora. */
export function resumirNomes(nomes, max = 2, vazio = '') {
  if (!nomes.length) return vazio;
  const mostrados = nomes.slice(0, max).join(' · ');
  return nomes.length > max ? `${mostrados} · +${nomes.length - max}` : mostrados;
}

/** Mostra uma lista longa aos poucos: os primeiros itens e um "Mostrar mais". `reiniciar` volta ao começo (ao mudar a busca). */
export function usePagina(itens, tamanho = 30) {
  const ate = ref(tamanho);

  return {
    visiveis: computed(() => itens.value.slice(0, ate.value)),
    restantes: computed(() => Math.max(0, itens.value.length - ate.value)),
    mais: () => { ate.value += tamanho; },
    reiniciar: () => { ate.value = tamanho; },
  };
}
