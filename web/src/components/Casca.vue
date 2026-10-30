<script setup>
import { computed } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import Icone from './Icone.vue';
import { useSessao } from '../stores/sessao.js';

const sessao = useSessao();
const rota = useRoute();

const abas = [
  { nome: 'inicio', texto: 'Início', icone: 'inicio' },
  { nome: 'disponibilidade', texto: 'Disponibilidade', icone: 'calendario' },
  { nome: 'minha-escala', texto: 'Minha escala', icone: 'certo' },
  { nome: 'perfil', texto: 'Perfil', icone: 'pessoa' },
];

// A aba "Disponibilidade" continua marcada quando a pessoa está dentro de uma escala.
const ativa = computed(() => (rota.name === 'disponibilidade-escala' ? 'disponibilidade' : rota.name));
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

    <main><RouterView /></main>

    <nav class="abas" aria-label="Seções">
      <RouterLink v-for="aba in abas" :key="aba.nome" :to="{ name: aba.nome }" :aria-current="ativa === aba.nome ? 'page' : undefined">
        <Icone :nome="aba.icone" />
        <span>{{ aba.texto }}</span>
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
main { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 16px 14px 28px; }
.abas { display: flex; background: var(--surface); border-top: 1px solid var(--line); padding-bottom: env(safe-area-inset-bottom); }
.abas a {
  flex: 1; min-height: 56px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
  font-size: 10.5px; font-weight: 600; color: var(--ink-3); text-decoration: none; text-align: center;
}
.abas a[aria-current='page'] { color: var(--brand); }
</style>
