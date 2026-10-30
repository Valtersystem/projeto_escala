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
| `app/Models/` | Modelos Eloquent; os que têm `igreja_id` usam o escopo por igreja |

## Decisões de modelagem

- **Tenant:** `igreja_id` em todas as tabelas de negócio. Cada filial é uma igreja individual.
- **Pessoa x usuário:** a pessoa existe antes do login (o líder cadastra). `pessoas.user_id` é opcional e o vínculo é feito pelo e-mail verificado.
- **Login:** e-mail e senha, com opção de Google (`users.google_id`; `password` pode ser vazio).
- **Concorrência:** `escalas.versao` e `turnos.versao` para detectar edição simultânea (D-14).
- **Rodízio:** a ordem das bandas é `bandas.ordem`.

## Acesso

- **Login:** `POST /api/login` com `email` e `senha` devolve o token (Sanctum, expira em 30 dias).
- **Rotas protegidas:** `POST /api/logout` e `GET /api/eu` (a pessoa logada, a igreja e os ministérios). Enviar `Authorization: Bearer <token>`.
- **Isolamento por igreja:** os modelos com `igreja_id` usam o trait `PertenceAIgreja`. O middleware `DefinirIgrejaAtual` define a igreja pela pessoa logada. Sem igreja definida, a consulta volta vazia. Código de sistema usa `withoutGlobalScopes()` de propósito.
- **Primeira igreja:** `php artisan escala:criar-igreja "Nome da igreja" "Nome do Adm" adm@email.com` (a senha é pedida).

## O que ainda falta

Rotas e controladores de cadastro (ministérios, pessoas, postos, bandas, funções, cultos), serviços de aplicação (gerar turnos, marcar, confirmar, publicar, trocar), permissões por papel (Adm, líder, voluntário), convite e primeiro acesso da pessoa cadastrada, login com Google, avisos, auditoria (D-12) e pedido de entrada (D-15).
