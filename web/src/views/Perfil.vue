<script setup>
import { useRouter } from 'vue-router';
import { useSessao } from '../stores/sessao.js';

const sessao = useSessao();
const roteador = useRouter();

async function sair() {
  await sessao.sair();
  await roteador.replace({ name: 'login' });
}
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <h2>Perfil</h2>
      <p>{{ sessao.pessoa?.igreja?.nome }}</p>
    </div>

    <div class="cartao lista">
      <div class="item"><span>Nome</span><b>{{ sessao.pessoa?.nome }}</b></div>
      <div class="item"><span>E-mail</span><b>{{ sessao.pessoa?.email }}</b></div>
    </div>

    <div class="rotulo"><span>Meus ministérios</span></div>
    <div v-if="!sessao.ministerios.length" class="cartao pad vazio">Você ainda não está em nenhum ministério.</div>
    <div v-else class="cartao lista">
      <div v-for="ministerio in sessao.ministerios" :key="ministerio.id" class="item">
        <b>{{ ministerio.nome }}</b>
        <span v-if="ministerio.lider" class="pilula marca"><i />Líder</span>
      </div>
    </div>

    <button class="botao perigo largo" @click="sair">Sair</button>
    <p class="nota">Esqueceu a senha? Peça um novo convite ao seu líder.</p>
  </section>
</template>

<style scoped>
.item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 14px; font-size: 14px; }
.item span:first-child { color: var(--ink-3); font-family: var(--mono); font-size: 10.5px; letter-spacing: 0.07em; text-transform: uppercase; }
.vazio { color: var(--ink-3); font-size: 13px; text-align: center; }
.nota { text-align: center; color: var(--ink-3); font-size: 12px; }
</style>
