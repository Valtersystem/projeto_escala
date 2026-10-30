<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Alerta from '../components/Alerta.vue';
import { api } from '../api/cliente.js';
import { useModo } from '../stores/modo.js';
import { diaDaSemana, diaDoMes, mesPorExtenso } from '../utils/datas.js';

const modo = useModo();

const carregando = ref(true);
const erro = ref('');
const relatorio = ref(null);
const meses = ref([]);
const mes = ref('');

const maximo = computed(() => Math.max(1, ...(relatorio.value?.turnos_por_pessoa ?? []).map((p) => p.turnos)));
const vazio = computed(() => !relatorio.value?.total_de_turnos);

async function carregar() {
  if (!modo.ministerio) return;

  carregando.value = true;
  erro.value = '';

  try {
    const base = `/ministerios/${modo.ministerio.id}`;
    const [dados, escalas] = await Promise.all([
      api.get(`${base}/relatorio${mes.value ? `?mes=${mes.value}` : ''}`),
      api.get(`${base}/escalas`),
    ]);
    relatorio.value = dados;
    // Só meses que já saíram do rascunho têm o que mostrar.
    meses.value = escalas.escalas.filter((e) => e.status !== 'rascunho').map((e) => e.mes).sort();
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

watch(() => [modo.ministerio?.id, mes.value], carregar);
onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Relatório</h2>
      <p>{{ modo.ministerio?.nome }} · só o seu ministério</p>
    </div>

    <div v-if="meses.length" class="formulario">
      <div class="campo">
        <label for="mes">Período</label>
        <select id="mes" v-model="mes">
          <option value="">Todos os meses</option>
          <option v-for="m in meses" :key="m" :value="m">{{ mesPorExtenso(m) }}</option>
        </select>
      </div>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando && !relatorio" class="vazio">Carregando…</p>

    <div v-else-if="vazio" class="cartao pad vazio">
      <b>Sem dados ainda</b>
      <p>O relatório aparece quando houver turnos em escalas liberadas.</p>
    </div>

    <template v-else>
      <div class="estatisticas">
        <div class="estatistica"><b>{{ relatorio.vagas_preenchidas_pct }}%</b><span>vagas preenchidas</span></div>
        <div class="estatistica"><b>{{ relatorio.turnos_sem_ninguem }}</b><span>turnos sem ninguém</span></div>
        <div class="estatistica"><b>{{ relatorio.voluntarios_ativos }}</b><span>voluntários ativos</span></div>
      </div>

      <div class="rotulo"><span>Turnos por pessoa</span></div>
      <div class="cartao pad barras">
        <div v-for="p in relatorio.turnos_por_pessoa" :key="p.pessoa_id" class="barra">
          <span class="nome">{{ p.nome.split(' ')[0] }}</span>
          <span class="trilho"><i :class="{ acima: p.acima_do_limite }" :style="{ width: `${(p.turnos / maximo) * 100}%` }" /></span>
          <span class="valor">{{ p.turnos }}</span>
        </div>
        <p v-if="relatorio.turnos_por_pessoa.some((p) => p.acima_do_limite)" class="nota">Em laranja: acima do limite de turnos por mês.</p>
      </div>

      <template v-if="relatorio.onde_aperta.length">
        <div class="rotulo"><span>Onde aperta</span></div>
        <div class="cartao lista">
          <div v-for="t in relatorio.onde_aperta.slice(0, 5)" :key="t.id" class="aperta">
            <div class="principal">
              <strong>{{ t.culto || t.posto?.nome }} · {{ diaDaSemana(t.data) }} {{ diaDoMes(t.data) }}/{{ t.data.slice(5, 7) }}</strong>
              <small>ninguém marcou disponibilidade</small>
            </div>
            <span class="pilula critico"><i />Vazio</span>
          </div>
        </div>
      </template>
    </template>
  </section>
</template>

<style scoped>
.barras { display: flex; flex-direction: column; gap: 2px; }
.barra { display: grid; grid-template-columns: 82px 1fr 34px; align-items: center; gap: 10px; padding: 6px 0; }
.nome { font-size: 13px; color: var(--ink-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.trilho { height: 16px; background: var(--surface-2); border-radius: 4px; overflow: hidden; }
.trilho i { display: block; height: 100%; background: var(--brand); border-radius: 0 4px 4px 0; }
.trilho i.acima { background: var(--warn); }
.valor { font-family: var(--mono); font-size: 12.5px; text-align: right; color: var(--ink-2); font-variant-numeric: tabular-nums; }
.nota { margin-top: 8px; font-size: 12px; color: var(--ink-3); }
.aperta { display: flex; align-items: center; gap: 12px; padding: 12px 14px; }
.principal { flex: 1; min-width: 0; }
.principal strong { display: block; font-size: 14px; font-weight: 600; }
.principal small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 1px; }
</style>
