<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Alerta from '../components/Alerta.vue';
import ConviteDaPessoa from '../components/ConviteDaPessoa.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { useSessao } from '../stores/sessao.js';
import { resumirNomes, usePagina } from '../utils/listas.js';
import { iniciaisDe } from '../utils/status.js';

const mensagens = useMensagens();
const sessao = useSessao();

const carregando = ref(true);
const erro = ref('');
const pessoas = ref([]);
const ministerios = ref([]);
const busca = ref('');
const ocupado = ref(false);

const filtradas = computed(() => {
  const termo = busca.value.trim().toLowerCase();
  if (!termo) return pessoas.value;

  return pessoas.value.filter((p) => p.nome.toLowerCase().includes(termo) || p.ministerios.some((m) => m.nome.toLowerCase().includes(termo)));
});

const { visiveis, restantes, mais, reiniciar } = usePagina(filtradas);
watch(busca, reiniciar);

async function carregar() {
  try {
    const [p, m] = await Promise.all([api.get('/pessoas'), api.get('/ministerios')]);
    pessoas.value = p.pessoas;
    ministerios.value = m.ministerios;
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

async function executar(acao, sucesso) {
  if (ocupado.value) return false;
  ocupado.value = true;

  try {
    await acao();
    if (sucesso) mensagens.mostrar(sucesso);
    await carregar();
    return true;
  } catch (e) {
    mensagens.erro(e);
    return false;
  } finally {
    ocupado.value = false;
  }
}

// --- uma pessoa ---
const selecionadaId = ref(null);
const selecionada = computed(() => pessoas.value.find((p) => p.id === selecionadaId.value) ?? null);
const dados = ref({ nome: '', telefone: '', email: '' });
const confirmandoExclusao = ref(false);

function abrir(pessoa) {
  selecionadaId.value = pessoa.id;
  dados.value = { nome: pessoa.nome, telefone: pessoa.telefone ?? '', email: pessoa.email ?? '' };
  confirmandoExclusao.value = false;
}

const estaEm = (ministerio) => selecionada.value.ministerios.some((m) => m.id === ministerio.id);

const alternarMinisterio = (ministerio) => executar(() => (estaEm(ministerio)
  ? api.delete(`/ministerios/${ministerio.id}/equipe/${selecionada.value.id}`)
  : api.post(`/ministerios/${ministerio.id}/equipe`, { pessoa_id: selecionada.value.id })));

const alternarAtivo = () => executar(() => api.patch(`/pessoas/${selecionada.value.id}`, { ativo: !selecionada.value.ativo }));
const alternarAdm = () => executar(
  () => api.patch(`/pessoas/${selecionada.value.id}`, { adm: !selecionada.value.adm }),
  selecionada.value.adm ? 'Não é mais Adm.' : 'Agora é Adm.',
);

function salvarDados() {
  const corpo = { nome: dados.value.nome.trim(), telefone: dados.value.telefone.trim() || null };
  if (!selecionada.value.tem_login) corpo.email = dados.value.email.trim() || null;
  return executar(() => api.patch(`/pessoas/${selecionada.value.id}`, corpo), 'Dados salvos.');
}

async function excluir() {
  if (!confirmandoExclusao.value) {
    confirmandoExclusao.value = true;
    return;
  }

  const ok = await executar(() => api.delete(`/pessoas/${selecionada.value.id}`), 'Pessoa excluída.');
  if (ok) selecionadaId.value = null;
}

// --- nova pessoa ---
const criando = ref(false);
const nova = ref({ nome: '', telefone: '', email: '', adm: false, ministerio_ids: [] });

function abrirNova() {
  nova.value = { nome: '', telefone: '', email: '', adm: false, ministerio_ids: [] };
  criando.value = true;
}

function alternarMinisterioNova(id) {
  const ids = nova.value.ministerio_ids;
  nova.value.ministerio_ids = ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id];
}

async function cadastrar() {
  if (!nova.value.nome.trim()) return;

  const ok = await executar(() => api.post('/pessoas', {
    nome: nova.value.nome.trim(),
    telefone: nova.value.telefone.trim() || null,
    email: nova.value.email.trim() || null,
    adm: nova.value.adm,
    ministerio_ids: nova.value.ministerio_ids,
  }), 'Pessoa cadastrada.');

  if (ok) criando.value = false;
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Pessoas</h2>
      <p>Todos da igreja, em qualquer ministério.</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>
    <template v-else>
      <div class="cartao"><div class="campo"><label for="busca">Buscar</label><input id="busca" v-model="busca" placeholder="Nome ou ministério" /></div></div>
      <p class="contagem">{{ busca.trim() ? `${filtradas.length} de ${pessoas.length}` : pessoas.length }} pessoa(s)</p>

      <div v-if="!filtradas.length" class="cartao pad vazio"><b>{{ busca ? 'Ninguém com esse nome' : 'Ninguém cadastrado' }}</b></div>
      <div v-else class="cartao lista">
        <button v-for="p in visiveis" :key="p.id" class="linha-pessoa" @click="abrir(p)">
          <span class="inicial" :class="{ mudo: !p.ativo }">{{ iniciaisDe(p.nome) }}</span>
          <span class="texto">
            <strong>{{ p.nome }}</strong>
            <small>{{ resumirNomes(p.ministerios.map((m) => m.nome), 2, 'Sem ministério') }}</small>
          </span>
          <span v-if="p.adm" class="pilula marca">Adm</span>
          <span v-else-if="!p.ativo" class="pilula mudo">Inativa</span>
          <span v-else-if="!p.tem_login" class="pilula atencao"><i />Sem acesso</span>
        </button>
      </div>
      <button v-if="restantes" class="botao contorno largo" @click="mais">Mostrar mais ({{ restantes }} restantes)</button>
      <button class="botao suave largo" @click="abrirNova">Nova pessoa</button>
    </template>

    <!-- nova -->
    <Folha :aberta="criando" titulo="Nova pessoa" @fechar="criando = false">
      <div class="miolo">
        <div class="cartao">
          <div class="campo"><label for="n-nome">Nome</label><input id="n-nome" v-model="nova.nome" maxlength="150" /></div>
          <div class="campo"><label for="n-tel">WhatsApp (opcional)</label><input id="n-tel" v-model="nova.telefone" inputmode="tel" maxlength="32" /></div>
          <div class="campo"><label for="n-email">E-mail (é o login)</label><input id="n-email" v-model="nova.email" type="email" /></div>
        </div>
        <div class="rotulo"><span>Ministérios</span></div>
        <div class="chips">
          <button v-for="m in ministerios" :key="m.id" class="chip" :aria-pressed="nova.ministerio_ids.includes(m.id)" @click="alternarMinisterioNova(m.id)">{{ m.nome }}</button>
        </div>
        <button class="interruptor cartao" :aria-pressed="nova.adm" @click="nova.adm = !nova.adm">
          <span>Administrador da igreja</span><span class="trilho-interruptor" />
        </button>
      </div>
      <template #rodape><button class="botao primario largo" :disabled="ocupado || !nova.nome.trim()" @click="cadastrar">Cadastrar</button></template>
    </Folha>

    <!-- uma pessoa -->
    <Folha :aberta="!!selecionada" :titulo="selecionada?.nome ?? ''" :legenda="selecionada ? (selecionada.tem_login ? 'Já tem acesso ao app' : 'Ainda não entrou no app') : ''" @fechar="selecionadaId = null">
      <div v-if="selecionada" class="miolo">
        <div class="rotulo"><span>Ministérios onde serve</span></div>
        <div class="chips">
          <button v-for="m in ministerios" :key="m.id" class="chip" :aria-pressed="estaEm(m)" :disabled="ocupado" @click="alternarMinisterio(m)">{{ m.nome }}</button>
        </div>

        <div class="rotulo"><span>Acesso ao app</span></div>
        <ConviteDaPessoa :pessoa="selecionada" />

        <div class="rotulo"><span>Dados</span></div>
        <form class="cartao" @submit.prevent="salvarDados">
          <div class="campo"><label for="a-nome">Nome</label><input id="a-nome" v-model="dados.nome" maxlength="150" /></div>
          <div class="campo"><label for="a-tel">WhatsApp</label><input id="a-tel" v-model="dados.telefone" inputmode="tel" maxlength="32" /></div>
          <div class="campo"><label for="a-email">E-mail</label><input id="a-email" v-model="dados.email" type="email" :disabled="selecionada.tem_login" /></div>
        </form>
        <button class="botao contorno largo" :disabled="ocupado || !dados.nome.trim()" @click="salvarDados">Salvar dados</button>

        <button class="interruptor cartao" data-teste="ativo" :aria-pressed="selecionada.ativo" :disabled="ocupado" @click="alternarAtivo">
          <span>Ativa na escala<br><span class="dica">Desativada, não entra em escalas novas</span></span><span class="trilho-interruptor" />
        </button>
        <button class="interruptor cartao" data-teste="adm" :aria-pressed="selecionada.adm" :disabled="ocupado || selecionada.id === sessao.pessoa?.id" @click="alternarAdm">
          <span>Administrador da igreja<br><span v-if="selecionada.id === sessao.pessoa?.id" class="dica">Você não pode tirar o seu próprio acesso</span></span><span class="trilho-interruptor" />
        </button>

        <button v-if="selecionada.id !== sessao.pessoa?.id" class="botao perigo largo" :disabled="ocupado" @click="excluir">
          {{ confirmandoExclusao ? 'Confirmar: excluir pessoa e login' : 'Excluir pessoa' }}
        </button>
      </div>
    </Folha>
  </section>
</template>

<style scoped>
section { display: flex; flex-direction: column; gap: 12px; }
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.rotulo { margin: 6px 4px -4px; }
.linha-pessoa { text-align: left; }
</style>
