import { createRouter, createWebHistory } from 'vue-router';
import Casca from './components/Casca.vue';
import { useModo } from './stores/modo.js';
import { useSessao } from './stores/sessao.js';

export const rotas = [
  { path: '/login', name: 'login', component: () => import('./views/Login.vue'), meta: { publica: true } },
  { path: '/convite/:token', name: 'convite', component: () => import('./views/Convite.vue'), meta: { publica: true } },
  { path: '/esqueci-senha', name: 'esqueci-senha', component: () => import('./views/EsqueciSenha.vue'), meta: { publica: true } },
  { path: '/redefinir-senha/:token', name: 'redefinir-senha', component: () => import('./views/RedefinirSenha.vue'), meta: { publica: true } },
  { path: '/entrar/:codigo', name: 'entrar', component: () => import('./views/Entrar.vue'), meta: { publica: true } },
  {
    path: '/',
    component: Casca,
    children: [
      { path: '', name: 'inicio', component: () => import('./views/Inicio.vue'), meta: { modo: 'voluntario' } },
      { path: 'disponibilidade', name: 'disponibilidade', component: () => import('./views/Disponibilidade.vue'), meta: { modo: 'voluntario' } },
      { path: 'disponibilidade/:escalaId', name: 'disponibilidade-escala', component: () => import('./views/DisponibilidadeEscala.vue'), props: true, meta: { modo: 'voluntario' } },
      { path: 'minha-escala', name: 'minha-escala', component: () => import('./views/MinhaEscala.vue'), meta: { modo: 'voluntario' } },
      { path: 'perfil', name: 'perfil', component: () => import('./views/Perfil.vue') },

      // Área do líder: só abre para quem lidera algum ministério.
      { path: 'lider', name: 'lider-escalas', component: () => import('./views/LiderEscalas.vue'), meta: { modo: 'lider' } },
      { path: 'lider/escalas/:escalaId', name: 'lider-escala', component: () => import('./views/LiderEscala.vue'), props: true, meta: { modo: 'lider' } },
      { path: 'lider/equipe', name: 'lider-equipe', component: () => import('./views/LiderEquipe.vue'), meta: { modo: 'lider' } },
      // Área do Adm: ministérios, cultos e pessoas da igreja.
      { path: 'adm/ministerios', name: 'adm-ministerios', component: () => import('./views/AdmMinisterios.vue'), meta: { modo: 'adm' } },
      { path: 'adm/cultos', name: 'adm-cultos', component: () => import('./views/AdmCultos.vue'), meta: { modo: 'adm' } },
      { path: 'adm/pessoas', name: 'adm-pessoas', component: () => import('./views/AdmPessoas.vue'), meta: { modo: 'adm' } },
      { path: 'adm/relatorio', name: 'adm-relatorio', component: () => import('./views/AdmRelatorio.vue'), meta: { modo: 'adm' } },
      { path: 'adm/historico', name: 'adm-historico', component: () => import('./views/AdmHistorico.vue'), meta: { modo: 'adm' } },
      { path: 'lider/trocas', name: 'lider-trocas', component: () => import('./views/LiderTrocas.vue'), meta: { modo: 'lider' } },
      { path: 'lider/relatorio', name: 'lider-relatorio', component: () => import('./views/LiderRelatorio.vue'), meta: { modo: 'lider' } },
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

    // O Adm que não serve em nenhum ministério não tem o que fazer na área do voluntário.
    if (para.name === 'inicio' && sessao.pessoa?.adm && !sessao.ministerios.length) return { name: 'adm-ministerios' };

    // A área do líder só abre para quem lidera algum ministério; nas telas de cada modo, o modo acompanha a tela.
    if (para.meta.modo) {
      const modo = useModo();
      if (para.meta.modo === 'lider' && !modo.ehLider) return { name: 'inicio' };
      if (para.meta.modo === 'adm' && !modo.ehAdm) return { name: 'inicio' };
      modo.usar(para.meta.modo);
    }
  });

  return roteador;
}
