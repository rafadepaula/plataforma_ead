---
name: testing-architecture
description: Visão geral, schemas, fluxos, regras da infraestrutura de testes, Quality Gate, esteira CI/CD da Plataforma EAD.
---

# Testing Architecture (`testing-architecture`)

## Overview

Infraestrutura de testes garante integridade do sistema via Test-Driven Development (TDD) e verificação contínua. Toda funcionalidade backend e frontend tem teste unitário, teste de funcionalidade (Feature) e teste automatizado de navegador End-to-End (E2E) com Laravel Dusk.

---

## Componentes Principais

1. **PHPUnit Test Framework (`phpunit.xml`)**:
   - Testsuites: `Unit`, `Feature`, `Browser`.
   - Roda sobre banco em memória (`sqlite` `:memory:`) para isolamento e velocidade.
   - Drivers leves: sessão (`array`), cache (`array`), fila (`sync`).

2. **Auditor de Cobertura de Código (`scripts/check-coverage.php`)**:
   - Lê relatório XML formato Clover (`storage/coverage/clover.xml`).
   - Valida cobertura de linhas contra meta mínima de **95,00%**.
   - Aborta pipeline CI/CD se não conforme.

3. **Automação E2E Laravel Dusk (`tests/Browser/`)**:
   - Roda testes End-to-End em Headless Chrome.
   - Testa interação real de UI, navegação de páginas, formulários, fluxos de usuário.
   - **Unidade de organização = cadeia de ciclo de vida (jornada), não módulo.** Um método cobre jornada inteira de entidade/ator (criar, editar, transicionar, excluir, consequência), com asserções intermediárias de UI e banco por etapa. Arquivos podem cruzar fronteiras de módulo/spec quando jornada cruza. Regra completa em `testing-conventions`.
   - **Custo fixo por método** (truncate + boot da sessão WebDriver + login + navegação) domina wall-clock da suíte. Daí encadear em vez de métodos atômicos.

4. **Banco de Dados MySQL Dedicado ao Dusk (RN13 / RF30)** (SPEC-14):
   - Suítes `tests/Browser/*` **nunca** rodam contra banco de desenvolvimento `plataforma_ead`. Apontam obrigatoriamente para base MySQL isolada `testing`.
   - Localmente (Sail), base `testing` é provisionada automática no container `mysql` via `./vendor/laravel/sail/database/mysql/create-testing-database.sh`, montado em `/docker-entrypoint-initdb.d/10-create-testing-database.sh` (ver `compose.yaml`). Ambos bancos (`plataforma_ead` e `testing`) convivem no mesmo serviço MySQL.
   - `vendor/bin/sail dusk` troca automático o `.env` ativo pelo `.env.dusk.local` (comportamento nativo do `DuskCommand`, que resolve `.env.dusk.{environment}`), aponta `DB_DATABASE=testing`, restaura `.env` original ao final.
   - Classes em `tests/Browser/*` herdam `DatabaseTruncation` de `Tests\DuskTestCase` (`$exceptTables = ['migrations', 'roles', 'permissions', 'role_has_permissions']`), que migra/limpa **estritamente** a conexão ativa no momento do teste. Como `.env` ativo é `.env.dusk.local`, isso significa base `testing`, garantindo `plataforma_ead` intacto (RN13).
   - **Por que `DatabaseTruncation` e não `DatabaseMigrations`**: `DatabaseMigrations` roda `migrate:fresh` a cada método (~30 migrações × N métodos). `DatabaseTruncation` migra uma vez por suíte, depois só executa `TRUNCATE` nas tabelas tocadas. Mesma garantia de isolamento, 60–70% menos tempo de banco. Trait fica centralizada na classe base; classes filhas não redeclaram.

5. **Pipeline CI/CD em GitHub Actions (`.github/workflows/ci.yml`)**:
   - Job único `test`, ambiente PHP 8.5, extensão Xdebug ativada.
   - Serviços dedicados `mysql` (base `testing`) e `selenium` (`selenium/standalone-chrome`, rede `host`) sobem em paralelo ao job, com healthchecks (`mysqladmin ping` / `curl` no endpoint `/wd/hub/status`).
   - Execução sequencial: compilar assets com Node 20 / Vite, configurar ambiente `.env.ci` (sqlite `:memory:`, rápido) para suítes Unit/Feature, trocar explícito para `.env.dusk.ci` (mysql `testing`, `DUSK_DRIVER_URL` apontando para serviço `selenium`) imediatamente antes de `php artisan dusk`, verificar limite de cobertura de 95,00%, upload dos artefatos de cobertura.

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

Serviços `mysql`/`selenium` do GitHub Actions são containers-irmãos do job (não `container:` de job), então acesso via `127.0.0.1`/`localhost` com portas publicadas (`3306`, `4444`). Serviço `selenium` roda com `--network=host` para alcançar `php artisan serve` do runner via `localhost`.
