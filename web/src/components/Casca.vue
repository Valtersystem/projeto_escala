<script setup>
import { computed, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import Icone from './Icone.vue';
import { useModo } from '../stores/modo.js';
import { useSessao } from '../stores/sessao.js';

const sessao = useSessao();
const modo = useModo();
const rota = useRoute();
const roteador = useRouter();

const ABAS = {
  voluntario: [
    { nome: 'inicio', texto: 'Início', icone: 'inicio' },
    { nome: 'disponibilidade', texto: 'Disponibilidade', icone: 'calendario' },
    { nome: 'minha-escala', texto: 'Minha escala', icone: 'certo' },
    { nome: 'perfil', texto: 'Perfil', icone: 'pessoa' },
  ],
  lider: [
    { nome: 'lider-escalas', texto: 'Escalas', icone: 'calendario' },
    { nome: 'lider-equipe', texto: 'Equipe', icone: 'equipe' },
    { nome: 'lider-trocas', texto: 'Trocas', icone: 'troca', selo: true },
    { nome: 'lider-relatorio', texto: 'Relatório', icone: 'grafico' },
    { nome: 'perfil', texto: 'Perfil', icone: 'pessoa' },
  ],
};

const abas = computed(() => ABAS[modo.modo]);

// Telas de dentro de uma escala continuam marcando a aba da lista.
const ATIVA = { 'disponibilidade-escala': 'disponibilidade', 'lider-escala': 'lider-escalas' };
const ativa = computed(() => ATIVA[rota.name] ?? rota.name);

async function trocarModo(novo) {
  if (modo.modo === novo) return;
  modo.usar(novo);
  await roteador.push({ name: novo === 'lider' ? 'lider-escalas' : 'inicio' });
}

// O número de pedidos de troca acompanha o ministério em uso.
watch(
  () => [modo.modo, modo.ministerio?.id],
  ([atual]) => atual === 'lider' && modo.atualizarTrocas(),
  { immediate: true },
);
</script>

<template>
  <div class="casca">
    <header class="topo">
      <div class="marca">E</div>
      <div class="identidade">
        <h1>Escala</h1>
        <div class="sub">{{ sessao.pessoa?.igreja?.nome }}</div>
      </div>
      <div class="avatar" :title="sessao.pessoa?.nome">{{ sessao.iniciais }}</div>
    </header>

    <div v-if="modo.ehLider" class="modos" role="tablist" aria-label="Como você quer usar o app">
      <button role="tab" :aria-selected="modo.modo === 'voluntario'" @click="trocarModo('voluntario')">Voluntário</button>
      <button role="tab" :aria-selected="modo.modo === 'lider'" @click="trocarModo('lider')">Líder</button>
    </div>

    <main><RouterView /></main>

    <nav class="abas" aria-label="Seções">
      <RouterLink v-for="aba in abas" :key="aba.nome" :to="{ name: aba.nome }" :aria-current="ativa === aba.nome ? 'page' : undefined">
        <Icone :nome="aba.icone" />
        <span>{{ aba.texto }}</span>
        <i v-if="aba.selo && modo.trocasAbertas" class="selo" :aria-label="`${modo.trocasAbertas} pedidos`">{{ modo.trocasAbertas }}</i>
      </RouterLink>
    </nav>
  </div>
</template>

<style scoped>
.casca { max-width: 480px; margin: 0 auto; height: 100dvh; display: flex; flex-direction: column; background: var(--bg); }
.topo {
  display: flex; align-items: center; gap: 12px; padding: 14px 18px 10px; padding-top: calc(14px + env(safe-area-inset-top));
  background: var(--surface); border-bottom: 1px solid var(--line);
}
.marca {
  width: 34px; height: 34px; border-radius: 11px; background: var(--brand); color: var(--on-brand);
  display: grid; place-items: center; font-weight: 800; font-size: 17px; flex: none;
}
.identidade { flex: 1; min-width: 0; }
h1 { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }
.sub { font-family: var(--mono); font-size: 11px; color: var(--ink-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.avatar {
  width: 34px; height: 34px; border-radius: 50%; background: var(--surface-2); border: 1px solid var(--line);
  display: grid; place-items: center; font-size: 12.5px; font-weight: 600; color: var(--ink-2); flex: none;
}
.modos { display: flex; gap: 4px; background: var(--surface-2); border-radius: 11px; padding: 3px; margin: 8px 14px 0; flex: none; }
.modos button { flex: 1; min-height: 34px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--ink-2); text-align: center; }
.modos button[aria-selected='true'] { background: var(--surface); color: var(--brand); box-shadow: var(--shadow-1); }
main { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 16px 14px 28px; }
.abas { display: flex; background: var(--surface); border-top: 1px solid var(--line); padding-bottom: env(safe-area-inset-bottom); }
.abas a {
  flex: 1; min-height: 56px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
  font-size: 10.5px; font-weight: 600; color: var(--ink-3); text-decoration: none; text-align: center; position: relative;
}
.abas a[aria-current='page'] { color: var(--brand); }
.selo {
  position: absolute; top: 7px; left: calc(50% + 6px); min-width: 16px; height: 16px; border-radius: 8px; padding: 0 4px;
  background: var(--crit); color: #fff; font-size: 9.5px; font-style: normal; font-weight: 700; display: grid; place-items: center;
}
</style>
