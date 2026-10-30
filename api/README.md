# API de escalas (v1)

API em Laravel do app de escalas de ministérios. O comportamento esperado está em [`../docs/regras-de-negocio.md`](../docs/regras-de-negocio.md): cada regra tem um código `RN-xx` e cada caso de teste um `T-xx`. Os testes citam o código `T-xx` no nome.

## Requisitos

PHP 8.4+, Composer. Em desenvolvimento usa SQLite.

## Primeira execução

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Testes

```bash
php artisan test
```

## Estrutura

| Pasta | Conteúdo |
|---|---|
| `app/Domain/` | Regras puras, sem banco: leitura de horário, choque, grade de cultos, rodízio de bandas, estado da escala, prazo |
| `app/Exceptions/RegraDeNegocio.php` | Exceção para violação de regra (a camada HTTP responde 422) |
| `database/migrations/` | Esquema do banco, com as restrições e cascatas das regras |
| `tests/Unit/Domain/` | Testes das regras puras (`T-03` a `T-06`, `T-14`, `T-18`, `T-24` a `T-26`, `T-36` a `T-40`, `T-56`, `T-62`) |
| `tests/Feature/BancoDeDadosTest.php` | Restrições e cascatas do banco (`T-07`, `T-08`, `T-10`, `T-13`, `T-22`) |
| `tests/Feature/Auth/`, `IsolamentoPorIgrejaTest.php`, `CriarIgrejaCommandTest.php` | Login, isolamento por igreja e comando da primeira igreja (`T-64` a `T-73`) |
| `tests/Feature/CadastrosTest.php`, `ConviteTest.php` | Cadastros por papel e convite (`T-74` a `T-95`) |
| `app/Models/` | Modelos Eloquent; os que têm `igreja_id` usam o escopo por igreja |

## Decisões de modelagem

- **Tenant:** `igreja_id` em todas as tabelas de negócio. Cada filial é uma igreja individual.
- **Pessoa x usuário:** a pessoa existe antes do login (o líder cadastra). `pessoas.user_id` é opcional e o vínculo é feito pelo e-mail verificado.
- **Login:** e-mail e senha, com opção de Google (`users.google_id`; `password` pode ser vazio).
- **Concorrência:** `escalas.versao` e `turnos.versao` para detectar edição simultânea (D-14).
- **Rodízio:** a ordem das bandas é `bandas.ordem`.

## Rotas da API

Todas ficam em `/api`. As protegidas exigem `Authorization: Bearer <token>`.

| Rota | Quem | O que faz |
|---|---|---|
| `POST /login` | todos | E-mail e senha; devolve o token (expira em 30 dias) |
| `POST /convites/aceitar` | todos | Escolhe a senha pelo link de convite (primeiro acesso ou troca de senha) |
| `POST /logout`, `GET /eu` | logado | Sair; quem sou eu (pessoa, igreja, ministérios) |
| `GET/POST /ministerios`, `PATCH/DELETE /ministerios/{id}`, `PUT /ministerios/{id}/lider` | Adm (listar: todos, filtrado) | Ministérios e líder |
| `POST /ministerios/{id}/equipe`, `DELETE /ministerios/{id}/equipe/{pessoa}` | Adm ou líder | Equipe do ministério |
| `GET/POST /ministerios/{id}/postos`, `PATCH/DELETE /postos/{id}` | líder (ver: equipe) | Postos |
| `GET/POST /pessoas`, `PATCH/DELETE /pessoas/{id}` | Adm ou líder | Pessoas (excluir: só Adm) |
| `POST /pessoas/{id}/convite` | Adm ou líder da pessoa | Gera o link de convite |

## Segurança e isolamento

- **Isolamento por igreja:** os modelos com `igreja_id` usam o trait `PertenceAIgreja`. O middleware `DefinirIgrejaAtual` define a igreja pela pessoa logada e roda antes do route model binding (ver `bootstrap/app.php`). Sem igreja definida, a consulta volta vazia. Código de sistema usa `withoutGlobalScopes()` de propósito.
- **Permissões:** definidas como Gates em `AppServiceProvider` (`adm`, `lidera`, `ver-ministerio`, `gerir-equipe`, `gerir-pessoa`).
- **Primeira igreja:** `php artisan escala:criar-igreja "Nome da igreja" "Nome do Adm" adm@email.com` (a senha é pedida). Depois o Adm cadastra e convida as pessoas.
- **Link do convite:** usa `APP_FRONTEND_URL` (endereço do app Vue).

## O que ainda falta

Cadastros de bandas, funções e cultos; escalas e turnos (gerar, editar, liberar, publicar); marcar disponibilidade, confirmar e tirar; pedido de troca; avisos; relatórios; login com Google; auditoria (D-12); pedido de entrada do voluntário (D-15). A recuperação de senha por e-mail fica por último e não entra na v1.
