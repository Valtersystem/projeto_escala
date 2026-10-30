<script setup>
// Link público da igreja: o voluntário pede para entrar e espera o líder aprovar.
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api/cliente.js';

const rota = useRoute();
const codigo = String(rota.params.codigo);

const carregando = ref(true);
const naoEncontrada = ref(false);
const igreja = ref(null);
const ministerios = ref([]);

const form = ref({ nome: '', email: '', telefone: '', senha: '', ministerio_id: '' });
const erro = ref('');
const enviando = ref(false);
const enviado = ref(false);

const curta = computed(() => form.value.senha !== '' && form.value.senha.length < 8);
const podeEnviar = computed(() => form.value.nome.trim() && form.value.email.trim() && form.value.senha.length >= 8 && form.value.ministerio_id && !enviando.value);

onMounted(async () => {
  try {
    const resposta = await api.get(`/entrada/${encodeURIComponent(codigo)}`);
    igreja.value = resposta.igreja;
    ministerios.value = resposta.ministerios;
    if (ministerios.value.length === 1) form.value.ministerio_id = ministerios.value[0].id;
  } catch (e) {
    naoEncontrada.value = e.status === 404;
    erro.value = e.message;
  } finally {
    carregando.value = false;
  }
});

async function enviar() {
  erro.value = '';
  enviando.value = true;

  try {
    await api.post(`/entrada/${encodeURIComponent(codigo)}`, {
      nome: form.value.nome.trim(),
      email: form.value.email.trim(),
      telefone: form.value.telefone.trim() || null,
      senha: form.value.senha,
      ministerio_id: Number(form.value.ministerio_id),
    });
    enviado.value = true;
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

      <template v-if="carregando"><p class="chamada">Carregando…</p></template>

      <template v-else-if="naoEncontrada">
        <h1>Link inválido</h1>
        <p class="chamada">Não encontramos essa igreja. Peça o link certo ao seu líder.</p>
      </template>

      <template v-else-if="enviado">
        <h1>Pedido enviado</h1>
        <p class="chamada">O líder vai avaliar. Quando aprovar, entre com o e-mail e a senha que você escolheu.</p>
        <RouterLink class="voltar" :to="{ name: 'login' }">Ir para o login</RouterLink>
      </template>

      <template v-else-if="igreja">
        <h1>{{ igreja.nome }}</h1>
        <p class="chamada">Peça para servir em um ministério. O líder aprova o seu acesso.</p>

        <form class="cartao-escuro" novalidate @submit.prevent="enviar">
          <label for="e-nome">Seu nome</label>
          <input id="e-nome" v-model="form.nome" autocomplete="name" maxlength="150" required />

          <label for="e-email">E-mail</label>
          <input id="e-email" v-model="form.email" type="email" autocomplete="email" required />

          <label for="e-tel">WhatsApp (opcional)</label>
          <input id="e-tel" v-model="form.telefone" inputmode="tel" autocomplete="tel" maxlength="32" />

          <label for="e-min">Onde quer servir</label>
          <select id="e-min" v-model="form.ministerio_id" required>
            <option value="" disabled>Escolha um ministério</option>
            <option v-for="m in ministerios" :key="m.id" :value="m.id">{{ m.nome }}</option>
          </select>

          <label for="e-senha">Escolha uma senha</label>
          <input id="e-senha" v-model="form.senha" type="password" autocomplete="new-password" minlength="8" required />
          <p v-if="curta" class="erro">Use pelo menos 8 caracteres.</p>

          <p v-if="erro" class="erro" role="alert">{{ erro }}</p>

          <button class="entrar" type="submit" :disabled="!podeEnviar">{{ enviando ? 'Enviando…' : 'Pedir para entrar' }}</button>
        </form>
      </template>

      <p v-else-if="erro" class="erro" role="alert">{{ erro }}</p>

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
.voltar { display: inline-block; margin-top: 4px; color: var(--destaque); font-size: 13px; font-weight: 600; }
select { width: 100%; min-height: 46px; padding: 0 12px; border-radius: 14px; background: var(--campo); color: var(--texto); border: 1px solid var(--linha); font-size: 15px; }
</style>
