<script setup>
import { computed, onMounted, ref } from 'vue';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { dataCurta, diaDaSemana, diaDoMes, hojeIso, mesPorExtenso } from '../utils/datas.js';
import { MOTIVOS_DE_TROCA, descreverConflito, detalheDoTurno, tituloDoTurno } from '../utils/descricoes.js';

const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const turnos = ref([]);
const verPassados = ref(false);

const alvo = ref(null); // turno para o qual se está pedindo substituição
const motivo = ref(MOTIVOS_DE_TROCA[0]);
const observacao = ref('');
const enviando = ref(false);

const visiveis = computed(() => turnos.value.filter((t) => verPassados.value || t.data >= hojeIso()));
const passados = computed(() => turnos.value.filter((t) => t.data < hojeIso()).length);

const porMes = computed(() => {
  const grupos = new Map();
  for (const turno of visiveis.value) {
    const mes = turno.data.slice(0, 7);
    if (!grupos.has(mes)) grupos.set(mes, []);
    grupos.get(mes).push(turno);
  }
  return [...grupos].map(([mes, itens]) => ({ mes, itens }));
});

async function carregar() {
  try {
    turnos.value = (await api.get('/minha-escala')).turnos;
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

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
      <p>Os turnos em que o líder confirmou você.</p>
    </div>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para carregar">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <div v-else-if="!visiveis.length" class="cartao pad vazio">
      <b>Nenhum turno por enquanto</b>
      <p>Seus turnos aparecem aqui depois que o líder publicar a escala.</p>
    </div>

    <template v-else>
      <template v-for="grupo in porMes" :key="grupo.mes">
        <div class="rotulo"><span>{{ mesPorExtenso(grupo.mes) }}</span></div>

        <div class="cartao lista">
          <article v-for="turno in grupo.itens" :key="turno.id" class="turno">
            <div class="data"><span>{{ diaDaSemana(turno.data) }}</span><b>{{ diaDoMes(turno.data) }}</b></div>
            <div class="principal">
              <strong>{{ tituloDoTurno(turno) }}</strong>
              <small>{{ turno.ministerio }} · {{ detalheDoTurno(turno) }}</small>

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
            </div>
          </article>
        </div>
      </template>
    </template>

    <button v-if="!carregando && passados" class="mais" @click="verPassados = !verPassados">
      {{ verPassados ? 'Esconder turnos passados' : `Ver ${passados} turno(s) passado(s)` }}
    </button>

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
.turno { display: flex; gap: 12px; padding: 13px 14px; }
.data { width: 44px; text-align: center; font-family: var(--mono); flex: none; }
.data span { display: block; font-size: 9.5px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-3); }
.data b { display: block; font-size: 18px; font-weight: 600; }
.principal { flex: 1; min-width: 0; }
.principal strong { display: block; font-size: 14.5px; font-weight: 600; }
.principal small { display: block; color: var(--ink-3); font-size: 12.5px; margin-top: 1px; }
.funcoes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.choque { margin-top: 8px; color: var(--crit); font-size: 12px; font-weight: 600; line-height: 1.4; }
.acoes { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px; }
.mais { align-self: center; color: var(--brand); font-size: 13px; font-weight: 600; padding: 8px; }
.formulario-troca { padding: 2px 16px 8px; display: flex; flex-direction: column; gap: 12px; font-size: 13px; }
</style>
