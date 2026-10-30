<script setup>
import { onMounted, ref, watch } from 'vue';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { useModo } from '../stores/modo.js';
import { dataCurta, diaDaSemana } from '../utils/datas.js';
import { descreverConflito } from '../utils/descricoes.js';
import { usePagina } from '../utils/listas.js';
import { iniciaisDe } from '../utils/status.js';

const modo = useModo();
const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const trocas = ref([]);
const ocupado = ref(false);

const alvo = ref(null); // pedido que está sendo resolvido
const candidatos = ref([]);
const { visiveis: trocasVisiveis, restantes: trocasRestantes, mais: maisTrocas } = usePagina(trocas, 10);
const { visiveis: candidatosVisiveis, restantes: candidatosRestantes, mais: maisCandidatos, reiniciar: reiniciarCandidatos } = usePagina(candidatos, 15);
const carregandoCandidatos = ref(false);

const primeiro = (nome) => nome.split(' ')[0];

async function carregar() {
  if (!modo.ministerio) return;

  try {
    const resposta = await api.get(`/ministerios/${modo.ministerio.id}/trocas`);
    trocas.value = resposta.trocas;
    modo.trocasAbertas = resposta.total_abertas;
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

async function resolver(troca) {
  alvo.value = troca;
  candidatos.value = [];
  reiniciarCandidatos();
  carregandoCandidatos.value = true;

  try {
    candidatos.value = (await api.get(`/trocas/${troca.id}/candidatos`)).candidatos;
  } catch (e) {
    mensagens.erro(e);
  } finally {
    carregandoCandidatos.value = false;
  }
}

async function executar(acao, sucesso) {
  if (ocupado.value) return;
  ocupado.value = true;

  try {
    await acao();
    mensagens.mostrar(sucesso);
    alvo.value = null;
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
    await carregar();
  }
}

const manter = (troca) => executar(() => api.post(`/trocas/${troca.id}/manter`), `${primeiro(troca.pessoa.nome)} segue no turno.`);
const vagaEmAberto = () => executar(() => api.post(`/trocas/${alvo.value.id}/vaga-aberta`), 'Vaga aberta de novo nesse turno.');
const escolher = (candidato) => executar(
  () => api.post(`/trocas/${alvo.value.id}/substituir`, { pessoa_id: candidato.pessoa_id }),
  `${primeiro(candidato.nome)} entrou no lugar de ${primeiro(alvo.value.pessoa.nome)}.`,
);

watch(() => modo.ministerio?.id, carregar);
onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Trocas</h2>
      <p>{{ modo.ministerio?.nome }} · quem pediu continua na escala até você resolver</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <div v-else-if="!trocas.length" class="cartao pad vazio">
      <b>Nada pendente</b>
      <p>Quando alguém não puder servir num turno, o pedido cai aqui.</p>
    </div>

    <article v-for="troca in trocasVisiveis" :key="troca.id" class="cartao pad troca">
      <div class="linha">
        <span class="pilula critico"><i />Substituição</span>
        <span class="quando">{{ diaDaSemana(troca.turno.data) }}, {{ dataCurta(troca.turno.data) }}</span>
      </div>
      <div class="pessoa">
        <span class="inicial">{{ iniciaisDe(troca.pessoa.nome) }}</span>
        <span class="texto">
          <b>{{ troca.pessoa.nome }}</b> não vai poder
          <small>{{ troca.turno.culto || troca.turno.posto?.nome }} · {{ troca.turno.inicio }} às {{ troca.turno.fim }}</small>
        </span>
      </div>
      <div class="motivo"><b>{{ troca.motivo }}</b><p v-if="troca.observacao">“{{ troca.observacao }}”</p></div>
      <p class="nota">{{ troca.marcaram }} pessoa(s) marcaram disponibilidade neste turno</p>
      <div class="acoes">
        <button class="botao contorno" :disabled="ocupado" @click="manter(troca)">Manter</button>
        <button class="botao primario" :disabled="ocupado" @click="resolver(troca)">Resolver</button>
      </div>
    </article>
    <button v-if="trocasRestantes" class="botao contorno largo" @click="maisTrocas">Mostrar mais ({{ trocasRestantes }} restantes)</button>

    <Folha
      :aberta="!!alvo" titulo="Resolver substituição"
      :legenda="alvo ? `${alvo.turno.posto?.nome} · ${dataCurta(alvo.turno.data)} · no lugar de ${primeiro(alvo.pessoa.nome)}` : ''" @fechar="alvo = null"
    >
      <div class="miolo">
        <div class="rotulo"><span>Quem pode entrar</span></div>
        <p v-if="carregandoCandidatos" class="vazio">Carregando…</p>
        <div v-else-if="!candidatos.length" class="vazio"><b>Ninguém disponível</b>Nenhuma outra pessoa do posto está livre. Você pode deixar a vaga em aberto.</div>
        <div v-else class="cartao lista">
          <div v-for="c in candidatosVisiveis" :key="c.pessoa_id" class="candidato">
            <div class="linha">
              <span class="inicial" :class="{ mudo: c.choques.length }">{{ iniciaisDe(c.nome) }}</span>
              <span class="texto">
                <b>{{ c.nome }}</b>
                <small>{{ c.marcou ? 'marcou disponibilidade neste turno' : 'não marcou este dia' }} · {{ c.turnos_no_total }} turno(s) no total<template v-if="c.fora_da_banda"> · fora da banda fixa</template></small>
              </span>
              <span v-if="c.choques.length" class="pilula critico"><i />Choca</span>
              <span v-else-if="c.marcou" class="pilula ok"><i />Livre</span>
              <button class="botao suave pequeno" :disabled="ocupado" @click="escolher(c)">{{ c.choques.length ? 'Escolher mesmo assim' : 'Escolher' }}</button>
            </div>
            <p v-if="c.choques.length" class="choque">⚠ já escalado em {{ descreverConflito(c.choques[0]) }}</p>
          </div>
        </div>
        <button v-if="candidatosRestantes" class="botao contorno largo" @click="maisCandidatos">Mostrar mais ({{ candidatosRestantes }} restantes)</button>
      </div>
      <template #rodape><button class="botao contorno largo" :disabled="ocupado" @click="vagaEmAberto">Deixar a vaga em aberto</button></template>
    </Folha>
  </section>
</template>

<style scoped>
.troca { display: flex; flex-direction: column; gap: 11px; }
.linha { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.quando { font-size: 12.5px; color: var(--ink-3); }
.pessoa { display: flex; align-items: center; gap: 11px; }
.texto { flex: 1; min-width: 0; font-size: 13.5px; }
.texto small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 2px; }
.motivo { padding: 10px 12px; background: var(--surface-2); border-radius: 10px; font-size: 13px; }
.motivo p { margin-top: 3px; color: var(--ink-2); }
.nota { font-size: 12.5px; color: var(--ink-3); }
.acoes { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
.miolo { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 10px; }
.rotulo { margin: 4px 4px -4px; }
.candidato { padding: 11px 13px; }
.choque { margin: 8px 0 0 47px; font-size: 12px; color: var(--crit); font-weight: 600; line-height: 1.4; }
</style>
