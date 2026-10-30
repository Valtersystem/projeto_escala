<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import Alerta from '../components/Alerta.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { useSessao } from '../stores/sessao.js';
import { dataCurta, diaDaSemana, diaDoMes, hojeIso, mesPorExtenso } from '../utils/datas.js';
import { detalheDoTurno, tituloDoTurno } from '../utils/descricoes.js';

const sessao = useSessao();
const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const turnos = ref([]);
const avisos = ref({ nao_lidos: 0, avisos: [] });
const abertas = ref([]);

const proximos = computed(() => turnos.value.filter((t) => t.data >= hojeIso()).slice(0, 3));

async function carregar() {
  erro.value = '';
  try {
    const [minha, painel, escalas] = await Promise.all([
      api.get('/minha-escala'),
      api.get('/avisos'),
      api.get('/escalas/abertas'),
    ]);
    turnos.value = minha.turnos;
    avisos.value = painel;
    abertas.value = escalas.escalas;
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

async function marcarLidos() {
  try {
    await api.post('/avisos/lidos');
    avisos.value = { ...avisos.value, nao_lidos: 0, avisos: avisos.value.avisos.map((a) => ({ ...a, lido: true })) };
  } catch (e) {
    mensagens.erro(e);
  }
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Olá, {{ sessao.nomeCurto }}</h2>
      <p>{{ sessao.ministerios.map((m) => m.nome).join(' · ') || 'Você ainda não está em nenhum ministério.' }}</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>

    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <template v-else>
      <div class="rotulo"><span>Para responder</span></div>
      <div v-if="!abertas.length" class="cartao pad vazio">
        <b>Nada para responder agora</b>
        <p>Quando o líder liberar uma escala, ela aparece aqui para você marcar os dias em que pode servir.</p>
      </div>
      <RouterLink
        v-for="escala in abertas" :key="escala.id" class="cartao pad escala"
        :to="{ name: 'disponibilidade-escala', params: { escalaId: escala.id } }"
      >
        <div class="linha">
          <strong>{{ escala.ministerio.nome }} · {{ mesPorExtenso(escala.mes) }}</strong>
          <span v-if="escala.prazo_encerrado" class="pilula critico"><i />Prazo encerrado</span>
          <span v-else-if="escala.marquei" class="pilula ok"><i />{{ escala.marquei }} marcado(s)</span>
          <span v-else class="pilula atencao"><i />Falta responder</span>
        </div>
        <p v-if="escala.prazo">Responder até {{ dataCurta(escala.prazo) }}</p>
      </RouterLink>

      <div class="rotulo"><span>Próximos turnos</span><RouterLink :to="{ name: 'minha-escala' }">Ver tudo</RouterLink></div>
      <div v-if="!proximos.length" class="cartao pad vazio">
        <b>Nenhum turno confirmado</b>
        <p>Seus turnos aparecem aqui depois que o líder publicar a escala.</p>
      </div>
      <div v-else class="cartao lista">
        <div v-for="turno in proximos" :key="turno.id" class="turno">
          <div class="data"><span>{{ diaDaSemana(turno.data) }}</span><b>{{ diaDoMes(turno.data) }}</b></div>
          <div class="principal">
            <strong>{{ tituloDoTurno(turno) }}</strong>
            <small>{{ detalheDoTurno(turno) }}</small>
          </div>
          <span v-if="turno.choque.length" class="pilula critico"><i />Choque</span>
        </div>
      </div>

      <div class="rotulo">
        <span>Avisos</span>
        <button v-if="avisos.nao_lidos" class="link" @click="marcarLidos">Marcar como lidos</button>
      </div>
      <div v-if="!avisos.avisos.length" class="cartao pad vazio"><b>Sem avisos por enquanto</b></div>
      <div v-else class="cartao lista">
        <div v-for="aviso in avisos.avisos.slice(0, 5)" :key="aviso.id" class="aviso" :class="{ novo: !aviso.lido }">
          <i v-if="!aviso.lido" class="ponto" aria-label="não lido" />
          <span>{{ aviso.texto }}</span>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.escala { display: block; text-decoration: none; color: inherit; }
.escala p { margin-top: 6px; color: var(--ink-3); font-size: 12.5px; }
.linha { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.vazio { color: var(--ink-3); font-size: 13px; text-align: center; }
.vazio b { display: block; color: var(--ink); margin-bottom: 4px; font-size: 14px; }
.turno { display: flex; align-items: center; gap: 12px; padding: 12px 14px; }
.data { width: 44px; text-align: center; font-family: var(--mono); flex: none; }
.data span { display: block; font-size: 9.5px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-3); }
.data b { display: block; font-size: 18px; font-weight: 600; }
.principal { flex: 1; min-width: 0; }
.principal strong { display: block; font-size: 14.5px; font-weight: 600; }
.principal small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 1px; }
.aviso { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; font-size: 13.5px; color: var(--ink-2); }
.aviso.novo { color: var(--ink); background: var(--brand-soft); }
.ponto { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); margin-top: 6px; flex: none; }
.rotulo a, .link { font-family: var(--ui); font-size: 12.5px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--brand); text-decoration: none; }
.repetir { color: inherit; font-weight: 700; text-decoration: underline; }
</style>
