<script setup>
// Postos e funções: a mesma lista simples (nome, e nos postos a descrição e as vagas).
import { computed, ref } from 'vue';
import Folha from './Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const props = defineProps({
  tipo: { type: String, required: true }, // 'postos' | 'funcoes'
  ministerio: { type: Object, required: true },
  itens: { type: Array, required: true },
});
const emit = defineEmits(['mudou']);

const mensagens = useMensagens();
const ehPosto = computed(() => props.tipo === 'postos');
const rotulo = computed(() => (ehPosto.value ? 'posto' : 'função'));
const prefixo = computed(() => (ehPosto.value ? 'postos' : 'funcoes'));

const aberta = ref(false);
const editando = ref(null);
const nome = ref('');
const descricao = ref('');
const vagas = ref(1);
const ocupado = ref(false);
const confirmando = ref(false);

function abrir(item) {
  editando.value = item;
  nome.value = item?.nome ?? '';
  descricao.value = item?.descricao ?? '';
  vagas.value = item?.vagas ?? 1;
  confirmando.value = false;
  aberta.value = true;
}

async function salvar() {
  if (ocupado.value || !nome.value.trim()) return;
  ocupado.value = true;

  const corpo = { nome: nome.value.trim() };
  if (ehPosto.value) Object.assign(corpo, { descricao: descricao.value.trim() || null, vagas: Number(vagas.value) || 1 });

  try {
    if (editando.value) await api.patch(`/${prefixo.value}/${editando.value.id}`, corpo);
    else await api.post(`/ministerios/${props.ministerio.id}/${prefixo.value}`, corpo);
    mensagens.mostrar(editando.value ? 'Alterações salvas.' : `${ehPosto.value ? 'Posto' : 'Função'} criado.`);
    aberta.value = false;
    emit('mudou');
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
  }
}

async function excluir() {
  if (ocupado.value) return;
  if (!confirmando.value) {
    confirmando.value = true;
    return;
  }
  ocupado.value = true;

  try {
    await api.delete(`/${prefixo.value}/${editando.value.id}`);
    mensagens.mostrar(`${ehPosto.value ? 'Posto' : 'Função'} excluído.`);
    aberta.value = false;
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
    <div v-if="!itens.length" class="cartao pad vazio">
      <b>Nenhum {{ rotulo }} ainda</b>
      <p>{{ ehPosto ? 'Postos são os lugares da escala: Recepção, Câmera, Voz…' : 'Funções são o que a pessoa faz no turno: Voz, Guitarra, Bateria…' }}</p>
    </div>
    <div v-else class="cartao lista">
      <button v-for="i in itens" :key="i.id" class="linha-pessoa" @click="abrir(i)">
        <span class="texto">
          <strong>{{ i.nome }}</strong>
          <small v-if="ehPosto">{{ i.vagas }} vaga(s) por turno<template v-if="i.descricao"> · {{ i.descricao }}</template></small>
        </span>
      </button>
    </div>
    <button class="botao suave largo" @click="abrir(null)">{{ ehPosto ? "Novo posto" : "Nova função" }}</button>

    <Folha :aberta="aberta" :titulo="editando ? `Editar ${rotulo}` : (ehPosto ? 'Novo posto' : 'Nova função')" @fechar="aberta = false">
      <form class="cartao" style="margin: 0 16px" @submit.prevent="salvar">
        <div class="campo"><label for="eq-nome">Nome</label><input id="eq-nome" v-model="nome" maxlength="120" required /></div>
        <template v-if="ehPosto">
          <div class="campo"><label for="eq-desc">Descrição (opcional)</label><input id="eq-desc" v-model="descricao" maxlength="255" /></div>
          <div class="campo"><label for="eq-vagas">Vagas por turno</label><input id="eq-vagas" v-model="vagas" type="number" min="1" /></div>
        </template>
      </form>
      <template #rodape>
        <div class="acoes">
          <button v-if="editando" class="botao perigo" :disabled="ocupado" @click="excluir">{{ confirmando ? 'Confirmar exclusão' : 'Excluir' }}</button>
          <button class="botao primario" :disabled="ocupado || !nome.trim()" @click="salvar">Salvar</button>
        </div>
      </template>
    </Folha>
  </div>
</template>

<style scoped>
.bloco { display: flex; flex-direction: column; gap: 12px; }
.acoes { display: flex; gap: 9px; }
.acoes .botao { flex: 1; }
.linha-pessoa { text-align: left; }
</style>
