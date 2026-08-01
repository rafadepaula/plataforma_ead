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
