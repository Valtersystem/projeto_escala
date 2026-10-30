<script setup>
// A equipe do ministério: cadastrar, incluir quem já está na igreja, postos e funções de cada um e o convite de acesso.
import { computed, ref, watch } from 'vue';
import ConviteDaPessoa from './ConviteDaPessoa.vue';
import Folha from './Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { resumirNomes, usePagina } from '../utils/listas.js';
import { iniciaisDe } from '../utils/status.js';

const props = defineProps({
  ministerio: { type: Object, required: true },
  pessoas: { type: Array, required: true },
  postos: { type: Array, required: true },
  funcoes: { type: Array, required: true },
});
const emit = defineEmits(['mudou']);

const mensagens = useMensagens();
const ocupado = ref(false);

// Equipe grande ganha busca; com poucas pessoas ela só atrapalharia.
const LIMITE_SEM_BUSCA = 8;
const busca = ref('');
const filtradas = computed(() => {
  const termo = busca.value.trim().toLowerCase();
  return termo ? props.pessoas.filter((p) => p.nome.toLowerCase().includes(termo)) : props.pessoas;
});

const { visiveis, restantes, mais, reiniciar } = usePagina(filtradas);
watch(busca, reiniciar);

// --- uma pessoa ---
const selecionadaId = ref(null);
const selecionada = computed(() => props.pessoas.find((p) => p.id === selecionadaId.value) ?? null);
const dados = ref({ nome: '', telefone: '', email: '' });
const confirmandoSaida = ref(false);

function abrir(pessoa) {
  selecionadaId.value = pessoa.id;
  dados.value = { nome: pessoa.nome, telefone: pessoa.telefone ?? '', email: pessoa.email ?? '' };
  confirmandoSaida.value = false;
}

async function executar(acao, sucesso) {
  if (ocupado.value) return false;
  ocupado.value = true;

  try {
    const resultado = await acao();
    if (sucesso) mensagens.mostrar(sucesso);
    emit('mudou');
    return resultado ?? true;
  } catch (e) {
    mensagens.erro(e);
    return false;
  } finally {
    ocupado.value = false;
  }
}

const lista = (ids, todos) => resumirNomes(todos.filter((t) => ids.includes(t.id)).map((t) => t.nome));

function alternarPosto(posto) {
  const atuais = selecionada.value.posto_ids;
  const novos = atuais.includes(posto.id) ? atuais.filter((i) => i !== posto.id) : [...atuais, posto.id];
  executar(() => api.put(`/ministerios/${props.ministerio.id}/equipe/${selecionada.value.id}/postos`, { posto_ids: novos }));
}

function alternarFuncao(funcao) {
  const atuais = selecionada.value.funcao_ids;
  const novos = atuais.includes(funcao.id) ? atuais.filter((i) => i !== funcao.id) : [...atuais, funcao.id];
  executar(() => api.put(`/ministerios/${props.ministerio.id}/equipe/${selecionada.value.id}/funcoes`, { funcao_ids: novos }));
}

const alternarAtivo = () => executar(
  () => api.patch(`/pessoas/${selecionada.value.id}`, { ativo: !selecionada.value.ativo }),
  selecionada.value.ativo ? 'Pessoa desativada: não entra em escalas novas.' : 'Pessoa reativada.',
);

function salvarDados() {
  const corpo = { nome: dados.value.nome.trim(), telefone: dados.value.telefone.trim() || null };
  if (!selecionada.value.tem_login) corpo.email = dados.value.email.trim() || null;
  return executar(() => api.patch(`/pessoas/${selecionada.value.id}`, corpo), 'Dados salvos.');
}

async function sairDaEquipe() {
  if (!confirmandoSaida.value) {
    confirmandoSaida.value = true;
    return;
  }

  const ok = await executar(() => api.delete(`/ministerios/${props.ministerio.id}/equipe/${selecionada.value.id}`), 'Saiu da equipe.');
  if (ok) selecionadaId.value = null;
}

// --- incluir ---
const incluindo = ref(false);
const modoInclusao = ref('novo'); // 'novo' | 'existente'
const novo = ref({ nome: '', telefone: '', email: '' });
const deFora = ref([]);
const carregandoDeFora = ref(false);

async function abrirInclusao() {
  incluindo.value = true;
  novo.value = { nome: '', telefone: '', email: '' };
  modoInclusao.value = 'novo';
  carregandoDeFora.value = true;

  try {
    deFora.value = (await api.get(`/ministerios/${props.ministerio.id}/pessoas-de-fora`)).pessoas;
  } catch (e) {
    mensagens.erro(e);
  } finally {
    carregandoDeFora.value = false;
  }
}

async function cadastrar() {
  if (!novo.value.nome.trim()) return;

  const ok = await executar(() => api.post('/pessoas', {
    nome: novo.value.nome.trim(),
    telefone: novo.value.telefone.trim() || null,
    email: novo.value.email.trim() || null,
    ministerio_ids: [props.ministerio.id],
  }), 'Pessoa cadastrada.');

  if (ok) incluindo.value = false;
}

async function incluir(pessoa) {
  const ok = await executar(() => api.post(`/ministerios/${props.ministerio.id}/equipe`, { pessoa_id: pessoa.id }), `${pessoa.nome.split(' ')[0]} entrou na equipe.`);
  if (ok) incluindo.value = false;
}
</script>

<template>
  <div class="bloco">
    <div v-if="!pessoas.length" class="cartao pad vazio"><b>Equipe vazia</b><p>Cadastre a primeira pessoa e mande o convite para ela entrar.</p></div>
    <div v-if="pessoas.length > LIMITE_SEM_BUSCA" class="cartao"><div class="campo"><label for="busca-equipe">Buscar</label><input id="busca-equipe" v-model="busca" placeholder="Nome" /></div></div>
    <p v-if="pessoas.length > LIMITE_SEM_BUSCA" class="contagem">{{ busca.trim() ? `${filtradas.length} de ${pessoas.length}` : pessoas.length }} pessoa(s)</p>
    <div v-if="pessoas.length && !filtradas.length" class="cartao pad vazio"><b>Ninguém com esse nome</b></div>
    <div v-else-if="pessoas.length" class="cartao lista">
      <button v-for="p in visiveis" :key="p.id" class="linha-pessoa" @click="abrir(p)">
        <span class="inicial" :class="{ mudo: !p.ativo }">{{ iniciaisDe(p.nome) }}</span>
        <span class="texto">
          <strong>{{ p.nome }}</strong>
          <small>{{ lista(p.posto_ids, postos) || 'Sem posto' }}</small>
        </span>
        <span v-if="!p.ativo" class="pilula mudo">Inativa</span>
        <span v-else-if="!p.tem_login" class="pilula atencao"><i />Sem acesso</span>
      </button>
    </div>
    <button v-if="restantes" class="botao contorno largo" @click="mais">Mostrar mais ({{ restantes }} restantes)</button>
    <button class="botao suave largo" @click="abrirInclusao">Adicionar pessoa</button>

    <!-- adicionar -->
    <Folha :aberta="incluindo" titulo="Adicionar pessoa" @fechar="incluindo = false">
      <div class="miolo">
        <div class="segmentos" role="tablist">
          <button role="tab" :aria-selected="modoInclusao === 'novo'" @click="modoInclusao = 'novo'">Cadastrar nova</button>
          <button role="tab" :aria-selected="modoInclusao === 'existente'" @click="modoInclusao = 'existente'">Já está na igreja</button>
        </div>

        <form v-if="modoInclusao === 'novo'" class="cartao" @submit.prevent="cadastrar">
          <div class="campo"><label for="np-nome">Nome</label><input id="np-nome" v-model="novo.nome" maxlength="150" /></div>
          <div class="campo"><label for="np-tel">WhatsApp (opcional)</label><input id="np-tel" v-model="novo.telefone" inputmode="tel" maxlength="32" /></div>
          <div class="campo"><label for="np-email">E-mail (é o login)</label><input id="np-email" v-model="novo.email" type="email" /></div>
        </form>

        <template v-else>
          <p v-if="carregandoDeFora" class="vazio">Carregando…</p>
          <div v-else-if="!deFora.length" class="vazio"><b>Ninguém de fora</b>Todos da igreja já estão nesta equipe.</div>
          <div v-else class="cartao lista">
            <div v-for="p in deFora" :key="p.id" class="linha-pessoa">
              <span class="texto"><strong>{{ p.nome }}</strong><small>{{ p.ministerios.join(' · ') || 'Sem ministério' }}</small></span>
              <button class="botao suave pequeno" :disabled="ocupado" @click="incluir(p)">Incluir</button>
            </div>
          </div>
        </template>
      </div>
      <template v-if="modoInclusao === 'novo'" #rodape>
        <button class="botao primario largo" :disabled="ocupado || !novo.nome.trim()" @click="cadastrar">Cadastrar</button>
      </template>
    </Folha>

    <!-- uma pessoa -->
    <Folha :aberta="!!selecionada" :titulo="selecionada?.nome ?? ''" :legenda="selecionada ? (selecionada.tem_login ? 'Já tem acesso ao app' : 'Ainda não entrou no app') : ''" @fechar="selecionadaId = null">
      <div v-if="selecionada" class="miolo">
        <div class="rotulo"><span>Postos que atende</span></div>
        <p v-if="!postos.length" class="vazio">Crie postos na aba Postos.</p>
        <div class="chips">
          <button v-for="po in postos" :key="po.id" class="chip" :aria-pressed="selecionada.posto_ids.includes(po.id)" :disabled="ocupado" @click="alternarPosto(po)">{{ po.nome }}</button>
        </div>

        <template v-if="funcoes.length">
          <div class="rotulo"><span>Funções que sabe fazer</span></div>
          <div class="chips">
            <button v-for="f in funcoes" :key="f.id" class="chip" :aria-pressed="selecionada.funcao_ids.includes(f.id)" :disabled="ocupado" @click="alternarFuncao(f)">{{ f.nome }}</button>
          </div>
        </template>

        <div class="rotulo"><span>Acesso ao app</span></div>
        <ConviteDaPessoa :pessoa="selecionada" />

        <div class="rotulo"><span>Dados</span></div>
        <form class="cartao" @submit.prevent="salvarDados">
          <div class="campo"><label for="p-nome">Nome</label><input id="p-nome" v-model="dados.nome" maxlength="150" /></div>
          <div class="campo"><label for="p-tel">WhatsApp</label><input id="p-tel" v-model="dados.telefone" inputmode="tel" maxlength="32" /></div>
          <div class="campo"><label for="p-email">E-mail</label><input id="p-email" v-model="dados.email" type="email" :disabled="selecionada.tem_login" /></div>
        </form>
        <button class="botao contorno largo" :disabled="ocupado || !dados.nome.trim()" @click="salvarDados">Salvar dados</button>

        <button class="interruptor cartao" :aria-pressed="selecionada.ativo" :disabled="ocupado" @click="alternarAtivo">
          <span>Ativa na escala<br><span class="dica">Desativada, não entra em escalas novas</span></span>
          <span class="trilho-interruptor" />
        </button>

        <button class="botao perigo largo" :disabled="ocupado" @click="sairDaEquipe">{{ confirmandoSaida ? 'Confirmar: tirar da equipe' : 'Tirar da equipe' }}</button>
      </div>
    </Folha>
  </div>
</template>

<style scoped>
.bloco { display: flex; flex-direction: column; gap: 12px; }
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.rotulo { margin: 6px 4px -4px; }
.linha-pessoa { text-align: left; }
</style>
