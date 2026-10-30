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

## Decisões de modelagem

- **Tenant:** `igreja_id` em todas as tabelas de negócio. Cada filial é uma igreja individual.
- **Pessoa x usuário:** a pessoa existe antes do login (o líder cadastra). `pessoas.user_id` é opcional e o vínculo é feito pelo e-mail verificado.
- **Login:** e-mail e senha, com opção de Google (`users.google_id`; `password` pode ser vazio).
- **Concorrência:** `escalas.versao` e `turnos.versao` para detectar edição simultânea (D-14).
- **Rodízio:** a ordem das bandas é `bandas.ordem`.

## O que ainda falta

Modelos Eloquent, escopo por igreja, autenticação (Sanctum e Google), rotas e controladores, serviços de aplicação (marcar, confirmar, publicar, trocar), avisos, auditoria (D-12) e pedido de entrada (D-15).
