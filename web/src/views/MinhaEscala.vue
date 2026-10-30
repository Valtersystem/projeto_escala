<script setup>
import { computed, onMounted, ref } from 'vue';
import Alerta from '../components/Alerta.vue';
import Calendario from '../components/Calendario.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { dataCurta, diaDaSemana, diaDoMes, hojeIso, mesDe, mesPorExtenso, somaMes } from '../utils/datas.js';
import { MOTIVOS_DE_TROCA, descreverConflito, detalheDoTurno, tituloDoTurno } from '../utils/descricoes.js';

const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const turnos = ref([]); // confirmados em escala publicada
const aguardando = ref([]); // marcados por mim, ainda sem confirmação do líder
const mes = ref(null); // "AAAA-MM" do calendário
const dia = ref(null); // dia tocado no calendário

const alvo = ref(null); // turno para o qual se está pedindo substituição
const motivo = ref(MOTIVOS_DE_TROCA[0]);
const observacao = ref('');
const enviando = ref(false);

// No calendário, o choque pesa mais que "você serve", que pesa mais que "aguardando".
const dias = computed(() => {
  const mapa = {};
  for (const t of aguardando.value) mapa[t.data] = 'aguardando';
  for (const t of turnos.value) mapa[t.data] = t.choque.length || mapa[t.data] === 'choque' ? 'choque' : 'confirmado';
  return mapa;
});

const doMes = computed(() => [
  ...turnos.value.map((turno) => ({ turno, aguardando: false })),
  ...aguardando.value.map((turno) => ({ turno, aguardando: true })),
]
  .filter((o) => mesDe(o.turno.data) === mes.value && (!dia.value || o.turno.data === dia.value))
  .sort((a, b) => a.turno.data.localeCompare(b.turno.data) || a.turno.inicio.localeCompare(b.turno.inicio)));

const total = computed(() => turnos.value.length + aguardando.value.length);

// Abre no mês do próximo turno confirmado; sem nenhum, no do primeiro que houver; sem nada, no mês de hoje.
function mesInicial() {
  const proximo = turnos.value.find((t) => t.data >= hojeIso()) ?? turnos.value[0] ?? aguardando.value[0];
  return mesDe(proximo?.data ?? hojeIso());
}

async function carregar() {
  try {
    const resposta = await api.get('/minha-escala');
    turnos.value = resposta.turnos;
    aguardando.value = resposta.aguardando ?? [];
    mes.value ??= mesInicial();
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

function mudarMes(k) {
  mes.value = somaMes(mes.value, k);
  dia.value = null;
}

const tocarDia = (iso) => { dia.value = dia.value === iso ? null : iso; };

function pedir(turno) {
  alvo.value = turno;
  motivo.value = MOTIVOS_DE_TROCA[0];
  observacao.value = '';
}

async function enviarPedido() {
  enviando.value = true;
  try {
    await api.post(`/turnos/${alvo.value.id}/trocas`, { motivo: motivo.value, observacao: observacao.value.trim() || null });
    alvo.value = null;
    mensagens.mostrar('Pedido enviado ao líder. Você continua no turno até ele resolver.');
    await carregar();
  } catch (e) {
    mensagens.erro(e);
  } finally {
    enviando.value = false;
  }
}

async function cancelarPedido(turno) {
  try {
    await api.delete(`/trocas/${turno.troca_aberta_id}`);
    mensagens.mostrar('Pedido cancelado. Você segue no turno.');
    await carregar();
  } catch (e) {
    mensagens.erro(e);
  }
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Minha escala</h2>
      <p>
        {{ turnos.length ? `${turnos.length} turno(s) confirmado(s)` : 'Nenhum turno confirmado ainda' }}<template v-if="aguardando.length"> · {{ aguardando.length }} aguardando</template>
      </p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <div v-else-if="!total" class="cartao pad vazio">
      <b>Nenhum turno por enquanto</b>
      <p>Seus turnos aparecem aqui depois que o líder publicar a escala.</p>
    </div>

    <template v-else>
      <Calendario :mes="mes" :dias="dias" :selecionado="dia" @mes="mudarMes" @dia="tocarDia" />

      <div class="rotulo">
        <span>{{ dia ? dataCurta(dia) : `Turnos de ${mesPorExtenso(mes)}` }}</span>
        <button v-if="dia" class="ver-mes" @click="dia = null">Ver o mês</button>
      </div>

      <div v-if="!doMes.length" class="cartao pad vazio">
        <b>{{ dia ? 'Nada neste dia' : 'Nada neste mês' }}</b>
        <p>Use as setas para ver outro mês.</p>
      </div>

      <div v-else class="cartao lista">
        <article v-for="{ turno, aguardando: espera } in doMes" :key="`${espera}-${turno.id}`" class="turno" :class="{ espera }">
          <div class="data"><span>{{ diaDaSemana(turno.data) }}</span><b>{{ diaDoMes(turno.data) }}</b></div>
          <div class="principal">
            <strong>{{ tituloDoTurno(turno) }}</strong>
            <small>{{ turno.ministerio }} · {{ detalheDoTurno(turno) }}</small>

            <div v-if="espera" class="funcoes"><span class="pilula atencao"><i />Aguardando o líder confirmar</span></div>
            <template v-else>
              <div class="funcoes">
                <span v-for="funcao in turno.funcoes" :key="funcao.id" class="pilula marca"><i />{{ funcao.nome }}</span>
                <span v-if="!turno.funcoes.length" class="pilula mudo">função a combinar</span>
              </div>

              <p v-if="turno.choque.length" class="choque">
                ⚠ Choque de escala em {{ dataCurta(turno.data) }}: {{ descreverConflito(turno.choque[0]) }}. Avise o líder.
              </p>

              <div v-if="turno.data >= hojeIso()" class="acoes">
                <template v-if="turno.troca_aberta_id">
                  <span class="pilula critico"><i />Substituição pedida</span>
                  <button class="botao contorno pequeno" @click="cancelarPedido(turno)">Cancelar pedido</button>
                </template>
                <button v-else class="botao contorno pequeno" @click="pedir(turno)">Não vou poder</button>
              </div>
            </template>
          </div>
        </article>
      </div>

      <Alerta v-if="aguardando.length && !dia" tipo="info" icone="calendario" titulo="Aguardando não é escala">
        Os dias em cinza são os que você marcou. Eles só viram compromisso quando o líder publicar.
      </Alerta>
    </template>

    <Folha :aberta="!!alvo" titulo="Pedir substituição" :legenda="alvo ? `${tituloDoTurno(alvo)} · ${dataCurta(alvo.data)}` : ''" @fechar="alvo = null">
      <div class="formulario-troca">
        <Alerta tipo="info" icone="troca">Você <b>continua na escala</b> até o líder resolver. Ele decide quem entra no seu lugar.</Alerta>

        <div class="formulario">
          <div class="campo">
            <label for="motivo">Motivo</label>
            <select id="motivo" v-model="motivo"><option v-for="m in MOTIVOS_DE_TROCA" :key="m">{{ m }}</option></select>
          </div>
          <div class="campo">
            <label for="obs">Quer explicar? (opcional)</label>
            <textarea id="obs" v-model="observacao" rows="3" maxlength="500" placeholder="Ex.: viajo no sábado e só volto na segunda." />
          </div>
        </div>
      </div>
      <template #rodape>
        <button class="botao primario largo" :disabled="enviando" @click="enviarPedido">{{ enviando ? 'Enviando…' : 'Enviar pedido ao líder' }}</button>
      </template>
    </Folha>
  </section>
</template>

<style scoped>
.vazio { color: var(--ink-3); font-size: 13px; text-align: center; }
.vazio b { display: block; color: var(--ink); margin-bottom: 4px; font-size: 14px; }
.repetir { color: inherit; font-weight: 700; text-decoration: underline; }
.ver-mes { font-family: var(--ui); font-size: 12.5px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--brand); }
.turno { display: flex; gap: 12px; padding: 13px 14px; }
.turno.espera { background: var(--surface-2); }
.data { width: 44px; text-align: center; font-family: var(--mono); flex: none; }
.data span { display: block; font-size: 9.5px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-3); }
.data b { display: block; font-size: 18px; font-weight: 600; }
.principal { flex: 1; min-width: 0; }
.principal strong { display: block; font-size: 14.5px; font-weight: 600; }
.principal small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 1px; }
.funcoes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.choque { margin-top: 8px; color: var(--crit); font-size: 12px; font-weight: 600; line-height: 1.4; }
.acoes { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px; }
.formulario-troca { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
</style>
