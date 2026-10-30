import { createRouter, createWebHistory } from 'vue-router';
import Casca from './components/Casca.vue';
import { useSessao } from './stores/sessao.js';

export const rotas = [
  { path: '/login', name: 'login', component: () => import('./views/Login.vue'), meta: { publica: true } },
  { path: '/convite/:token', name: 'convite', component: () => import('./views/Convite.vue'), meta: { publica: true } },
  {
    path: '/',
    component: Casca,
    children: [
      { path: '', name: 'inicio', component: () => import('./views/Inicio.vue') },
      { path: 'disponibilidade', name: 'disponibilidade', component: () => import('./views/Disponibilidade.vue') },
      { path: 'disponibilidade/:escalaId', name: 'disponibilidade-escala', component: () => import('./views/DisponibilidadeEscala.vue'), props: true },
      { path: 'minha-escala', name: 'minha-escala', component: () => import('./views/MinhaEscala.vue') },
      { path: 'perfil', name: 'perfil', component: () => import('./views/Perfil.vue') },
    ],
  },
  { path: '/:resto(.*)*', redirect: '/' },
];

export function criarRoteador(historia = createWebHistory()) {
  const roteador = createRouter({ history: historia, routes: rotas });

  roteador.beforeEach(async (para) => {
    const sessao = useSessao();

    // Quem já tem token: confere com a API e traz os dados da pessoa antes de mostrar qualquer tela.
    if (sessao.autenticada && !sessao.pessoa) {
      try {
        await sessao.carregar();
      } catch {
        /* sem rede: segue com o token; as telas mostram o erro de conexão */
      }
    }

    if (!para.meta.publica && !sessao.autenticada) {
      return { name: 'login', query: para.fullPath !== '/' ? { volta: para.fullPath } : {} };
    }

    if (para.name === 'login' && sessao.autenticada) return { name: 'inicio' };
  });

  return roteador;
}
