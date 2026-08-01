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

4. **Pipeline CI/CD em GitHub Actions (`.github/workflows/ci.yml`)**:
   - Job único `test` focado no ambiente PHP 8.5 com extensão Xdebug ativada.
   - Execução sequencial: compilação de assets com Node 20 / Vite, configuração do ambiente `.env.ci`, execução das suítes de testes Unit, Feature e Dusk, verificação do limite de cobertura de 95,00% e upload dos artefatos de cobertura.

---

## Fluxo da Esteira CI/CD

```
[Checkout] -> [Node 20 Setup & Build] -> [PHP 8.5 & Xdebug Setup]
    |
    v
[Composer Install] -> [Env & Key Setup] -> [Chrome Driver & Artisan Serve]
    |
    v
[PHPUnit Unit & Feature + Clover XML] -> [Laravel Dusk E2E]
    |
    v
[Check Coverage >= 95.00%] -> [Upload Clover Artifact]
```
