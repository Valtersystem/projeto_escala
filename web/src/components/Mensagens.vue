<script setup>
import { useMensagens } from '../stores/mensagens.js';

const mensagens = useMensagens();
</script>

<template>
  <div class="mensagem" :class="[mensagens.tipo, { on: mensagens.texto }]" role="status" aria-live="polite">
    {{ mensagens.texto }}
  </div>
</template>

<style scoped>
.mensagem {
  position: fixed; left: 14px; right: 14px; top: calc(72px + env(safe-area-inset-top)); max-width: 452px; margin: 0 auto; z-index: 30;
  background: var(--ink); color: var(--bg); border-radius: 13px; padding: 12px 15px; font-size: 13.5px; font-weight: 500;
  box-shadow: var(--shadow-2); transform: translateY(-14px); opacity: 0; pointer-events: none;
  transition: opacity 0.2s, transform 0.2s;
}
.mensagem.on { opacity: 1; transform: translateY(0); }
.mensagem.erro { background: var(--crit); color: #fff; }
</style>
