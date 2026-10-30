<script setup>
// D-12: quem mudou o quê. Com `ministerioId`, só o histórico daquele ministério (líder); sem ele, a igreja toda (Adm).
import { onMounted, ref, watch } from 'vue';
import Alerta from './Alerta.vue';
import { api } from '../api/cliente.js';

const props = defineProps({ ministerioId: { type: Number, default: null } });

const carregando = ref(true);
const erro = ref('');
const registros = ref([]);
const pagina = ref(1);
const temMais = ref(false);
const tentando = ref(1); // a página que estava sendo buscada, para o "Tentar de novo"

const quando = (iso) => new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

async function carregar(proxima = 1) {
  tentando.value = proxima;
  carregando.value = true;
  erro.value = '';

  try {
    const filtro = props.ministerioId ? `&ministerio_id=${props.ministerioId}` : '';
    const resposta = await api.get(`/auditoria?pagina=${proxima}${filtro}`);
    registros.value = proxima === 1 ? resposta.registros : [...registros.value, ...resposta.registros];
    pagina.value = resposta.pagina;
    temMais.value = resposta.tem_mais;
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

watch(() => props.ministerioId, () => carregar());
onMounted(() => carregar());
</script>

<template>
  <div class="historico">
    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar(tentando)">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando && !registros.length" class="vazio">Carregando…</p>
    <div v-else-if="!registros.length" class="cartao pad vazio"><b>Nada registrado ainda</b><p>As mudanças em escalas, equipes e pessoas aparecem aqui.</p></div>

    <div v-else class="cartao lista">
      <div v-for="r in registros" :key="r.id" class="registro">
        <strong>{{ r.descricao }}</strong>
        <small>{{ r.quem?.nome ?? 'Sistema' }} · {{ quando(r.quando) }}</small>
      </div>
    </div>

    <button v-if="temMais" class="botao contorno largo" :disabled="carregando" @click="carregar(pagina + 1)">Ver mais antigos</button>
  </div>
</template>

<style scoped>
.historico { display: flex; flex-direction: column; gap: 12px; }
.registro { padding: 11px 14px; }
.registro strong { display: block; font-size: 13.5px; font-weight: 600; }
.registro small { display: block; color: var(--ink-3); font-size: 12px; margin-top: 2px; }
</style>
