import { createApp, watch } from 'vue';
import { createPinia } from 'pinia';
import { registerSW } from 'virtual:pwa-register';
import App from './App.vue';
import { criarRoteador } from './router.js';
import { useSessao } from './stores/sessao.js';
import './styles/base.css';

const app = createApp(App);
app.use(createPinia());

const roteador = criarRoteador();
app.use(roteador);

// Token vencido ou revogado (ou "sair"): volta para o login e lembra a tela em que a pessoa estava.
const sessao = useSessao();
watch(
  () => sessao.autenticada,
  (autenticada) => {
    const atual = roteador.currentRoute.value;
    if (!autenticada && !atual.meta.publica) {
      roteador.push({ name: 'login', query: atual.fullPath !== '/' ? { volta: atual.fullPath } : {} });
    }
  },
);

registerSW({ immediate: true });

app.mount('#app');
