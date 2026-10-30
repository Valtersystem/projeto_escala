# Escala de ministérios

Sistema para a igreja montar as escalas dos ministérios: o voluntário marca em que dias pode servir, o líder confirma e publica, e o app cuida do rodízio de bandas, dos choques de horário e dos pedidos de troca.

| Pasta | O que é |
|---|---|
| [`docs/regras-de-negocio.md`](docs/regras-de-negocio.md) | As regras (RN-xx), os casos de teste (T-xx) e as decisões da v1 |
| [`api/`](api/README.md) | API em Laravel 13 (Sanctum), com 255 testes |
| [`web/`](web/README.md) | App em Vue 3 + PWA (voluntário e líder), com 231 testes |
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

- **API e app:** todas as regras da v1 estão implementadas e testadas: voluntário, líder, Adm, pedido de entrada com aprovação, relatório geral, histórico de alterações, recuperação de senha por e-mail e login com Google.
- **Decisões:** todas fechadas (D-01 a D-18); a D-18 foi respondida pelo cliente: todos os cultos têm Louvor.
- **Falta:** avisos por WhatsApp ou e-mail e a versão Flutter. Para o Google e o e-mail funcionarem no servidor, veja "Configuração para produção" no README da API.
