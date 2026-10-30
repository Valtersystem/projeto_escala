<script setup>
import { computed, ref, watch } from 'vue';
import Alerta from './Alerta.vue';
import Folha from './Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { dataCurta, diaDaSemana } from '../utils/datas.js';
import { descreverConflito } from '../utils/descricoes.js';
import { iniciaisDe } from '../utils/status.js';

/** A folha do turno: quem está confirmado, quem marcou disponibilidade e as funções de cada um. */
const props = defineProps({ turnoId: { type: Number, default: null }, escala: { type: Object, required: true } });
const emit = defineEmits(['fechar', 'mudou']);

const mensagens = useMensagens();

const dados = ref(null);
const funcoes = ref([]);
const erro = ref('');
const ocupado = ref(false);
const escolhendo = ref(null); // pessoa cujas funções estão sendo escolhidas

const aberta = computed(() => props.turnoId !== null);
const turno = computed(() => dados.value?.turno);
const confirmaveis = computed(() => props.escala.status !== 'rascunho');

async function carregar() {
  try {
    const [folha, lista] = await Promise.all([
      api.get(`/turnos/${props.turnoId}/pessoas`),
      api.get(`/ministerios/${props.escala.ministerio_id}/funcoes`),
    ]);
    dados.value = folha;
    funcoes.value = lista.funcoes;
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  }
}

async function executar(acao) {
  if (ocupado.value) return;
  ocupado.value = true;
  try {
    await acao();
    await carregar();
    emit('mudou');
  } catch (e) {
    mensagens.erro(e);
    await carregar();
  } finally {
    ocupado.value = false;
  }
}

const confirmar = (pessoa) => executar(async () => {
  const { choques } = await api.post(`/turnos/${props.turnoId}/confirmacoes`, { pessoa_id: pessoa.pessoa_id });
  mensagens.mostrar(choques.length ? `${pessoa.nome} confirmado, mas choca com outro turno.` : `${pessoa.nome} confirmado.`);
  // Com funções cadastradas, já pergunta o que a pessoa vai fazer.
  if (funcoes.value.length) escolhendo.value = pessoa.pessoa_id;
});

const tirar = (confirmado) => executar(async () => {
  await api.delete(`/turnos/${props.turnoId}/confirmacoes/${confirmado.pessoa_id}`);
  if (escolhendo.value === confirmado.pessoa_id) escolhendo.value = null;
  mensagens.mostrar(`${confirmado.nome} saiu do turno.`);
});

const alternarFuncao = (confirmado, funcao) => executar(async () => {
  const atuais = confirmado.funcoes.map((f) => f.id);
  const novas = atuais.includes(funcao.id) ? atuais.filter((id) => id !== funcao.id) : [...atuais, funcao.id];
  await api.put(`/turnos/${props.turnoId}/confirmacoes/${confirmado.pessoa_id}/funcoes`, { funcao_ids: novas });
});

const aumentarVagas = () => executar(async () => {
  await api.patch(`/turnos/${props.turnoId}`, { versao: turno.value.versao, vagas: turno.value.vagas + 1 });
  mensagens.mostrar('Mais uma vaga.');
});

watch(() => props.turnoId, (id) => {
  dados.value = null;
  escolhendo.value = null;
  if (id !== null) carregar();
}, { immediate: true });
</script>

<template>
  <Folha
    :aberta="aberta" :titulo="turno ? `${turno.culto || turno.posto?.nome} · ${diaDaSemana(turno.data)}, ${dataCurta(turno.data)}` : 'Turno'"
    :legenda="turno ? `${turno.inicio} às ${turno.fim} · ${turno.posto?.nome}${turno.banda ? ' · ' + turno.banda.nome : ''}` : ''" @fechar="emit('fechar')"
  >
    <div class="miolo">
      <Alerta v-if="erro" tipo="critico">{{ erro }}</Alerta>
      <p v-else-if="!dados" class="vazio">Carregando…</p>

      <template v-else>
        <Alerta v-if="dados.lotado" tipo="aviso" titulo="Turno lotado">
          {{ dados.confirmados.length }} de {{ turno.vagas }} vagas preenchidas.
          <template v-if="escala.status === 'aberta'"> <button class="repetir" :disabled="ocupado" @click="aumentarVagas">Aumentar uma vaga</button></template>
          <template v-else> Reabra a escala para aumentar as vagas.</template>
        </Alerta>

        <div class="rotulo"><span>Confirmados ({{ dados.confirmados.length }}/{{ turno.vagas }})</span></div>
        <p v-if="!dados.confirmados.length" class="vazio">Ninguém confirmado ainda.</p>
        <div v-else class="cartao lista">
          <div v-for="c in dados.confirmados" :key="c.pessoa_id" class="pessoa">
            <div class="linha">
              <span class="inicial">{{ iniciaisDe(c.nome) }}</span>
              <span class="nome">
                <strong>{{ c.nome }}</strong>
                <small v-if="funcoes.length">
                  <template v-if="c.funcoes.length">{{ c.funcoes.map((f) => f.nome).join(' · ') }}</template>
                  <span v-else class="alerta-texto">sem função definida</span>
                </small>
                <small v-if="c.fora_da_banda" class="alerta-texto">fora da banda fixa</small>
              </span>
              <button v-if="funcoes.length" class="botao suave pequeno" @click="escolhendo = escolhendo === c.pessoa_id ? null : c.pessoa_id">Funções</button>
              <button class="botao contorno pequeno" :disabled="ocupado" @click="tirar(c)">Tirar</button>
            </div>
            <div v-if="escolhendo === c.pessoa_id" class="chips funcoes">
              <button
                v-for="f in funcoes" :key="f.id" class="chip" :disabled="ocupado"
                :aria-pressed="c.funcoes.some((x) => x.id === f.id)" @click="alternarFuncao(c, f)"
              >{{ f.nome }}</button>
            </div>
          </div>
        </div>

        <div class="rotulo"><span>Marcaram disponibilidade ({{ dados.disponiveis.length }})</span></div>
        <p v-if="!dados.disponiveis.length" class="vazio">Ninguém disponível. Vai precisar chamar alguém do posto.</p>
        <div v-else class="cartao lista">
          <div v-for="p in dados.disponiveis" :key="p.pessoa_id" class="pessoa">
            <div class="linha">
              <span class="inicial" :class="{ mudo: p.choques.length }">{{ iniciaisDe(p.nome) }}</span>
              <span class="nome">
                <strong>{{ p.nome }}</strong>
                <small>{{ p.turnos_no_mes }} turno(s) no mês<template v-if="p.fora_da_banda"> · <b class="alerta-texto">fora da banda fixa</b></template></small>
              </span>
              <span v-if="p.choques.length" class="pilula critico"><i />Choca</span>
              <span v-else-if="p.carga" class="pilula atencao"><i />Carga</span>
              <button v-if="confirmaveis" class="botao suave pequeno" :disabled="ocupado || dados.lotado" @click="confirmar(p)">
                {{ p.choques.length ? 'Confirmar mesmo assim' : 'Confirmar' }}
              </button>
            </div>
            <p v-if="p.choques.length" class="choque">⚠ já escalado em {{ descreverConflito(p.choques[0]) }}</p>
          </div>
        </div>
      </template>
    </div>
  </Folha>
</template>

<style scoped>
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 12px; }
.rotulo { margin: 4px 4px -4px; }
.pessoa { padding: 11px 13px; }
.linha { display: flex; align-items: center; gap: 11px; }
.nome { flex: 1; min-width: 0; }
.nome strong { display: block; font-size: 14px; font-weight: 600; }
.nome small { display: block; font-size: 12px; color: var(--ink-3); margin-top: 1px; }
.alerta-texto { color: var(--warn); font-weight: 600; }
.choque { margin: 8px 0 0 47px; font-size: 12px; color: var(--crit); font-weight: 600; line-height: 1.4; }
.funcoes { margin: 10px 0 2px 47px; }
</style>
