<script setup>
// Folha que sobe de baixo (o "bottom sheet" do protótipo).
defineProps({ aberta: Boolean, titulo: { type: String, default: '' }, legenda: { type: String, default: '' } });
defineEmits(['fechar']);
</script>

<template>
  <Teleport to="body">
    <div class="veu" :class="{ on: aberta }" @click="$emit('fechar')" />
    <div class="folha" :class="{ on: aberta }" role="dialog" aria-modal="true" :aria-label="titulo" :aria-hidden="!aberta">
      <div class="alca" />
      <h3>{{ titulo }}</h3>
      <p v-if="legenda" class="legenda">{{ legenda }}</p>
      <div class="corpo"><slot /></div>
      <div v-if="$slots.rodape" class="rodape"><slot name="rodape" /></div>
    </div>
  </Teleport>
</template>

<style scoped>
.veu {
  position: fixed; inset: 0; background: rgba(15, 13, 24, 0.5); opacity: 0; pointer-events: none;
  transition: opacity 0.2s; z-index: 20;
}
.veu.on { opacity: 1; pointer-events: auto; }
.folha {
  position: fixed; left: 50%; bottom: 0; width: 100%; max-width: 480px; transform: translate(-50%, 101%);
  background: var(--surface); border-radius: 22px 22px 0 0; box-shadow: var(--shadow-3); z-index: 21;
  transition: transform 0.26s cubic-bezier(0.32, 0.72, 0, 1); max-height: 88dvh; display: flex; flex-direction: column;
  padding-bottom: env(safe-area-inset-bottom);
}
.folha.on { transform: translate(-50%, 0); }
.alca { width: 38px; height: 4px; border-radius: 3px; background: var(--line-2); margin: 9px auto 4px; flex: none; }
h3 { margin: 6px 18px 2px; font-size: 18px; font-weight: 700; letter-spacing: -0.01em; }
.legenda { margin: 0 18px 10px; font-size: 12.5px; color: var(--ink-3); }
.corpo { overflow-y: auto; padding: 0 0 12px; }
.rodape { padding: 12px 16px 14px; border-top: 1px solid var(--line); }
</style>
