<script setup>
// Os módulos que o ministério usa: o líder liga o que precisa e desliga o resto. Desligar não apaga nada.
import { ref } from 'vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const props = defineProps({
  ministerio: { type: Object, required: true },
  modulos: { type: Array, required: true },
});
const emit = defineEmits(['mudou']);

const mensagens = useMensagens();
const ocupado = ref(false);

async function alternar(modulo) {
  if (ocupado.value) return;
  ocupado.value = true;

  const ativos = props.modulos.filter((m) => m.ativo !== (m.chave === modulo.chave)).map((m) => m.chave);

  try {
    await api.put(`/ministerios/${props.ministerio.id}/modulos`, { ativos });
    mensagens.mostrar(modulo.ativo ? `Módulo ${modulo.nome} desativado. Nada foi apagado.` : `Módulo ${modulo.nome} ativado.`);
    emit('mudou');
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
  }
}
</script>

<template>
  <div class="bloco">
    <p class="dica">Ligue só o que {{ ministerio.nome }} usa. Desligar esconde o módulo, mas guarda tudo: é só ligar de novo.</p>

    <button
      v-for="m in modulos" :key="m.chave" class="interruptor cartao" :aria-pressed="m.ativo" :disabled="ocupado" @click="alternar(m)"
    >
      <span>{{ m.nome }}<br><span class="dica">{{ m.descricao }}</span></span>
      <span class="trilho-interruptor" />
    </button>
  </div>
</template>

<style scoped>
.bloco { display: flex; flex-direction: column; gap: 12px; }
.bloco > .dica { margin: 0 4px; }
</style>
