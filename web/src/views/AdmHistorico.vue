<script setup>
import HistoricoLista from '../components/HistoricoLista.vue';
</script>

<template>
  <section class="tela">
    <div class="saudacao">
      <RouterLink class="voltar" :to="{ name: 'adm-relatorio' }">‹ Relatório</RouterLink>
      <h2>Histórico de alterações</h2>
      <p>Quem mudou o quê na igreja, do mais novo para o mais antigo</p>
    </div>
    <HistoricoLista />
  </section>
</template>

<style scoped>
section { display: flex; flex-direction: column; gap: 12px; }
.voltar { color: var(--brand); font-size: 13px; font-weight: 600; text-decoration: none; }
</style>
