<script setup>
import Icone from './Icone.vue';

defineProps({
  tipo: { type: String, default: 'info' }, // info | aviso | critico | ok
  titulo: { type: String, default: '' },
  icone: { type: String, default: 'alerta' },
});
</script>

<template>
  <div class="alerta" :class="tipo" role="status">
    <Icone :nome="icone" :tamanho="18" />
    <div>
      <b v-if="titulo">{{ titulo }}</b>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.alerta { display: flex; gap: 10px; padding: 12px 13px; border-radius: var(--r-m); font-size: 13px; line-height: 1.4; align-items: flex-start; }
.alerta svg { flex: none; margin-top: 1px; }
.alerta b { display: block; font-weight: 700; margin-bottom: 1px; }
.info { background: var(--brand-soft); color: var(--brand); border: 1px solid var(--brand-line); }
.aviso { background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn-line); }
.critico { background: var(--crit-soft); color: var(--crit); border: 1px solid var(--crit-line); }
.ok { background: var(--ok-soft); color: var(--ok); border: 1px solid var(--ok-line); }
</style>
