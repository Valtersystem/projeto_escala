<script setup>
// Ícones do protótipo (traço simples, herdam a cor do texto).
defineProps({ nome: { type: String, required: true }, tamanho: { type: Number, default: 20 } });

const CAMINHOS = {
  inicio: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
  calendario: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
  pessoa: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
  certo: '<path d="M4 12.5 9.5 18 20 6.5"/>',
  voltar: '<path d="M15 5l-7 7 7 7"/>',
  troca: '<path d="M4 8h13l-3.5-3.5M20 16H7l3.5 3.5"/>',
  sino: '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 21h4"/>',
  alerta: '<path d="M12 4 2.5 20h19z"/><path d="M12 10v5M12 17.5v.5"/>',
  grafico: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  mais: '<path d="M12 5v14M5 12h14"/>',
  equipe: '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20c0-3.4 3-5.2 6.5-5.2s6.5 1.8 6.5 5.2"/><path d="M17 5.5a3.4 3.4 0 0 1 0 6.6M18.5 14.6c2 .7 3.2 2.2 3.2 4.4"/>',
};
</script>

<template>
  <svg
    :width="tamanho" :height="tamanho" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="CAMINHOS[nome]"
  />
</template>
