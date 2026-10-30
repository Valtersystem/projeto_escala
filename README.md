# Escala de ministérios

Sistema para a igreja montar as escalas dos ministérios: o voluntário marca em que dias pode servir, o líder confirma e publica, e o app cuida do rodízio de bandas, dos choques de horário e dos pedidos de troca.

| Pasta | O que é |
|---|---|
| [`docs/regras-de-negocio.md`](docs/regras-de-negocio.md) | As regras (RN-xx), os casos de teste (T-xx) e as decisões da v1 |
| [`api/`](api/README.md) | API em Laravel 13 (Sanctum), com 193 testes |
| [`web/`](web/README.md) | App em Vue 3 + PWA (voluntário e líder), com 154 testes |
| `index.html` | Protótipo original (uma página só, dados no navegador). Serve de referência visual e já está no ar pelo GitHub Pages |

## Subindo tudo para testar

```bash
# Terminal 1: a API, com a igreja de exemplo
cd api
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh && php artisan escala:dados-de-exemplo
php artisan serve

# Terminal 2: o app
cd web
cp .env.example .env
npm install && npm run dev      # http://localhost:5173
```

O comando de exemplo mostra os logins (Adm, líderes e voluntários). Todos usam a senha passada em `--senha` (padrão `senha-exemplo-123`, só para teste local).

## Situação

- **API:** cobre as regras do protótipo, exceto o relatório geral do Adm, o login com Google, a auditoria e o pedido de entrada do voluntário.
- **App:** telas do voluntário e do líder prontas, inclusive a equipe (pessoas, convite, bandas, postos e funções). Faltam as do Adm.
- **Em aberto com o cliente:** quais cultos o Louvor serve (D-18 no documento de regras).
