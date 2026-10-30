<script setup>
// Bandas do rodízio: a ordem da fila e quem toca em cada uma (membros fixos, RN-15).
import { ref } from 'vue';
import Folha from './Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const props = defineProps({
  ministerio: { type: Object, required: true },
  bandas: { type: Array, required: true },
  pessoas: { type: Array, required: true },
});
const emit = defineEmits(['mudou']);

const mensagens = useMensagens();
const aberta = ref(false);
const editando = ref(null);
const nome = ref('');
const membros = ref([]);
const ocupado = ref(false);
const confirmando = ref(false);

const equipeAtiva = () => props.pessoas.filter((p) => p.ativo);

function abrir(banda) {
  editando.value = banda;
  nome.value = banda?.nome ?? '';
  membros.value = banda ? banda.membros.map((m) => m.id) : [];
  confirmando.value = false;
  aberta.value = true;
}

function alternar(id) {
  membros.value = membros.value.includes(id) ? membros.value.filter((m) => m !== id) : [...membros.value, id];
}

async function executar(acao, sucesso) {
  if (ocupado.value) return false;
  ocupado.value = true;

  try {
    await acao();
    if (sucesso) mensagens.mostrar(sucesso);
    emit('mudou');
    return true;
  } catch (e) {
    mensagens.erro(e);
    return false;
  } finally {
    ocupado.value = false;
  }
}

async function salvar() {
  if (!nome.value.trim()) return;

  const ok = await executar(async () => {
    let banda = editando.value;
    if (banda) await api.patch(`/bandas/${banda.id}`, { nome: nome.value.trim() });
    else banda = (await api.post(`/ministerios/${props.ministerio.id}/bandas`, { nome: nome.value.trim() })).banda;
    await api.put(`/bandas/${banda.id}/membros`, { pessoa_ids: membros.value });
  }, 'Banda salva.');

  if (ok) aberta.value = false;
}

async function excluir() {
  if (!confirmando.value) {
    confirmando.value = true;
    return;
  }

  const ok = await executar(() => api.delete(`/bandas/${editando.value.id}`), 'Banda excluída.');
  if (ok) aberta.value = false;
}

/** Troca a posição de duas bandas na fila do rodízio. */
function mover(indice, passo) {
  const a = props.bandas[indice];
  const b = props.bandas[indice + passo];
  if (!a || !b) return;

  executar(async () => {
    await api.patch(`/bandas/${a.id}`, { ordem: b.ordem });
    await api.patch(`/bandas/${b.id}`, { ordem: a.ordem });
  });
}
</script>

<template>
  <div class="bloco">
    <p class="dica">A fila abaixo é a ordem do rodízio: a primeira banda abre o primeiro turno e assim por diante.</p>

    <div v-if="!bandas.length" class="cartao pad vazio"><b>Nenhuma banda ainda</b><p>Crie as bandas e escolha quem toca em cada uma.</p></div>
    <div v-else class="cartao lista">
      <div v-for="(b, i) in bandas" :key="b.id" class="linha-pessoa">
        <span class="inicial">{{ i + 1 }}</span>
        <button class="texto aberto" @click="abrir(b)">
          <strong>{{ b.nome }}</strong>
          <small>{{ b.membros.length ? b.membros.map((m) => m.nome.split(' ')[0]).join(', ') : 'Sem membros' }}</small>
        </button>
        <button class="botao contorno pequeno" :disabled="ocupado || i === 0" :aria-label="`Subir ${b.nome}`" @click="mover(i, -1)">↑</button>
        <button class="botao contorno pequeno" :disabled="ocupado || i === bandas.length - 1" :aria-label="`Descer ${b.nome}`" @click="mover(i, 1)">↓</button>
      </div>
    </div>
    <button class="botao suave largo" @click="abrir(null)">Nova banda</button>

    <Folha :aberta="aberta" :titulo="editando ? 'Editar banda' : 'Nova banda'" legenda="Quem está marcado toca nesta banda; o líder ainda pode escalar outras pessoas no turno." @fechar="aberta = false">
      <div class="miolo">
        <div class="cartao"><div class="campo"><label for="banda-nome">Nome</label><input id="banda-nome" v-model="nome" maxlength="120" /></div></div>
        <div class="rotulo"><span>Membros</span></div>
        <p v-if="!equipeAtiva().length" class="vazio">Cadastre pessoas na equipe primeiro.</p>
        <div class="chips">
          <button
            v-for="p in equipeAtiva()" :key="p.id" class="chip" :aria-pressed="membros.includes(p.id)" @click="alternar(p.id)"
          >{{ p.nome }}</button>
        </div>
      </div>
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
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.acoes { display: flex; gap: 9px; }
.acoes .botao { flex: 1; }
.aberto { text-align: left; }
</style>
