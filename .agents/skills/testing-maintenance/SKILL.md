---
name: testing-maintenance
description: Guia de Manutenção, Debug, Resolução de Flakiness e Edge Cases da Infraestrutura de Testes e CI/CD.
---

# Testing Maintenance (`testing-maintenance`)

## Overview

Este documento fornece instruções para diagnóstico de erros, manutenção contínua e solução de problemas comuns nas suítes de testes da Plataforma EAD.

---

## Diagnóstico e Debugging

1. **Falhas em Testes Laravel Dusk**:
   - Verificar capturas de tela geradas em `tests/Browser/screenshots/` e logs em `tests/Browser/console/`.
   - Garantir que o driver do Chrome esteja sincronizado executando:
     ```bash
     php artisan dusk:chrome-driver --detect
     ```
   - Garantir que a aplicação esteja sendo servida antes dos testes Dusk (ex: `php artisan serve --port=8000 &`).

2. **Auditoria e Verificação de Cobertura**:
   - Para gerar o relatório de cobertura localmente com Xdebug:
     ```bash
     XDEBUG_MODE=coverage php artisan test --testsuite=Unit,Feature --coverage-clover=storage/coverage/clover.xml
     php scripts/check-coverage.php
     ```
   - O script `scripts/check-coverage.php` verifica apenas o arquivo `storage/coverage/clover.xml` pré-existente e calcula se a porcentagem de cobertura de código atinge o mínimo exigido de 95,00%.

3. **Execução de Testes via Sail**:
   - Executar suíte PHPUnit:
     ```bash
     vendor/bin/sail artisan test
     ```
   - Executar formatador Pint em arquivos modificados:
     ```bash
     vendor/bin/sail bin pint --dirty --format agent
     ```

---

## Resolução de Problemas Comuns (Edge Cases)

- **Instabilidade (Flakiness) em Testes Dusk**:
  - Evitar chamadas `sleep()`. Utilizar métodos explícitos como `$browser->waitForText(...)` ou `$browser->waitUntilMissing(...)`.
- **Erro de Cobertura não Gerada**:
  - Certifique-se de que a extensão Xdebug ou PCOV está habilitada no ambiente PHP (`XDEBUG_MODE=coverage`).
- **Dusk rodou contra o banco errado (`plataforma_ead` foi modificado) — RN13/RF30 (SPEC-14)**:
  - Sintoma: dados de desenvolvimento sumiram/foram truncados após rodar `vendor/bin/sail dusk`, ou uma suíte Dusk aparentemente "passou" mas alterou registros que você reconhece do seu ambiente local.
  - Diagnóstico:
    1. O script exige um subcomando (`snapshot`/`verify`; `getopt()` não é usado por parar de ler flags no primeiro argumento posicional). Rode `vendor/bin/sail php scripts/verify-dev-db-preserved.php snapshot` **antes** de `vendor/bin/sail dusk` e `vendor/bin/sail php scripts/verify-dev-db-preserved.php verify` **depois** — o primeiro grava um snapshot (contagem de linhas/checksum via `CHECKSUM TABLE`) das tabelas de `plataforma_ead` em `storage/framework/testing/dusk-db-snapshot.json` (gitignored), o segundo re-tira o snapshot e compara, falhando (`exit 1`) em qualquer tabela alterada/adicionada/removida.
    2. Confirme que `.env.dusk.local` existe na raiz do repositório e contém `DB_DATABASE=testing` e `DB_CONNECTION=mysql` (não `plataforma_ead`).
    3. Verifique o `APP_ENV` do `.env` **ativo antes** de rodar o Dusk: o `DuskCommand` nativo resolve o arquivo de troca como `.env.dusk.{environment()}`. Se o `.env` local não tiver `APP_ENV=local`, o Dusk vai procurar por `.env.dusk.<outro-ambiente>` (que não existe) e, na ausência de um `.env.dusk` genérico, **continuará usando o `.env` ativo** — silenciosamente rodando contra `plataforma_ead`. Corrija `APP_ENV=local` no `.env` de desenvolvimento ou gere manualmente o `.env.dusk.<seu-ambiente>` correspondente.
    4. Confira se o volume `sail-mysql` já existia antes desta spec ser adotada: o script `create-testing-database.sh` só roda em `/docker-entrypoint-initdb.d/` na **primeira** criação do volume. Se o volume for antigo, a base `testing` pode não ter sido criada — valide com `vendor/bin/sail artisan db:show` ou crie manualmente via `vendor/bin/sail mysql -e "CREATE DATABASE IF NOT EXISTS testing;"`.
  - No CI, o equivalente é conferir se o passo "Setup CI Dusk environment" (que copia `.env.dusk.ci` por cima do `.env`) realmente rodou **antes** do passo "Run Dusk Browser Tests" no `.github/workflows/ci.yml`, e não antes do passo de Unit/Feature (que deve continuar em sqlite via `.env.ci`).
