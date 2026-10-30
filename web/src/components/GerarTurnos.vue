<script setup>
import { ref, watch } from 'vue';
import Alerta from './Alerta.vue';
import Folha from './Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { mesPorExtenso } from '../utils/datas.js';

const props = defineProps({ aberta: Boolean, escala: { type: Object, required: true } });
const emit = defineEmits(['fechar', 'gerado']);

const mensagens = useMensagens();

const carregando = ref(false);
const erro = ref('');
const cultos = ref([]); // só os que acontecem neste mês, com as datas
const postos = ref([]);
const cultosEscolhidos = ref([]);
const postosEscolhidos = ref([]);
const enviando = ref(false);

async function carregar() {
  carregando.value = true;
  erro.value = '';
  cultosEscolhidos.value = [];

  try {
    const [grade, listaDePostos] = await Promise.all([
      api.get('/cultos'),
      api.get(`/ministerios/${props.escala.ministerio_id}/postos`),
    ]);

    const comDatas = await Promise.all(
      grade.cultos.map(async (culto) => ({ ...culto, datas: (await api.get(`/cultos/${culto.id}/ocorrencias?mes=${props.escala.mes}`)).datas })),
    );

    cultos.value = comDatas.filter((c) => c.datas.length);
    postos.value = listaDePostos.postos;
    // Com um posto só, não há o que escolher.
    postosEscolhidos.value = postos.value.length === 1 ? [postos.value[0].id] : [];
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

const alternarItem = (lista, id) => (lista.includes(id) ? lista.filter((x) => x !== id) : [...lista, id]);
const alternarCulto = (id) => { cultosEscolhidos.value = alternarItem(cultosEscolhidos.value, id); };
const alternarPosto = (id) => { postosEscolhidos.value = alternarItem(postosEscolhidos.value, id); };

async function gerar() {
  enviando.value = true;
  try {
    const { total } = await api.post(`/escalas/${props.escala.id}/turnos/gerar`, {
      culto_ids: cultosEscolhidos.value, posto_ids: postosEscolhidos.value,
    });
    mensagens.mostrar(`${total} turno(s) gerado(s).`);
    emit('gerado');
  } catch (e) {
    mensagens.erro(e);
  } finally {
    enviando.value = false;
  }
}

watch(() => props.aberta, (aberta) => aberta && carregar());
</script>

<template>
  <Folha :aberta="aberta" :titulo="`Gerar turnos de ${mesPorExtenso(escala.mes)}`" legenda="Escolha os cultos e os postos. Se houver bandas, o rodízio já entra." @fechar="emit('fechar')">
    <div class="miolo">
      <Alerta v-if="erro" tipo="critico">{{ erro }}</Alerta>
      <p v-else-if="carregando" class="vazio">Carregando…</p>

      <template v-else>
        <div class="rotulo"><span>Cultos deste mês</span></div>
        <p v-if="!cultos.length" class="vazio">Nenhum culto da grade cai neste mês. O Adm cadastra os cultos em Cultos.</p>
        <div v-else class="chips">
          <button
            v-for="culto in cultos" :key="culto.id" class="chip" :aria-pressed="cultosEscolhidos.includes(culto.id)"
            @click="alternarCulto(culto.id)"
          >{{ culto.nome }} · {{ culto.inicio }} ({{ culto.datas.length }})</button>
        </div>

        <div class="rotulo"><span>Postos do ministério</span></div>
        <p v-if="!postos.length" class="vazio">Crie um posto antes de gerar turnos.</p>
        <div v-else class="chips">
          <button
            v-for="posto in postos" :key="posto.id" class="chip" :aria-pressed="postosEscolhidos.includes(posto.id)"
            @click="alternarPosto(posto.id)"
          >{{ posto.nome }}</button>
        </div>
      </template>
    </div>

    <template #rodape>
      <button class="botao primario largo" :disabled="enviando || !cultosEscolhidos.length || !postosEscolhidos.length" @click="gerar">
        {{ enviando ? 'Gerando…' : 'Gerar turnos' }}
      </button>
    </template>
  </Folha>
</template>

<style scoped>
.miolo { padding: 2px 18px 8px; display: flex; flex-direction: column; gap: 12px; }
.rotulo { margin: 4px 0 -2px; }
</style>
