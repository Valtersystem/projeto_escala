<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Alerta from '../components/Alerta.vue';
import { api } from '../api/cliente.js';
import { mesPorExtenso } from '../utils/datas.js';

const carregando = ref(true);
const erro = ref('');
const relatorio = ref(null);
const mes = ref('');

const totais = computed(() => relatorio.value?.totais);
const vazio = computed(() => !totais.value?.turnos);

const atencao = computed(() => {
  const t = totais.value;
  if (!t) return [];

  return [
    t.ministerios_sem_lider && { chave: 'lider', texto: `${t.ministerios_sem_lider} ministério(s) sem líder` },
    t.pedidos_de_entrada && { chave: 'pedidos', texto: `${t.pedidos_de_entrada} pedido(s) de entrada esperando` },
    t.trocas_abertas && { chave: 'trocas', texto: `${t.trocas_abertas} pedido(s) de troca sem resposta` },
  ].filter(Boolean);
});

async function carregar() {
  carregando.value = true;
  erro.value = '';

  try {
    relatorio.value = await api.get(`/relatorio-geral${mes.value ? `?mes=${mes.value}` : ''}`);
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

watch(mes, carregar);
onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Relatório</h2>
      <p>A igreja inteira, ministério por ministério</p>
    </div>

    <div class="formulario">
      <div class="campo">
        <label for="mes">Mês</label>
        <input id="mes" v-model="mes" type="month" />
      </div>
      <button v-if="mes" class="botao contorno pequeno" @click="mes = ''">Todos os meses</button>
    </div>

    <RouterLink class="botao suave largo" :to="{ name: 'adm-historico' }">Ver histórico de alterações</RouterLink>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando && !relatorio" class="vazio">Carregando…</p>

    <template v-else-if="relatorio">
      <div v-if="atencao.length" class="atencao">
        <span v-for="a in atencao" :key="a.chave" class="pilula atencao"><i />{{ a.texto }}</span>
      </div>

      <div v-if="vazio" class="cartao pad vazio">
        <b>Sem dados {{ mes ? `em ${mesPorExtenso(mes)}` : 'ainda' }}</b>
        <p>O relatório aparece quando houver turnos em escalas liberadas.</p>
      </div>

      <template v-else>
        <div class="estatisticas">
          <div class="estatistica"><b>{{ totais.vagas_preenchidas_pct }}%</b><span>vagas preenchidas</span></div>
          <div class="estatistica"><b>{{ totais.turnos_sem_ninguem }}</b><span>turnos sem ninguém</span></div>
          <div class="estatistica"><b>{{ totais.pessoas_ativas }}</b><span>pessoas ativas</span></div>
        </div>

        <div class="rotulo"><span>Por ministério</span></div>
        <div class="cartao lista">
          <div v-for="m in relatorio.ministerios" :key="m.id" class="ministerio">
            <div class="principal">
              <strong>{{ m.nome }}</strong>
              <small>{{ m.lider ? `Líder: ${m.lider.nome}` : 'Sem líder' }} · {{ m.voluntarios_ativos }} voluntário(s) · {{ m.turnos }} turno(s)</small>
              <span class="trilho"><i :style="{ width: `${m.vagas_preenchidas_pct}%` }" /></span>
            </div>
            <div class="numeros">
              <b>{{ m.vagas_preenchidas_pct }}%</b>
              <small v-if="m.turnos_sem_ninguem">{{ m.turnos_sem_ninguem }} vazio(s)</small>
            </div>
          </div>
        </div>

        <template v-if="relatorio.acima_do_limite?.length">
          <div class="rotulo"><span>Acima do limite de turnos no mês</span></div>
          <div class="cartao lista">
            <div v-for="p in relatorio.acima_do_limite" :key="p.pessoa_id" class="ministerio">
              <div class="principal"><strong>{{ p.nome }}</strong><small>somando todos os ministérios</small></div>
              <span class="pilula atencao"><i />{{ p.turnos }} turnos</span>
            </div>
          </div>
        </template>
        <p v-else-if="mes" class="vazio">Ninguém passou do limite de turnos neste mês.</p>
      </template>
    </template>
  </section>
</template>

<style scoped>
a.botao { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.atencao { display: flex; flex-wrap: wrap; gap: 7px; }
.ministerio { display: flex; align-items: center; gap: 12px; padding: 12px 14px; }
.principal { flex: 1; min-width: 0; }
.principal strong { display: block; font-size: 14px; font-weight: 600; }
.principal small { display: block; color: var(--ink-3); font-size: 12.5px; margin: 1px 0 7px; }
.trilho { display: block; height: 8px; background: var(--surface-2); border-radius: 4px; overflow: hidden; }
.trilho i { display: block; height: 100%; background: var(--brand); border-radius: 4px; }
.numeros { text-align: right; }
.numeros b { display: block; font-family: var(--mono); font-size: 15px; }
.numeros small { color: var(--crit); font-size: 11.5px; font-weight: 600; }
</style>
