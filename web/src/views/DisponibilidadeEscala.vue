<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import Alerta from '../components/Alerta.vue';
import Folha from '../components/Folha.vue';
import Icone from '../components/Icone.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';
import { dataCurta, diaDaSemana, mesPorExtenso } from '../utils/datas.js';
import { descreverConflito } from '../utils/descricoes.js';

const props = defineProps({ escalaId: { type: String, required: true } });

const roteador = useRouter();
const mensagens = useMensagens();

const carregando = ref(true);
const erro = ref('');
const dados = ref(null);
const ocupado = ref(false);
const resumo = ref(null); // preenchido depois de enviar

const turnos = computed(() => dados.value?.turnos ?? []);
const encerrado = computed(() => dados.value?.escala.prazo_encerrado ?? false);
const totalMarcados = computed(() => turnos.value.filter((t) => t.marquei).length);
const comChoque = computed(() => turnos.value.filter((t) => t.marquei && t.choques.length).length);

async function carregar() {
  try {
    dados.value = await api.get(`/escalas/${props.escalaId}/disponibilidade`);
    erro.value = '';
  } catch (e) {
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
}

async function alternar(turno) {
  if (ocupado.value || encerrado.value) return;
  ocupado.value = true;

  try {
    const resposta = await api.put(`/turnos/${turno.id}/disponibilidade`, { marcado: !turno.marquei });

    if (resposta.marcado && resposta.choques.length) {
      mensagens.mostrar('Atenção: esse horário choca com outra escala sua.', 'erro');
    }

    // Recarrega: marcar um turno muda o aviso de choque dos outros.
    await carregar();
  } catch (e) {
    mensagens.erro(e);
    await carregar();
  } finally {
    ocupado.value = false;
  }
}

async function enviar() {
  if (ocupado.value) return;
  ocupado.value = true;

  try {
    resumo.value = await api.post(`/escalas/${props.escalaId}/disponibilidade/enviar`);
  } catch (e) {
    mensagens.erro(e);
  } finally {
    ocupado.value = false;
  }
}

async function terminar() {
  resumo.value = null;
  await roteador.push({ name: 'inicio' });
}

onMounted(carregar);
</script>

<template>
  <section class="tela">
    <RouterLink class="voltar" :to="{ name: 'disponibilidade' }"><Icone nome="voltar" :tamanho="16" />Disponibilidade</RouterLink>

    <Alerta v-if="erro" tipo="critico" titulo="Não deu para abrir esta escala">
      {{ erro }} <button class="repetir" @click="carregar">Tentar de novo</button>
    </Alerta>
    <p v-else-if="carregando" class="vazio">Carregando…</p>

    <template v-else>
      <div class="saudacao">
        <h2>{{ mesPorExtenso(dados.escala.mes) }}</h2>
        <p v-if="dados.escala.prazo">Responder até {{ dataCurta(dados.escala.prazo) }}</p>
      </div>

      <Alerta v-if="encerrado" tipo="aviso" titulo="O prazo terminou">
        Não dá mais para mudar suas respostas. Se precisar, fale com o seu líder.
      </Alerta>

      <div v-if="!turnos.length" class="cartao pad vazio"><b>Sem turnos ainda</b><p>O líder ainda não adicionou os turnos desta escala.</p></div>

      <div v-else class="cartao lista">
        <button
          v-for="turno in turnos" :key="turno.id" class="turno" :aria-pressed="turno.marquei"
          :disabled="ocupado || encerrado" @click="alternar(turno)"
        >
          <span class="caixa"><Icone nome="certo" :tamanho="14" /></span>
          <span class="texto">
            <strong>{{ diaDaSemana(turno.data) }}, {{ dataCurta(turno.data) }}<template v-if="turno.culto"> · {{ turno.culto }}</template></strong>
            <small>
              {{ turno.inicio }} às {{ turno.fim }} · {{ turno.posto?.nome }}<template v-if="turno.banda"> · {{ turno.banda.nome }}</template>
            </small>
            <span v-if="turno.choques.length" class="choque">⚠ Choca com {{ descreverConflito(turno.choques[0]) }}</span>
            <span v-else-if="turno.mesmo_dia.length" class="mesmo-dia">Mesmo dia de {{ descreverConflito(turno.mesmo_dia[0]) }}</span>
          </span>
          <span v-if="!turno.do_meu_perfil" class="pilula mudo">fora do seu perfil</span>
          <span v-if="turno.choques.length" class="pilula critico"><i />Choca</span>
        </button>
      </div>

      <div class="rodape">
        <div class="contagem">
          <span>{{ totalMarcados ? `${totalMarcados} turno(s) marcado(s)` : 'Nenhum turno marcado ainda' }}</span>
          <span v-if="comChoque" class="choque">{{ comChoque }} com choque</span>
        </div>
        <button class="botao primario largo" :disabled="ocupado || encerrado || !totalMarcados" @click="enviar">Enviar disponibilidade</button>
      </div>
    </template>

    <Folha :aberta="!!resumo" titulo="Disponibilidade enviada" legenda="O líder recebe agora e monta a escala." @fechar="terminar">
      <div v-if="resumo" class="resumo">
        <p>Você marcou <b>{{ resumo.total }} turno(s)</b>. Isso ainda não é escala: o líder confirma quem fica em cada vaga.</p>
        <Alerta v-if="resumo.com_choque" tipo="critico" titulo="Atenção">
          {{ resumo.com_choque }} turno(s) chocam com outro horário seu. O líder vê esse aviso antes de confirmar.
        </Alerta>
        <Alerta tipo="info" icone="calendario">Dá para mudar sua resposta até o prazo.</Alerta>
      </div>
      <template #rodape><button class="botao primario largo" @click="terminar">Entendi</button></template>
    </Folha>
  </section>
</template>

<style scoped>
.voltar { display: inline-flex; align-items: center; gap: 6px; color: var(--brand); font-size: 13.5px; font-weight: 600; margin: 0 4px; text-decoration: none; }
.vazio { color: var(--ink-3); font-size: 13px; text-align: center; }
.vazio b { display: block; color: var(--ink); margin-bottom: 4px; font-size: 14px; }
.repetir { color: inherit; font-weight: 700; text-decoration: underline; }
.turno { display: flex; align-items: flex-start; gap: 11px; padding: 12px 13px; width: 100%; }
.turno[disabled] { opacity: 0.6; }
.caixa {
  width: 24px; height: 24px; border-radius: 7px; border: 2px solid var(--line-2); flex: none; display: grid; place-items: center;
  color: transparent; margin-top: 1px; transition: background 0.14s, border-color 0.14s;
}
.turno[aria-pressed='true'] .caixa { background: var(--brand); border-color: var(--brand); color: var(--on-brand); }
.texto { flex: 1; min-width: 0; }
.texto strong { display: block; font-size: 13.5px; font-weight: 600; }
.texto small { display: block; font-size: 12px; color: var(--ink-3); margin-top: 1px; }
.choque { display: block; margin-top: 6px; font-size: 11.5px; color: var(--crit); font-weight: 600; line-height: 1.35; }
.mesmo-dia { display: block; margin-top: 6px; font-size: 11.5px; color: var(--warn); line-height: 1.35; }
.rodape {
  position: sticky; bottom: -28px; margin: 6px -14px -28px; padding: 12px 14px calc(14px + env(safe-area-inset-bottom));
  background: var(--surface); border-top: 1px solid var(--line); box-shadow: var(--shadow-3); z-index: 5;
}
.contagem { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; color: var(--ink-3); margin-bottom: 9px; }
.contagem .choque { margin: 0; }
.resumo { padding: 2px 18px 12px; display: flex; flex-direction: column; gap: 10px; font-size: 13.5px; color: var(--ink-2); }
</style>
