---
name: testing-architecture
description: Visão Geral, Schemas, Fluxos e Regras da Infraestrutura de Testes, Quality Gate e Esteira CI/CD da Plataforma EAD.
---

# Testing Architecture (`testing-architecture`)

## Overview

A infraestrutura de testes da Plataforma EAD garante a integridade do sistema por meio de Test-Driven Development (TDD) e verificação contínua. Toda funcionalidade backend e frontend é respaldada por testes unitários, testes de funcionalidade (Feature) e testes automatizados de navegador End-to-End (E2E) com Laravel Dusk.

---

## Componentes Principais

1. **PHPUnit Test Framework (`phpunit.xml`)**:
   - Organizado nas testsuites: `Unit`, `Feature` e `Browser`.
   - Executa sobre banco de dados em memória (`sqlite` `:memory:`) para isolamento e velocidade.
   - Drivers leves de sessão (`array`), cache (`array`) e fila (`sync`).

2. **Auditor de Cobertura de Código (`scripts/check-coverage.php`)**:
   - Analisa o relatório XML em formato Clover (`storage/coverage/clover.xml`).
   - Valida se a cobertura de linhas de código atinge a meta estipulada de no mínimo **95,00%**.
   - Aborta a execução do pipeline de CI/CD em caso de não conformidade.

3. **Automação E2E Laravel Dusk (`tests/Browser/`)**:
   - Executa testes End-to-End em navegador Headless Chrome.
   - Testa interações reais de UI, navegação de páginas, formulários e fluxos do usuário.

4. **Banco de Dados MySQL Dedicado ao Dusk (RN13 / RF30)** (SPEC-14):
   - As suítes `tests/Browser/*` **nunca** rodam contra o banco de desenvolvimento `plataforma_ead`. Elas apontam obrigatoriamente para uma base MySQL isolada chamada `testing`.
   - Localmente (Sail), a base `testing` é provisionada automaticamente no container `mysql` via `./vendor/laravel/sail/database/mysql/create-testing-database.sh`, montado em `/docker-entrypoint-initdb.d/10-create-testing-database.sh` (ver `compose.yaml`). Ambos os bancos (`plataforma_ead` e `testing`) convivem no mesmo serviço MySQL.
   - `vendor/bin/sail dusk` troca automaticamente o `.env` ativo pelo `.env.dusk.local` (comportamento nativo do `DuskCommand`, que resolve `.env.dusk.{environment}`), aponta `DB_DATABASE=testing` e restaura o `.env` original ao final da execução.
   - As classes em `tests/Browser/*` usam `DatabaseMigrations` (ou `DatabaseTruncation`), que migram/limpam **estritamente** a conexão ativa no momento do teste — como o `.env` ativo é o `.env.dusk.local`, isso significa a base `testing`, garantindo que `plataforma_ead` permaneça intacto (RN13).

5. **Pipeline CI/CD em GitHub Actions (`.github/workflows/ci.yml`)**:
   - Job único `test` focado no ambiente PHP 8.5 com extensão Xdebug ativada.
   - Serviços dedicados `mysql` (base `testing`) e `selenium` (`selenium/standalone-chrome`, rede `host`) sobem em paralelo ao job, com healthchecks (`mysqladmin ping` / `curl` no endpoint `/wd/hub/status`).
   - Execução sequencial: compilação de assets com Node 20 / Vite, configuração do ambiente `.env.ci` (sqlite `:memory:`, rápido) para as suítes Unit/Feature, seguida da troca explícita para `.env.dusk.ci` (mysql `testing`, `DUSK_DRIVER_URL` apontando para o serviço `selenium`) imediatamente antes de `php artisan dusk`, verificação do limite de cobertura de 95,00% e upload dos artefatos de cobertura.

---

## Fluxo da Esteira CI/CD

```
[Checkout] -> [Node 20 Setup & Build] -> [PHP 8.5 & Xdebug Setup]
    |
    v
[Composer Install] -> [Env & Key Setup (.env.ci / sqlite)] -> [Chrome Driver Setup]
    |
    v
[PHPUnit Unit & Feature + Clover XML]
    |
    v
[Swap to .env.dusk.ci (mysql `testing` + selenium)] -> [Wait for MySQL] -> [Artisan Serve]
    |
    v
[Laravel Dusk E2E against `testing` DB] -> [Check Coverage >= 95.00%] -> [Upload Clover Artifact]
```

Serviços `mysql`/`selenium` do GitHub Actions são containers-irmãos do job (não um `container:` de job), então são acessados via `127.0.0.1`/`localhost` com as portas publicadas (`3306`, `4444`). O serviço `selenium` roda com `--network=host` para conseguir alcançar o `php artisan serve` do runner via `localhost`.
