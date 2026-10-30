import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { api, configurarCliente } from '../api/cliente.js';

const CHAVE = 'escala.token';

function lerDoArmazenamento() {
  try {
    return localStorage.getItem(CHAVE);
  } catch {
    return null; // armazenamento bloqueado (janela privada): o app funciona, mas não lembra o login
  }
}

function gravarNoArmazenamento(token) {
  try {
    if (token) localStorage.setItem(CHAVE, token);
    else localStorage.removeItem(CHAVE);
  } catch {
    /* sem armazenamento: a sessão vale só enquanto o app estiver aberto */
  }
}

export const useSessao = defineStore('sessao', () => {
  const token = ref(lerDoArmazenamento());
  const pessoa = ref(null);

  const autenticada = computed(() => !!token.value);
  const nomeCurto = computed(() => (pessoa.value?.nome ?? '').split(' ')[0]);
  const iniciais = computed(() => {
    const partes = (pessoa.value?.nome ?? '?').trim().split(/\s+/);
    return ((partes[0]?.[0] ?? '') + (partes[1]?.[0] ?? '')).toUpperCase();
  });
  const ministerios = computed(() => pessoa.value?.ministerios ?? []);

  function definir(novoToken, novaPessoa) {
    token.value = novoToken;
    pessoa.value = novaPessoa;
    gravarNoArmazenamento(novoToken);
  }

  /** Apaga a sessão só no aparelho (sem chamar a API). */
  function limpar() {
    token.value = null;
    pessoa.value = null;
    gravarNoArmazenamento(null);
  }

  async function entrar(email, senha) {
    const dados = await api.post('/login', { email, senha });
    definir(dados.token, dados.pessoa);
  }

  /** Login com Google: a API confere a credencial e só deixa entrar quem já está cadastrado. */
  async function entrarComGoogle(credencial) {
    const dados = await api.post('/login/google', { credencial });
    definir(dados.token, dados.pessoa);
  }

  /** Quem aceitou um convite já entra logado. */
  async function aceitarConvite(tokenDoConvite, senha) {
    const dados = await api.post('/convites/aceitar', { token: tokenDoConvite, senha });
    definir(dados.token, dados.pessoa);
  }

  /** Confere o token guardado e traz os dados da pessoa. Token vencido derruba a sessão. */
  async function carregar() {
    if (!token.value) return;

    try {
      pessoa.value = (await api.get('/eu')).pessoa;
    } catch (erro) {
      if (erro.status === 401 || erro.status === 403) limpar();
      else throw erro;
    }
  }

  async function sair() {
    try {
      await api.post('/logout');
    } catch {
      /* mesmo sem rede, sair no aparelho tem que funcionar */
    }
    limpar();
  }

  configurarCliente({ token: () => token.value, naoAutorizado: limpar });

  return { token, pessoa, autenticada, nomeCurto, iniciais, ministerios, entrar, entrarComGoogle, aceitarConvite, carregar, sair, limpar };
});
