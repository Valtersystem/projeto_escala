import { ref } from 'vue';
import { defineStore } from 'pinia';

/** A mensagem curta que aparece no pé da tela ("toast" do protótipo). */
export const useMensagens = defineStore('mensagens', () => {
  const texto = ref('');
  const tipo = ref('ok'); // ok | erro
  let relogio = null;

  function mostrar(novoTexto, novoTipo = 'ok') {
    texto.value = novoTexto;
    tipo.value = novoTipo;
    clearTimeout(relogio);
    relogio = setTimeout(() => (texto.value = ''), 3600);
  }

  const erro = (erroOuTexto) => mostrar(erroOuTexto?.message ?? String(erroOuTexto), 'erro');

  return { texto, tipo, mostrar, erro };
});
