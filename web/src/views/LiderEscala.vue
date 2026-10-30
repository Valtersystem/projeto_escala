<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import FormularioTurno from '../components/FormularioTurno.vue';
import GerarTurnos from '../components/GerarTurnos.vue';
import Icone from '../components/Icone.vue';
import TurnoFolha from '../components/TurnoFolha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { dataCurta, diaDaSemana, mesPorExtenso } from '../utils/datas.js';
import { descreverConflito } from '../utils/descricoes.js';
import { usePagina } from '../utils/listas.js';
import { iniciaisDe, statusDaEscala } from '../utils/status.js';

const props = defineProps({ escalaId: { type: String, required: true } });

const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const dados = ref(null);
const ocupado = ref(false);

const escala = computed(() => dados.value?.escala);
const turnos = computed(() => dados.value?.turnos ?? []);
const status = computed(() => statusDaEscala(escala.value?.status));

const vagas = computed(() => turnos.value.reduce((soma, t) => soma + t.vagas, 0));
const preenchidas = computed(() => turnos.value.reduce((soma, t) => soma + Math.min(t.confirmados_total, t.vagas), 0));

// Escala grande: filtro "Faltam pessoas", turnos agrupados por dia e em blocos (RN-136).
const TURNOS_SEM_FILTRO = 12;
const filtro = ref('todos'); // 'todos' | 'faltam'
const faltam = computed(() => turnos.value.filter((t) => t.confirmados_total < t.vagas));
const filtrados = computed(() => (filtro.value === 'faltam' ? faltam.value : turnos.value));
const { visiveis, restantes, mais, reiniciar } = usePagina(filtrados);
watch(filtro, reiniciar);

const porDia = computed(() => {
  const grupos = [];
  for (const turno of visiveis.value) {
    if (grupos.at(-1)?.data !== turno.data) grupos.push({ data: turno.data, turnos: [] });
    grupos.at(-1).turnos.push(turno);
  }
  return grupos;
});

// Sheets
const gerando = ref(false);
const formulario = ref({ aberta: false, turno: null });
const turnoAberto = ref(null);
const previa = ref(null); // preenchida ao abrir "Publicar"
const editandoPrazo = ref(false);
const novoPrazo = ref('');

async function carregar() {
  try {
    dados.value = await api.get(`/escalas/${props.escalaId}`);
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

async function executar(acao, sucesso) {
  if (ocupado.value) return;
  ocupado.value = true;
  try {
    await acao();
    if (sucesso) mensagens.mostrar(sucesso);
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
    await carregar();
  }
}

const liberar = () => executar(() => api.post(`/escalas/${props.escalaId}/liberar`), 'Escala liberada. A equipe já pode marcar.');
const reabrir = () => executar(() => api.post(`/escalas/${props.escalaId}/reabrir`), 'Escala reaberta para ajustes.');

async function abrirPublicacao() {
  try {
    previa.value = await api.get(`/escalas/${props.escalaId}/publicacao`);
  } catch (e) {
    mensagens.erro(e);
  }
}

async function publicar() {
  previa.value = null;
  await executar(() => api.post(`/escalas/${props.escalaId}/publicar`), 'Escala publicada. Cada pessoa foi avisada.');
}

function abrirPrazo() {
  novoPrazo.value = escala.value.prazo ?? '';
  editandoPrazo.value = true;
}

async function salvarPrazo() {
  editandoPrazo.value = false;
  await executar(() => api.patch(`/escalas/${props.escalaId}`, { prazo: novoPrazo.value, versao: escala.value.versao }), 'Prazo atualizado.');
}

function tocar(turno) {
  if (escala.value.status === 'rascunho') formulario.value = { aberta: true, turno };
  else turnoAberto.value = turno.id;
}

const aoSalvarTurno = async () => {
  formulario.value = { aberta: false, turno: null };
  await carregar();
};

const aoGerar = async () => {
  gerando.value = false;
  await carregar();
};

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <RouterLink class="voltar" :to="{ name: 'lider-escalas' }"><Icone nome="voltar" :tamanho="16" />Escalas</RouterLink>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para abrir esta escala">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <template v-else>
      <div class="saudacao">
        <h2>{{ mesPorExtenso(escala.mes) }}</h2>
        <p>{{ status.dica }}</p>
      </div>

      <div class="cartao pad resumo">
        <div class="linha">
          <span class="pilula" :class="status.tipo"><i />{{ status.texto }}</span>
          <span class="contagem">{{ turnos.length }} turno(s) · {{ vagas }} vaga(s)</span>
        </div>
        <div v-if="vagas" class="progresso"><i :style="{ width: `${(preenchidas / vagas) * 100}%` }" /></div>
        <div v-if="vagas && escala.status !== 'rascunho'" class="contagem">{{ preenchidas }} de {{ vagas }} vagas preenchidas</div>
        <div v-if="escala.prazo && escala.status !== 'publicada'" class="linha prazo">
          <span>Responder até {{ dataCurta(escala.prazo) }}</span>
          <button class="link" @click="abrirPrazo">Mudar</button>
        </div>
      </div>

      <div v-if="escala.status !== 'publicada'" class="botoes">
        <button class="botao suave" @click="gerando = true">Gerar dos cultos</button>
        <button class="botao contorno" @click="formulario = { aberta: true, turno: null }">Turno avulso</button>
      </div>

      <div v-if="!turnos.length" class="cartao pad vazio">
        <b>Nenhum turno ainda</b>
        <p>Gere os turnos a partir da grade de cultos, ou crie um turno avulso.</p>
      </div>

      <template v-else>
        <div v-if="escala.status !== 'rascunho' && turnos.length > TURNOS_SEM_FILTRO" class="segmentos" role="tablist">
          <button role="tab" :aria-selected="filtro === 'todos'" @click="filtro = 'todos'">Todos ({{ turnos.length }})</button>
          <button role="tab" :aria-selected="filtro === 'faltam'" @click="filtro = 'faltam'">Faltam pessoas ({{ faltam.length }})</button>
        </div>

        <div v-if="!filtrados.length" class="cartao pad vazio"><b>Nenhum turno com vaga em aberto</b><p>Todas as vagas têm alguém confirmado.</p></div>

        <template v-for="grupo in porDia" :key="grupo.data">
          <div class="rotulo dia-titulo"><span>{{ diaDaSemana(grupo.data) }}, {{ dataCurta(grupo.data) }}</span></div>
          <div class="cartao lista">
            <button v-for="turno in grupo.turnos" :key="turno.id" class="turno" @click="tocar(turno)">
              <div class="principal">
                <strong>{{ turno.culto || turno.posto?.nome }}</strong>
                <small>{{ turno.inicio }} às {{ turno.fim }} · {{ turno.posto?.nome }}<template v-if="turno.banda"> · {{ turno.banda.nome }}</template></small>
                <small v-if="escala.status === 'rascunho'">{{ turno.vagas }} vaga(s)</small>
                <small v-else>{{ turno.marcaram_total }} marcaram · {{ turno.confirmados_total }} de {{ turno.vagas }} confirmados</small>
              </div>
              <div v-if="turno.confirmados.length" class="pilha">
                <span v-for="c in turno.confirmados.slice(0, 3)" :key="c.pessoa_id" class="inicial pequena" :class="{ mudo: c.sem_funcao }" :title="c.nome">{{ iniciaisDe(c.nome) }}</span>
              </div>
              <span v-if="escala.status !== 'rascunho' && !turno.marcaram_total && !turno.confirmados_total" class="pilula critico"><i />Vazio</span>
            </button>
          </div>
        </template>

        <button v-if="restantes" class="botao contorno largo" @click="mais">Mostrar mais ({{ restantes }} restantes)</button>
      </template>

      <div class="acao-principal">
        <button v-if="escala.status === 'rascunho'" class="botao primario largo" :disabled="ocupado || !turnos.length" @click="liberar">Liberar para a equipe</button>
        <button v-else-if="escala.status === 'aberta'" class="botao primario largo" :disabled="ocupado" @click="abrirPublicacao">Publicar escala</button>
        <button v-else class="botao contorno largo" :disabled="ocupado" @click="reabrir">Reabrir para ajustes</button>
      </div>
    </template>

    <GerarTurnos v-if="escala" :aberta="gerando" :escala="escala" @fechar="gerando = false" @gerado="aoGerar" />
    <FormularioTurno v-if="escala" :aberta="formulario.aberta" :escala="escala" :turno="formulario.turno" @fechar="formulario = { aberta: false, turno: null }" @salvo="aoSalvarTurno" />
    <TurnoFolha v-if="escala" :turno-id="turnoAberto" :escala="escala" @fechar="turnoAberto = null" @mudou="carregar" />

    <Folha :aberta="!!previa" titulo="Publicar escala" :legenda="previa ? mesPorExtenso(escala.mes) : ''" @fechar="previa = null">
      <div v-if="previa" class="previa">
        <Alerta v-if="previa.sem_funcao" tipo="aviso" titulo="Pessoas sem função">
          {{ previa.sem_funcao }} pessoa(s) vão receber "função a combinar". Dá para definir depois, mas o aviso já sai assim.
        </Alerta>
        <Alerta v-if="previa.choques.length" tipo="critico" titulo="Choque de horário">
          <div v-for="(c, i) in previa.choques.slice(0, 3)" :key="i">{{ c.pessoa.nome }}: {{ descreverConflito(c.turno) }} e {{ descreverConflito(c.com) }}</div>
          <div v-if="previa.choques.length > 3">e mais {{ previa.choques.length - 3 }}…</div>
        </Alerta>
        <Alerta v-if="previa.incompletos" tipo="aviso" titulo="Publicar assim mesmo?">
          {{ previa.incompletos }} turno(s) ainda sem todas as vagas preenchidas.
        </Alerta>
        <Alerta v-if="!previa.sem_funcao && !previa.choques.length && !previa.incompletos" tipo="ok" icone="certo" titulo="Tudo certo">
          Todos os turnos completos, com função e sem choque.
        </Alerta>
        <p class="nota">Cada pessoa passa a ver só os turnos dela em "Minha escala" e recebe um aviso.</p>
      </div>
      <template #rodape>
        <div class="duas">
          <button class="botao contorno" @click="previa = null">Voltar</button>
          <button class="botao primario" :disabled="ocupado" @click="publicar">Publicar</button>
        </div>
      </template>
    </Folha>

    <Folha :aberta="editandoPrazo" titulo="Prazo para responder" legenda="Depois dele, ninguém mais marca disponibilidade." @fechar="editandoPrazo = false">
      <div class="previa"><div class="formulario"><div class="campo"><label for="novo-prazo">Responder até</label><input id="novo-prazo" v-model="novoPrazo" type="date" /></div></div></div>
      <template #rodape><button class="botao primario largo" :disabled="!novoPrazo" @click="salvarPrazo">Salvar prazo</button></template>
    </Folha>
  </section>
</template>

<style scoped>
.voltar { display: inline-flex; align-items: center; gap: 6px; color: var(--brand); font-size: 13.5px; font-weight: 600; margin: 0 4px; text-decoration: none; }
.resumo { display: flex; flex-direction: column; gap: 10px; }
.linha { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.contagem { font-size: 12.5px; color: var(--ink-3); }
.prazo { font-size: 13px; color: var(--ink-2); }
.link { color: var(--brand); font-weight: 600; font-size: 13px; }
.botoes { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
.turno { display: flex; align-items: center; gap: 12px; padding: 12px 14px; width: 100%; }
.principal { flex: 1; min-width: 0; }
.principal strong { display: block; font-size: 14.5px; font-weight: 600; }
.principal small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 1px; }
.pilha { display: flex; gap: 3px; flex: none; }
.acao-principal { margin-top: 4px; }
.previa { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; font-size: 13px; }
.nota { color: var(--ink-2); font-size: 13.5px; }
.duas { display: flex; gap: 9px; }
.duas .primario { flex: 1; }
</style>
