<script setup>
// Link de acesso da pessoa: gerar, copiar e mandar pelo WhatsApp (o líder e o Adm usam o mesmo bloco).
import { computed, ref, watch } from 'vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const props = defineProps({ pessoa: { type: Object, required: true } });

const mensagens = useMensagens();
const convite = ref(null);
const ocupado = ref(false);

// Outra pessoa na folha: o link da anterior não vale aqui.
watch(() => props.pessoa.id, () => { convite.value = null; });

const semEmail = computed(() => !props.pessoa.tem_login && !props.pessoa.email);

async function gerar() {
  if (ocupado.value) return;
  ocupado.value = true;

  try {
    convite.value = await api.post(`/pessoas/${props.pessoa.id}/convite`);
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
  }
}

async function copiar() {
  try {
    await navigator.clipboard.writeText(convite.value.link);
    mensagens.mostrar('Link copiado.');
  } catch {
    mensagens.mostrar('Não deu para copiar. Segure o link para copiar.');
  }
}

const linkWhatsapp = computed(() => {
  if (!convite.value) return '';
  const texto = `Oi, ${props.pessoa.nome.split(' ')[0]}! Este é o seu acesso à escala: ${convite.value.link} (vale por 7 dias)`;
  const numero = (props.pessoa.telefone ?? '').replace(/\D/g, '');
  return `https://wa.me/${numero ? (numero.length <= 11 ? `55${numero}` : numero) : ''}?text=${encodeURIComponent(texto)}`;
});
</script>

<template>
  <div class="convite-da-pessoa">
    <div v-if="convite" class="cartao pad convite">
      <b>Link de acesso</b>
      <code>{{ convite.link }}</code>
      <p class="dica">Vale por 7 dias e só pode ser usado uma vez.</p>
      <div class="duas">
        <button class="botao contorno" @click="copiar">Copiar link</button>
        <a class="botao primario" :href="linkWhatsapp" target="_blank" rel="noopener">WhatsApp</a>
      </div>
    </div>
    <button v-else class="botao suave largo" :disabled="ocupado || semEmail" @click="gerar">
      {{ pessoa.tem_login ? 'Gerar novo link (nova senha)' : 'Gerar link de acesso' }}
    </button>
    <p v-if="semEmail && !convite" class="dica">Sem e-mail cadastrado a pessoa não consegue criar o login: preencha abaixo antes.</p>
  </div>
</template>

<style scoped>
.convite-da-pessoa { display: flex; flex-direction: column; gap: 10px; }
.convite { display: flex; flex-direction: column; gap: 8px; }
.convite code { word-break: break-all; font-size: 12px; background: var(--surface-2); padding: 8px 10px; border-radius: 8px; user-select: all; }
.duas { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
a.botao { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
</style>
