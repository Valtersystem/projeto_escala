<script setup>
import { onMounted, ref } from 'vue';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const ministerios = ref([]);
const pessoas = ref([]);

const aberta = ref(false);
const editando = ref(null);
const nome = ref('');
const liderId = ref('');
const ocupado = ref(false);
const confirmando = ref(false);

async function carregar() {
  try {
    const [m, p] = await Promise.all([api.get('/ministerios'), api.get('/pessoas')]);
    ministerios.value = m.ministerios;
    pessoas.value = p.pessoas.filter((x) => x.ativo);
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

function abrir(ministerio) {
  editando.value = ministerio;
  nome.value = ministerio?.nome ?? '';
  liderId.value = ministerio?.lider?.id ?? '';
  confirmando.value = false;
  aberta.value = true;
}

async function executar(acao, sucesso) {
  if (ocupado.value) return false;
  ocupado.value = true;

  try {
    await acao();
    mensagens.mostrar(sucesso);
    await carregar();
    return true;
  } catch (e) {
    mensagens.erro(e);
    return false;
  } finally {
    ocupado.value = false;
  }
}

async function salvar() {
  const nomeLimpo = nome.value.trim();
  if (!nomeLimpo) return;

  const ok = await executar(async () => {
    let ministerio = editando.value;
    if (!ministerio) ministerio = (await api.post('/ministerios', { nome: nomeLimpo })).ministerio;
    else if (ministerio.nome !== nomeLimpo) await api.patch(`/ministerios/${ministerio.id}`, { nome: nomeLimpo });

    if (liderId.value && liderId.value !== ministerio.lider?.id) {
      await api.put(`/ministerios/${ministerio.id}/lider`, { pessoa_id: Number(liderId.value) });
    }
  }, 'Ministério salvo.');

  if (ok) aberta.value = false;
}

async function excluir() {
  if (!confirmando.value) {
    confirmando.value = true;
    return;
  }

  const ok = await executar(() => api.delete(`/ministerios/${editando.value.id}`), 'Ministério excluído.');
  if (ok) aberta.value = false;
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Ministérios</h2>
      <p>Quem lidera cada um. O líder cuida do resto: equipe, bandas e escalas.</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>
    <template v-else>
      <div v-if="!ministerios.length" class="cartao pad vazio"><b>Nenhum ministério</b><p>Crie o primeiro e escolha quem vai liderar.</p></div>
      <div v-else class="cartao lista">
        <button v-for="m in ministerios" :key="m.id" class="linha-pessoa" @click="abrir(m)">
          <span class="texto">
            <strong>{{ m.nome }}</strong>
            <small>{{ m.lider ? `Líder: ${m.lider.nome}` : 'Sem líder' }}</small>
          </span>
          <span v-if="!m.lider" class="pilula atencao"><i />Sem líder</span>
        </button>
      </div>
      <button class="botao suave largo" @click="abrir(null)">Novo ministério</button>
    </template>

    <Folha :aberta="aberta" :titulo="editando ? 'Editar ministério' : 'Novo ministério'" @fechar="aberta = false">
      <div class="miolo">
        <div class="cartao">
          <div class="campo"><label for="min-nome">Nome</label><input id="min-nome" v-model="nome" maxlength="120" /></div>
          <div class="campo">
            <label for="min-lider">Líder</label>
            <select id="min-lider" v-model="liderId">
              <option value="">{{ editando?.lider ? editando.lider.nome : 'Escolher depois' }}</option>
              <option v-for="p in pessoas" :key="p.id" :value="p.id">{{ p.nome }}</option>
            </select>
          </div>
        </div>
        <p v-if="confirmando" class="dica">Excluir apaga os postos, escalas e turnos do ministério. As pessoas continuam na igreja.</p>
      </div>
      <template #rodape>
        <div class="acoes">
          <button v-if="editando" class="botao perigo" :disabled="ocupado" @click="excluir">{{ confirmando ? 'Confirmar exclusão' : 'Excluir' }}</button>
          <button class="botao primario" :disabled="ocupado || !nome.trim()" @click="salvar">Salvar</button>
        </div>
      </template>
    </Folha>
  </section>
</template>

<style scoped>
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.acoes { display: flex; gap: 9px; }
.acoes .botao { flex: 1; }
.linha-pessoa { text-align: left; }
section { display: flex; flex-direction: column; gap: 12px; }
</style>
