<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Alerta from '../components/Alerta.vue';
import EquipePedidos from '../components/EquipePedidos.vue';
import EquipePessoas from '../components/EquipePessoas.vue';
import EquipeBandas from '../components/EquipeBandas.vue';
import EquipeLista from '../components/EquipeLista.vue';
import { api } from '../api/cliente.js';
import { useModo } from '../stores/modo.js';

const ABAS = [
  { chave: 'pessoas', texto: 'Pessoas' },
  { chave: 'bandas', texto: 'Bandas' },
  { chave: 'pedidos', texto: 'Pedidos' },
  { chave: 'postos', texto: 'Postos' },
  { chave: 'funcoes', texto: 'Funções' },
];

const modo = useModo();
const rota = useRoute();
const roteador = useRouter();

const carregando = ref(true);
const erro = ref('');
const pessoas = ref([]);
const bandas = ref([]);
const postos = ref([]);
const funcoes = ref([]);
const pedidos = ref([]);

const aba = computed(() => (ABAS.some((a) => a.chave === rota.query.aba) ? rota.query.aba : 'pessoas'));
const ministerio = computed(() => modo.ministerio);

// Bandas só fazem sentido onde há rodízio de bandas; os outros ministérios veem as outras três abas.
const abas = computed(() => (bandas.value.length || aba.value === 'bandas' || ministerio.value?.nome === 'Louvor' ? ABAS : ABAS.filter((a) => a.chave !== 'bandas')));

const escolher = (chave) => roteador.replace({ query: { ...rota.query, aba: chave } });

async function carregar() {
  if (!ministerio.value) return;
  const id = ministerio.value.id;

  try {
    const [p, b, po, f, pe] = await Promise.all([
      api.get(`/pessoas?ministerio_id=${id}`),
      api.get(`/ministerios/${id}/bandas`),
      api.get(`/ministerios/${id}/postos`),
      api.get(`/ministerios/${id}/funcoes`),
      api.get(`/ministerios/${id}/pedidos-de-entrada`),
    ]);
    pessoas.value = p.pessoas;
    bandas.value = b.bandas;
    postos.value = po.postos;
    funcoes.value = f.funcoes;
    pedidos.value = pe.pedidos;
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

watch(() => ministerio.value?.id, carregar);
onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Equipe</h2>
      <p>{{ ministerio?.nome }} · quem serve, em quê e em qual banda</p>
    </div>

    <div class="segmentos" role="tablist">
      <button
        v-for="a in abas" :key="a.chave" role="tab" :aria-selected="aba === a.chave" @click="escolher(a.chave)"
      >{{ a.texto }}<template v-if="a.chave === 'pedidos' && pedidos.length"> ({{ pedidos.length }})</template></button>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <template v-else-if="ministerio">
      <EquipePessoas v-if="aba === 'pessoas'" :ministerio="ministerio" :pessoas="pessoas" :postos="postos" :funcoes="funcoes" @mudou="carregar" />
      <EquipeBandas v-else-if="aba === 'bandas'" :ministerio="ministerio" :bandas="bandas" :pessoas="pessoas" @mudou="carregar" />
      <EquipePedidos v-else-if="aba === 'pedidos'" :pedidos="pedidos" @mudou="carregar" />
      <EquipeLista
        v-else-if="aba === 'postos'" :key="'postos'" tipo="postos" :ministerio="ministerio" :itens="postos" @mudou="carregar"
      />
      <EquipeLista v-else :key="'funcoes'" tipo="funcoes" :ministerio="ministerio" :itens="funcoes" @mudou="carregar" />
    </template>
  </section>
</template>
