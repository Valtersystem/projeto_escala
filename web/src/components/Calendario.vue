<script setup>
// Calendário do mês: cada dia pode estar "confirmado" (você serve), "aguardando" (marcou e o líder ainda não confirmou) ou em "choque".
import { computed } from 'vue';
import Icone from './Icone.vue';
import { dataCurta, gradeDoMes, hojeIso, mesPorExtenso } from '../utils/datas.js';

const props = defineProps({
  mes: { type: String, required: true }, // "AAAA-MM"
  dias: { type: Object, default: () => ({}) }, // { "AAAA-MM-DD": 'confirmado' | 'aguardando' | 'choque' }
  selecionado: { type: String, default: null },
});
defineEmits(['mes', 'dia']);

const SEMANA = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
const ROTULO = { confirmado: 'você serve', aguardando: 'marcou, aguardando o líder', choque: 'choque de escala' };

const grade = computed(() => gradeDoMes(props.mes));
const lista = computed(() => Array.from({ length: grade.value.dias }, (_, i) => {
  const dia = i + 1;
  const iso = `${props.mes}-${String(dia).padStart(2, '0')}`;
  return { dia, iso, estado: props.dias[iso] ?? null };
}));
const temChoque = computed(() => Object.values(props.dias).includes('choque'));
</script>

<template>
  <section class="cartao calendario">
    <div class="topo">
      <b>{{ mesPorExtenso(mes) }}</b>
      <span class="nav">
        <button aria-label="Mês anterior" @click="$emit('mes', -1)"><Icone nome="voltar" :tamanho="17" /></button>
        <button aria-label="Próximo mês" class="avancar" @click="$emit('mes', 1)"><Icone nome="voltar" :tamanho="17" /></button>
      </span>
    </div>

    <div class="semana"><span v-for="(letra, i) in SEMANA" :key="i">{{ letra }}</span></div>
    <div class="grade">
      <span v-for="n in grade.vazios" :key="`v${n}`" class="dia vazio" />
      <button
        v-for="d in lista" :key="d.iso" class="dia" :class="[d.estado, { hoje: d.iso === hojeIso() }]"
        :aria-pressed="selecionado === d.iso" :aria-disabled="!d.estado"
        :aria-label="`${dataCurta(d.iso)}${d.estado ? ', ' + ROTULO[d.estado] : ''}`"
        @click="d.estado && $emit('dia', d.iso)"
      >{{ d.dia }}<i v-if="d.estado" class="ponto" /></button>
    </div>

    <div class="legenda">
      <span><i class="c" />Você serve</span>
      <span><i />Marcou, aguardando</span>
      <span><i class="h" />Hoje</span>
      <span v-if="temChoque"><i class="x" />Choque</span>
    </div>
  </section>
</template>

<style scoped>
.calendario { padding: 13px 11px 15px; }
.topo { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 3px 11px; }
.topo b { display: block; font-size: 15.5px; font-weight: 700; letter-spacing: -0.01em; }
.topo b::first-letter { text-transform: uppercase; }
.nav { display: flex; gap: 5px; flex: none; }
.nav button { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; border: 1px solid var(--line); background: var(--surface); color: var(--ink-2); }
.nav .avancar :deep(svg) { transform: scaleX(-1); }
.semana, .grade { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.semana { margin-bottom: 5px; }
.semana span { text-align: center; font-family: var(--mono); font-size: 9.5px; color: var(--ink-3); letter-spacing: 0.04em; }
.dia {
  aspect-ratio: 1; min-height: 40px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 2px; font-size: 13.5px; color: var(--ink-3); border: 1px solid transparent; background: none;
}
.dia.vazio { visibility: hidden; }
.dia.hoje { border-color: var(--line-2); color: var(--ink); font-weight: 700; }
.dia.aguardando { background: var(--surface-2); border-color: var(--line-2); color: var(--ink); font-weight: 600; }
.dia.confirmado { background: var(--brand); border-color: var(--brand); color: var(--on-brand); font-weight: 700; }
.dia.choque { background: var(--crit); border-color: var(--crit); color: #fff; font-weight: 700; }
.dia[aria-pressed='true'] { box-shadow: 0 0 0 2px var(--brand); }
.ponto { width: 4px; height: 4px; border-radius: 50%; background: currentColor; }
.legenda { display: flex; flex-wrap: wrap; gap: 13px; margin-top: 13px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 12px; color: var(--ink-3); }
.legenda span { display: inline-flex; align-items: center; gap: 6px; }
.legenda i { width: 13px; height: 13px; border-radius: 5px; display: inline-block; border: 1px solid var(--line-2); background: var(--surface-2); }
.legenda i.c { background: var(--brand); border-color: var(--brand); }
.legenda i.h { background: none; }
.legenda i.x { background: var(--crit); border-color: var(--crit); }
</style>
