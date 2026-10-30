<script setup>
// Link recebido por e-mail: escolhe a nova senha.
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/cliente.js';

const rota = useRoute();

const senha = ref('');
const confirmacao = ref('');
const erro = ref('');
const enviando = ref(false);
const pronto = ref(false);

const email = typeof rota.query.email === 'string' ? rota.query.email : '';

const diferentes = computed(() => confirmacao.value !== '' && senha.value !== confirmacao.value);
const curta = computed(() => senha.value !== '' && senha.value.length < 8);
const podeEnviar = computed(() => senha.value.length >= 8 && senha.value === confirmacao.value && !enviando.value);

async function redefinir() {
  erro.value = '';
  enviando.value = true;

  try {
    await api.post('/redefinir-senha', { email, token: String(rota.params.token), senha: senha.value });
    pronto.value = true;
  } catch (e) {
    erro.value = e.message;
  } finally {
    enviando.value = false;
  }
}
</script>

<template>
  <div class="entrada">
    <div class="miolo">
      <div class="marca" aria-hidden="true">E</div>
      <h1>Nova senha</h1>

      <template v-if="pronto">
        <p class="chamada">Senha alterada. Entre com a nova senha.</p>
        <RouterLink class="voltar" :to="{ name: 'login' }">Ir para o login</RouterLink>
      </template>

      <template v-else>
        <p class="chamada">Escolha uma senha nova para {{ email || 'a sua conta' }}.</p>
        <form class="cartao-escuro" novalidate @submit.prevent="redefinir">
          <label for="senha">Nova senha</label>
          <input id="senha" v-model="senha" type="password" autocomplete="new-password" minlength="8" required />
          <p v-if="curta" class="erro">Use pelo menos 8 caracteres.</p>

          <label for="confirmacao">Repita a senha</label>
          <input id="confirmacao" v-model="confirmacao" type="password" autocomplete="new-password" required />
          <p v-if="diferentes" class="erro">As senhas não são iguais.</p>

          <p v-if="erro" class="erro" role="alert">{{ erro }}</p>

          <button class="entrar" type="submit" :disabled="!podeEnviar">{{ enviando ? 'Salvando…' : 'Salvar nova senha' }}</button>
          <p class="esqueceu">Link vencido? <RouterLink :to="{ name: 'esqueci-senha' }">Peça outro</RouterLink></p>
        </form>
      </template>

      <p class="assinatura">Um produto <b>SEVENFAITHTECH</b></p>
    </div>
  </div>
</template>

<style scoped>
.entrada {
  --fundo: #2a2c32; --cartao: #23252b; --campo: #1c1e23; --linha: rgba(255, 255, 255, 0.07);
  --texto: #ececf1; --mudo: #9a9ca6; --destaque: #b7a8f6; --sobre-destaque: #17122b;
  min-height: 100dvh; display: grid; place-items: center; padding: 28px 18px; color: var(--texto);
  background: radial-gradient(120% 90% at 50% 0%, #3a3c44 0%, var(--fundo) 55%, #212329 100%);
  color-scheme: dark;
}
.miolo { width: 100%; max-width: 360px; text-align: center; }
.marca {
  width: 62px; height: 62px; margin: 0 auto 14px; border-radius: 19px; background: var(--destaque); color: var(--sobre-destaque);
  display: grid; place-items: center; font-size: 30px; font-weight: 800; box-shadow: 0 0 60px -6px rgba(183, 168, 246, 0.35);
}
h1 { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; }
.chamada { margin: 8px auto 22px; color: var(--mudo); font-size: 13px; max-width: 30ch; }
.cartao-escuro { text-align: left; background: var(--cartao); border: 1px solid var(--linha); border-radius: 22px; padding: 18px 20px 20px; box-shadow: 0 24px 60px -30px rgba(0, 0, 0, 0.8); }
label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 6px; }
input { width: 100%; min-height: 46px; padding: 0 14px; border-radius: 14px; background: var(--campo); color: var(--texto); border: 1px solid var(--linha); font-size: 15px; }
input:focus { outline: 2px solid var(--destaque); outline-offset: 1px; }
.erro { margin: 8px 0 0; color: #f6a198; font-size: 13px; }
.entrar {
  width: 100%; min-height: 46px; margin-top: 18px; border-radius: 14px; background: var(--destaque); color: var(--sobre-destaque);
  font-size: 12px; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; text-align: center;
}
.entrar[disabled] { opacity: 0.5; }
.esqueceu { margin: 14px 0 0; text-align: center; color: var(--mudo); font-size: 12px; }
.assinatura { margin-top: 22px; color: var(--mudo); font-size: 10.5px; letter-spacing: 0.12em; text-transform: uppercase; }
.assinatura b { color: var(--destaque); font-weight: 600; }
.voltar, .esqueceu a { color: var(--destaque); font-size: 13px; font-weight: 600; text-decoration: none; }
</style>
