<script setup>
// Pedidos de entrada de voluntários (D-15) e o link público para divulgar.
import { computed, ref } from 'vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { useSessao } from '../stores/sessao.js';

defineProps({ pedidos: { type: Array, required: true } });
const emit = defineEmits(['mudou']);

const mensagens = useMensagens();
const sessao = useSessao();
const ocupado = ref(false);

const link = computed(() => {
  const codigo = sessao.pessoa?.igreja?.codigo;
  return codigo ? `${window.location.origin}/entrar/${codigo}` : '';
});

const linkWhatsapp = computed(() => `https://wa.me/?text=${encodeURIComponent(`Quer servir na igreja? Peça para entrar por aqui: ${link.value}`)}`);

async function copiar() {
  try {
    await navigator.clipboard.writeText(link.value);
    mensagens.mostrar('Link copiado.');
  } catch {
    mensagens.mostrar('Não deu para copiar. Segure o link para copiar.');
  }
}

async function resolver(pedido, acao) {
  if (ocupado.value) return;
  ocupado.value = true;

  try {
    await api.post(`/pedidos-de-entrada/${pedido.id}/${acao}`);
    mensagens.mostrar(acao === 'aprovar' ? `${pedido.nome.split(' ')[0]} entrou na equipe.` : 'Pedido recusado.');
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
    emit('mudou');
  }
}
</script>

<template>
  <div class="bloco">
    <div v-if="!pedidos.length" class="cartao pad vazio"><b>Nenhum pedido</b><p>Quando alguém pedir para entrar, aparece aqui.</p></div>

    <article v-for="p in pedidos" :key="p.id" class="cartao pad pedido">
      <div class="texto"><strong>{{ p.nome }}</strong><small>{{ p.email }}<template v-if="p.telefone"> · {{ p.telefone }}</template></small></div>
      <div class="acoes">
        <button class="botao contorno" :disabled="ocupado" @click="resolver(p, 'recusar')">Recusar</button>
        <button class="botao primario" :disabled="ocupado" @click="resolver(p, 'aprovar')">Aprovar</button>
      </div>
    </article>

    <div v-if="link" class="cartao pad divulgar">
      <b>Link para novos voluntários</b>
      <code>{{ link }}</code>
      <p class="dica">Mande no grupo da igreja. Quem pedir cai aqui para você aprovar.</p>
      <div class="acoes">
        <button class="botao contorno" @click="copiar">Copiar link</button>
        <a class="botao suave" :href="linkWhatsapp" target="_blank" rel="noopener">WhatsApp</a>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bloco { display: flex; flex-direction: column; gap: 12px; }
.pedido, .divulgar { display: flex; flex-direction: column; gap: 10px; }
.texto strong { display: block; font-size: 14.5px; }
.texto small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 2px; word-break: break-all; }
.acoes { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
code { word-break: break-all; font-size: 12px; background: var(--surface-2); padding: 8px 10px; border-radius: 8px; user-select: all; }
a.botao { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
</style>
