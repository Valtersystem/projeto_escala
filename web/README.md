# App de escalas (Vue + PWA)

App do voluntário e do líder em Vue 3, instalável como PWA. Conversa com a API em [`../api`](../api/README.md). As regras estão em [`../docs/regras-de-negocio.md`](../docs/regras-de-negocio.md).

## Telas

**Voluntário**

| Tela | O que faz |
|---|---|
| Login | Entra com e-mail e senha. Esqueceu a senha: pede novo convite ao líder |
| Convite (`/convite/:token`) | Primeiro acesso: escolhe a senha pelo link que o líder mandou |
| Início | Escalas para responder, próximos turnos e avisos |
| Disponibilidade | Marca os turnos em que pode servir, vê choques e envia |
| Minha escala | Turnos confirmados, funções, choque e "Não vou poder" (pedido de substituição) |
| Perfil | Dados, ministérios e sair |

**Líder** (aparece o seletor "Voluntário | Líder" só para quem lidera um ministério)

| Tela | O que faz |
|---|---|
| Escalas | Lista os meses, abre a escala de um mês novo e mostra os pedidos de troca esperando |
| Escala | Gera turnos dos cultos (com o rodízio de bandas), cria turno avulso, edita, libera, publica (com prévia do que está torto), reabre e muda o prazo |
| Folha do turno | Quem marcou, quem está confirmado, funções de cada um, choques, carga e aumentar vaga |
| Trocas | Resolve pedidos: manter, escolher um substituto ou deixar a vaga em aberto |
| Relatório | % de vagas, turnos sem ninguém, turnos por pessoa e onde aperta |

Faltam as telas do **Adm** (ministérios, pessoas, cultos) e, para o líder, os cadastros de postos, bandas, funções e equipe. A API já tem tudo isso.

## Rodando

Precisa de Node 20+ e da API rodando (veja o README da API).

```bash
cd web
cp .env.example .env     # VITE_API_URL aponta para a API (padrão: http://localhost:8000/api)
npm install
npm run dev              # http://localhost:5173
```

Para ter dados prontos, na pasta `api`: `php artisan migrate:fresh && php artisan escala:dados-de-exemplo`. O comando lista os logins; a senha é a que você passar em `--senha` (padrão `senha-exemplo-123`, só para teste local).

## Testes e build

```bash
npm test                 # 127 testes (Vitest)
npm run build            # gera dist/ com o service worker do PWA
npm run icones           # refaz os PNGs do app a partir de public/icone.svg
```

## Como é organizado

| Pasta | Conteúdo |
|---|---|
| `src/api/cliente.js` | Fala com a API: token no cabeçalho, erros em português, 401 derruba a sessão |
| `src/stores/` | Sessão (login, token guardado, sair) e mensagens curtas na tela |
| `src/router.js` | Rotas e proteção: sem login vai para o login e volta depois |
| `src/views/` | Uma tela por arquivo |
| `src/components/` | Casca com abas, folha que sobe de baixo, alertas, ícones |
| `src/styles/base.css` | Cores do protótipo, em claro e escuro |
| `tests/` | `ajudantes.js` simula a API; um arquivo de testes por assunto |

## PWA

- A casca do app (telas, estilos, ícones) fica em cache e abre sem internet.
- As chamadas à API **nunca** vão para o cache: escala desatualizada engana. Sem internet, o app avisa em vez de mostrar dado velho.
- Para instalar no celular: abrir o endereço no navegador e usar "Adicionar à tela inicial".

## Observações

- O token fica em `localStorage`. Em janela privada o app funciona, mas não lembra o login.
- O rodapé "Um produto SEVENFAITHTECH" da tela de login segue o padrão dos outros produtos; é só trocar o texto em `Login.vue` e `Convite.vue`.
- `npm audit` aponta falhas só em ferramentas de desenvolvimento (Vitest e dependências); o código que vai para o celular tem 0 vulnerabilidades conhecidas. A atualização do Vitest ficou pendente porque o npm travou ao instalar a versão nova.
