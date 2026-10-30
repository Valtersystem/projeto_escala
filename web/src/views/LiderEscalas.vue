<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { useModo } from '../stores/modo.js';
import { dataCurta, hojeIso, mesDe, mesPorExtenso } from '../utils/datas.js';
import { usePagina } from '../utils/listas.js';
import { proximosMeses, statusDaEscala } from '../utils/status.js';

const modo = useModo();
const mensagens = useMensagens();
const roteador = useRouter();

const carregando = ref(true);
const erro = ref('');
const escalas = ref([]);

const abrindo = ref(false);
const mes = ref('');
const prazo = ref('');
const enviando = ref(false);

// Em dia: do mês atual em diante e tudo que ainda não foi publicado. O resto é histórico, atrás de um botão.
const mesAtual = mesDe(hojeIso());
const emDia = computed(() => escalas.value.filter((e) => e.mes >= mesAtual || e.status !== 'publicada'));
const anteriores = computed(() => escalas.value.filter((e) => e.mes < mesAtual && e.status === 'publicada'));
const verAnteriores = ref(false);
const { visiveis: anterioresVisiveis, restantes, mais } = usePagina(anteriores, 12);

// Uma lista só: as escalas em dia e, abertas as anteriores, um título por ano antes de cada grupo.
const linhas = computed(() => {
  const saida = emDia.value.map((escala) => ({ chave: `e${escala.id}`, escala }));
  if (!verAnteriores.value) return saida;

  let ano = null;
  for (const escala of anterioresVisiveis.value) {
    if (escala.mes.slice(0, 4) !== ano) {
      ano = escala.mes.slice(0, 4);
      saida.push({ chave: `a${ano}`, ano });
    }
    saida.push({ chave: `e${escala.id}`, escala });
  }
  return saida;
});

const mesesLivres = computed(() => proximosMeses().filter((m) => !escalas.value.some((e) => e.mes === m)));

async function carregar() {
  if (!modo.ministerio) return;

  carregando.value = true;
  erro.value = '';

  try {
    escalas.value = (await api.get(`/ministerios/${modo.ministerio.id}/escalas`)).escalas;
    await modo.atualizarTrocas();
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

function abrirFolha() {
  mes.value = mesesLivres.value[0] ?? '';
  prazo.value = '';
  abrindo.value = true;
}

async function criar() {
  enviando.value = true;
  try {
    const { escala } = await api.post(`/ministerios/${modo.ministerio.id}/escalas`, { mes: mes.value, prazo: prazo.value || null });
    abrindo.value = false;
    mensagens.mostrar('Escala criada. Agora adicione os turnos.');
    await roteador.push({ name: 'lider-escala', params: { escalaId: escala.id } });
  } catch (e) {
    mensagens.erro(e);
  } finally {
    enviando.value = false;
  }
}

watch(() => modo.ministerio?.id, carregar);
onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Escalas</h2>
      <p>{{ modo.ministerio?.nome }}</p>
    </div>

    <div v-if="modo.ministeriosDoLider.length > 1" class="chips" role="group" aria-label="Ministério">
      <button
        v-for="m in modo.ministeriosDoLider" :key="m.id" class="chip" :aria-pressed="m.id === modo.ministerio?.id"
        @click="modo.escolherMinisterio(m.id)"
      >{{ m.nome }}</button>
    </div>

    <RouterLink v-if="modo.trocasAbertas" class="cartao pad trocas" :to="{ name: 'lider-trocas' }">
      <b>{{ modo.trocasAbertas }} pedido(s) de substituição</b>
      <span>esperando você resolver</span>
    </RouterLink>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <template v-else>
      <div v-if="!escalas.length" class="cartao pad vazio">
        <b>Nenhuma escala ainda</b>
        <p>Abra a escala de um mês, monte os turnos e libere para a equipe marcar a disponibilidade.</p>
      </div>

      <template v-for="linha in linhas" :key="linha.chave">
        <div v-if="linha.ano" class="rotulo"><span>{{ linha.ano }}</span></div>
        <RouterLink v-else class="cartao pad escala" :to="{ name: 'lider-escala', params: { escalaId: linha.escala.id } }">
          <div class="linha">
            <strong>{{ mesPorExtenso(linha.escala.mes) }}</strong>
            <span class="pilula" :class="statusDaEscala(linha.escala.status).tipo"><i />{{ statusDaEscala(linha.escala.status).texto }}</span>
          </div>
          <p>{{ linha.escala.total_de_turnos }} turno(s)<template v-if="linha.escala.prazo && linha.escala.status === 'aberta'"> · responder até {{ dataCurta(linha.escala.prazo) }}</template></p>
        </RouterLink>
      </template>

      <button v-if="verAnteriores && restantes" class="botao contorno largo" @click="mais">Mostrar mais ({{ restantes }} restantes)</button>
      <button v-if="anteriores.length" class="botao suave largo" @click="verAnteriores = !verAnteriores">
        {{ verAnteriores ? 'Esconder escalas anteriores' : `Ver escalas anteriores (${anteriores.length})` }}
      </button>

      <button class="botao suave largo" :disabled="!mesesLivres.length" @click="abrirFolha">Abrir escala de um mês</button>
    </template>

    <Folha :aberta="abrindo" titulo="Abrir escala do mês" legenda="Você monta os turnos primeiro; a equipe só vê quando você liberar." @fechar="abrindo = false">
      <div class="formulario-folha">
        <div class="formulario">
          <div class="campo">
            <label for="mes">Mês da escala</label>
            <select id="mes" v-model="mes"><option v-for="m in mesesLivres" :key="m" :value="m">{{ mesPorExtenso(m) }}</option></select>
          </div>
          <div class="campo">
            <label for="prazo">Prazo para responder (opcional)</label>
            <input id="prazo" v-model="prazo" type="date" />
          </div>
        </div>
        <p class="dica">Sem prazo, o app sugere o início do mês menos os dias padrão da igreja.</p>
      </div>
      <template #rodape>
        <button class="botao primario largo" :disabled="enviando || !mes" @click="criar">{{ enviando ? 'Criando…' : 'Criar escala' }}</button>
      </template>
    </Folha>
  </section>
</template>

<style scoped>
.escala, .trocas { display: block; text-decoration: none; color: inherit; }
.escala p { margin-top: 6px; color: var(--ink-3); font-size: 12.5px; }
.linha { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.trocas { background: var(--crit-soft); border-color: var(--crit-line); color: var(--crit); }
.trocas span { display: block; font-size: 12.5px; margin-top: 2px; }
.formulario-folha { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.dica { font-size: 12.5px; color: var(--ink-3); }
</style>
