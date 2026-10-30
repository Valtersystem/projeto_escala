<script setup>
import { computed, ref, watch } from 'vue';
import Folha from './Folha.vue';
import { api } from '../api/cliente.js';
import { useMensagens } from '../stores/mensagens.js';

const props = defineProps({
  aberta: Boolean,
  escala: { type: Object, required: true },
  turno: { type: Object, default: null }, // sem turno = criar um avulso
});
const emit = defineEmits(['fechar', 'salvo']);

const mensagens = useMensagens();

const postos = ref([]);
const bandas = ref([]);
const form = ref({ posto_id: '', banda_id: '', data: '', inicio: '08:00', fim: '11:30', vagas: '' });
const enviando = ref(false);

const edicao = computed(() => !!props.turno);

async function abrir() {
  try {
    const [p, b] = await Promise.all([
      api.get(`/ministerios/${props.escala.ministerio_id}/postos`),
      api.get(`/ministerios/${props.escala.ministerio_id}/bandas`),
    ]);
    postos.value = p.postos;
    bandas.value = b.bandas;
  } catch (e) {
    mensagens.erro(e);
  }

  const t = props.turno;
  form.value = t
    ? { posto_id: t.posto?.id ?? '', banda_id: t.banda?.id ?? '', data: t.data, inicio: t.inicio, fim: t.fim, vagas: t.vagas }
    : { posto_id: postos.value[0]?.id ?? '', banda_id: '', data: `${props.escala.mes}-01`, inicio: '08:00', fim: '11:30', vagas: postos.value[0]?.vagas ?? '' };
}

async function salvar() {
  enviando.value = true;
  const corpo = {
    posto_id: Number(form.value.posto_id),
    banda_id: form.value.banda_id ? Number(form.value.banda_id) : null,
    data: form.value.data,
    inicio: form.value.inicio,
    fim: form.value.fim,
    vagas: form.value.vagas === '' ? undefined : Number(form.value.vagas),
  };

  try {
    if (edicao.value) await api.patch(`/turnos/${props.turno.id}`, { ...corpo, versao: props.turno.versao });
    else await api.post(`/escalas/${props.escala.id}/turnos`, corpo);

    mensagens.mostrar(edicao.value ? 'Turno atualizado.' : 'Turno adicionado.');
    emit('salvo');
  } catch (e) {
    mensagens.erro(e);
    // 409: alguém mexeu antes; a tela de trás recarrega para mostrar o que mudou.
    if (e.status === 409) emit('salvo');
  } finally {
    enviando.value = false;
  }
}

async function excluir() {
  enviando.value = true;
  try {
    await api.delete(`/turnos/${props.turno.id}`);
    mensagens.mostrar('Turno excluído.');
    emit('salvo');
  } catch (e) {
    mensagens.erro(e);
  } finally {
    enviando.value = false;
  }
}

watch(() => props.aberta, (aberta) => aberta && abrir());
</script>

<template>
  <Folha
    :aberta="aberta" :titulo="edicao ? 'Editar turno' : 'Turno avulso'"
    :legenda="edicao ? 'Enquanto a escala é rascunho, tudo pode mudar.' : 'Um dia de serviço fora da grade de cultos.'" @fechar="emit('fechar')"
  >
    <div class="miolo">
      <div class="formulario">
        <div class="campo">
          <label for="posto">Posto</label>
          <select id="posto" v-model="form.posto_id"><option v-for="p in postos" :key="p.id" :value="p.id">{{ p.nome }}</option></select>
        </div>
        <div v-if="bandas.length" class="campo">
          <label for="banda">Banda</label>
          <select id="banda" v-model="form.banda_id">
            <option value="">— sem banda —</option>
            <option v-for="b in bandas" :key="b.id" :value="b.id">{{ b.nome }}</option>
          </select>
        </div>
        <div class="campo"><label for="data">Data</label><input id="data" v-model="form.data" type="date" /></div>
        <div class="duplo">
          <div class="campo"><label for="inicio">Início</label><input id="inicio" v-model="form.inicio" type="time" /></div>
          <div class="campo"><label for="fim">Fim</label><input id="fim" v-model="form.fim" type="time" /></div>
        </div>
        <div class="campo"><label for="vagas">Vagas</label><input id="vagas" v-model="form.vagas" type="number" min="1" /></div>
      </div>
    </div>

    <template #rodape>
      <div class="acoes">
        <button v-if="edicao" class="botao perigo" :disabled="enviando" @click="excluir">Excluir</button>
        <button class="botao primario" :disabled="enviando || !form.posto_id || !form.data" @click="salvar">{{ enviando ? 'Salvando…' : 'Salvar' }}</button>
      </div>
    </template>
  </Folha>
</template>

<style scoped>
.miolo { padding: 2px 16px 8px; }
.duplo { display: grid; grid-template-columns: 1fr 1fr; }
.duplo .campo:first-child { border-right: 1px solid var(--line); }
.acoes { display: flex; gap: 9px; }
.acoes .primario { flex: 1; }
</style>
