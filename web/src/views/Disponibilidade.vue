<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import Alerta from '../components/Alerta.vue';
import { api } from '../api/cliente.js';
import { dataCurta, mesPorExtenso } from '../utils/datas.js';

const roteador = useRouter();

const carregando = ref(true);
const erro = ref('');
const escalas = ref([]);

async function carregar() {
  erro.value = '';
  try {
    escalas.value = (await api.get('/escalas/abertas')).escalas;

    // Com uma escala só, não faz sentido pedir para escolher.
    if (escalas.value.length === 1) {
      await roteador.replace({ name: 'disponibilidade-escala', params: { escalaId: escalas.value[0].id } });
    }
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Disponibilidade</h2>
      <p>Marque <b>todos</b> os turnos em que você pode servir. Marcar não é estar escalado: o líder confirma depois.</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <div v-else-if="!escalas.length" class="cartao pad vazio">
      <b>Nenhuma escala aberta</b>
      <p>Quando o líder liberar uma escala do mês, ela aparece aqui. Você será avisado.</p>
    </div>

    <template v-else>
      <RouterLink
        v-for="escala in escalas" :key="escala.id" class="cartao pad escala"
        :to="{ name: 'disponibilidade-escala', params: { escalaId: escala.id } }"
      >
        <strong>{{ escala.ministerio.nome }} · {{ mesPorExtenso(escala.mes) }}</strong>
        <p>{{ escala.marquei }} de {{ escala.total_de_turnos }} turnos marcados<template v-if="escala.prazo"> · até {{ dataCurta(escala.prazo) }}</template></p>
        <span v-if="escala.prazo_encerrado" class="pilula critico"><i />Prazo encerrado</span>
      </RouterLink>
    </template>
  </section>
</template>

<style scoped>
.escala { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; text-decoration: none; color: inherit; }
.escala p { color: var(--ink-3); font-size: 12.5px; }
.vazio { color: var(--ink-3); font-size: 13px; text-align: center; }
.vazio b { display: block; color: var(--ink); margin-bottom: 4px; font-size: 14px; }
.repetir { color: inherit; font-weight: 700; text-decoration: underline; }
</style>
