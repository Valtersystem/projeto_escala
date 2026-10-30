<script setup>
import { onMounted, ref } from 'vue';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const ORDENS = [[1, '1º'], [2, '2º'], [3, '3º'], [4, '4º'], [5, '5º'], [-1, 'Último']];

const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const cultos = ref([]);

const aberta = ref(false);
const editando = ref(null);
const form = ref({});
const ocupado = ref(false);
const confirmando = ref(false);

const quando = (c) => {
  const dia = DIAS[c.dia_semana];
  if (c.tipo === 'semanal') return `Todo ${dia.toLowerCase()}`;
  const ordem = ORDENS.find(([valor]) => valor === c.ordem)?.[1] ?? `${c.ordem}º`;
  return `${ordem} ${dia.toLowerCase()} do mês`;
};

async function carregar() {
  try {
    cultos.value = (await api.get('/cultos')).cultos;
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

function abrir(culto) {
  editando.value = culto;
  form.value = culto
    ? { nome: culto.nome, tipo: culto.tipo, dia_semana: culto.dia_semana, ordem: culto.ordem, inicio: culto.inicio, fim: culto.fim }
    : { nome: '', tipo: 'semanal', dia_semana: 0, ordem: 1, inicio: '08:00', fim: '11:30' };
  confirmando.value = false;
  aberta.value = true;
}

const valido = () => form.value.nome?.trim() && form.value.inicio && form.value.fim;

async function executar(acao, sucesso) {
  if (ocupado.value) return;
  ocupado.value = true;

  try {
    await acao();
    mensagens.mostrar(sucesso);
    aberta.value = false;
    await carregar();
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
  }
}

function salvar() {
  if (!valido()) return;

  const corpo = {
    nome: form.value.nome.trim(),
    tipo: form.value.tipo,
    dia_semana: Number(form.value.dia_semana),
    inicio: form.value.inicio,
    fim: form.value.fim,
    ...(form.value.tipo === 'mensal' ? { ordem: Number(form.value.ordem) } : {}),
  };

  return executar(
    () => (editando.value ? api.patch(`/cultos/${editando.value.id}`, corpo) : api.post('/cultos', corpo)),
    'Culto salvo.',
  );
}

function excluir() {
  if (!confirmando.value) {
    confirmando.value = true;
    return;
  }

  return executar(() => api.delete(`/cultos/${editando.value.id}`), 'Culto excluído.');
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Cultos</h2>
      <p>A grade da igreja. Os líderes geram os turnos a partir dela.</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>
    <template v-else>
      <div v-if="!cultos.length" class="cartao pad vazio"><b>Nenhum culto</b><p>Cadastre os cultos para os líderes gerarem os turnos.</p></div>
      <div v-else class="cartao lista">
        <button v-for="c in cultos" :key="c.id" class="linha-pessoa" @click="abrir(c)">
          <span class="texto">
            <strong>{{ c.nome }}</strong>
            <small>{{ quando(c) }} · {{ c.inicio }} às {{ c.fim }}</small>
          </span>
        </button>
      </div>
      <button class="botao suave largo" @click="abrir(null)">Novo culto</button>
    </template>

    <Folha :aberta="aberta" :titulo="editando ? 'Editar culto' : 'Novo culto'" legenda="Turnos já gerados continuam como estão." @fechar="aberta = false">
      <div class="miolo">
        <div class="cartao">
          <div class="campo"><label for="c-nome">Nome</label><input id="c-nome" v-model="form.nome" maxlength="120" /></div>
          <div class="campo">
            <label for="c-tipo">Repete</label>
            <select id="c-tipo" v-model="form.tipo"><option value="semanal">Toda semana</option><option value="mensal">Uma vez por mês</option></select>
          </div>
          <div class="campo">
            <label for="c-dia">Dia da semana</label>
            <select id="c-dia" v-model="form.dia_semana"><option v-for="(d, i) in DIAS" :key="i" :value="i">{{ d }}</option></select>
          </div>
          <div v-if="form.tipo === 'mensal'" class="campo">
            <label for="c-ordem">Qual do mês</label>
            <select id="c-ordem" v-model="form.ordem"><option v-for="[v, t] in ORDENS" :key="v" :value="v">{{ t }}</option></select>
          </div>
          <div class="campo"><label for="c-inicio">Início</label><input id="c-inicio" v-model="form.inicio" type="time" /></div>
          <div class="campo"><label for="c-fim">Fim</label><input id="c-fim" v-model="form.fim" type="time" /></div>
        </div>
        <p v-if="confirmando" class="dica">Os turnos já gerados guardam o nome do culto e continuam.</p>
      </div>
      <template #rodape>
        <div class="acoes">
          <button v-if="editando" class="botao perigo" :disabled="ocupado" @click="excluir">{{ confirmando ? 'Confirmar exclusão' : 'Excluir' }}</button>
          <button class="botao primario" :disabled="ocupado || !valido()" @click="salvar">Salvar</button>
        </div>
      </template>
    </Folha>
  </section>
</template>

<style scoped>
section { display: flex; flex-direction: column; gap: 12px; }
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.acoes { display: flex; gap: 9px; }
.acoes .botao { flex: 1; }
.linha-pessoa { text-align: left; }
</style>
