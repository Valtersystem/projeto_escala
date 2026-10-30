// Cliente da API: fala JSON, manda o token e traduz erro em mensagem para a tela.

const BASE = (import.meta.env?.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '');

export class ErroDaApi extends Error {
  constructor(status, mensagem, erros = {}) {
    super(mensagem);
    this.name = 'ErroDaApi';
    this.status = status;
    /** Erros de validação por campo: { campo: ['mensagem'] } */
    this.erros = erros;
  }

  /** A primeira mensagem de erro de um campo, se houver. */
  doCampo(campo) {
    return this.erros?.[campo]?.[0] ?? null;
  }
}

let lerToken = () => null;
let aoNaoAutorizado = () => {};

/** A sessão registra aqui como ler o token e o que fazer quando a API responde 401. */
export function configurarCliente({ token, naoAutorizado }) {
  if (token) lerToken = token;
  if (naoAutorizado) aoNaoAutorizado = naoAutorizado;
}

export async function chamar(metodo, caminho, corpo) {
  const token = lerToken();

  let resposta;
  try {
    resposta = await fetch(`${BASE}${caminho}`, {
      method: metodo,
      headers: {
        Accept: 'application/json',
        ...(corpo !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: corpo !== undefined ? JSON.stringify(corpo) : undefined,
    });
  } catch {
    throw new ErroDaApi(0, 'Sem conexão com o servidor. Confira sua internet e tente de novo.');
  }

  if (resposta.status === 204) return null;

  const dados = await resposta.json().catch(() => null);

  if (!resposta.ok) {
    // Token inválido ou vencido: a sessão acaba. (Login errado também é 422, não 401.)
    if (resposta.status === 401) aoNaoAutorizado();

    const primeiroErro = dados?.errors ? Object.values(dados.errors)[0]?.[0] : null;
    throw new ErroDaApi(
      resposta.status,
      primeiroErro ?? dados?.message ?? 'Não foi possível completar a ação.',
      dados?.errors ?? {},
    );
  }

  return dados;
}

export const api = {
  get: (caminho) => chamar('GET', caminho),
  post: (caminho, corpo = {}) => chamar('POST', caminho, corpo),
  put: (caminho, corpo = {}) => chamar('PUT', caminho, corpo),
  patch: (caminho, corpo = {}) => chamar('PATCH', caminho, corpo),
  delete: (caminho) => chamar('DELETE', caminho),
};
