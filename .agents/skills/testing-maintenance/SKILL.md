---
name: testing-maintenance
description: >
  Infraestrutura de testes da Plataforma EAD: suítes PHPUnit Unit/Feature, Dusk E2E
  agrupado por cadeia de ciclo de vida em base MySQL `testing` dedicada,
  `DatabaseTruncation` via `Tests\DuskTestCase`, helpers de tenancy por host
  (`onHost`, `actingAsAdmin`, `withOrgContext`), snapshot dusk-selectors, Quality Gate
  de cobertura 95%, esteira CI/CD. Use ao escrever ou editar qualquer teste
  PHPUnit/Dusk, ao adicionar seletor `dusk=`, ao debugar flakiness ou erro de
  isolamento de banco, ou quando `HarnessVerificationTest`, snapshot de seletores ou
  o pipeline CI/CD falha.
license: MIT
metadata:
  feature: testing
  roles: [architecture, conventions, maintenance]
---

# Test Infrastructure (`testing-maintenance`)

Infraestrutura de testes: PHPUnit (Unit/Feature sobre sqlite `:memory:`), Laravel Dusk E2E em base MySQL `testing` dedicada com cadeias de ciclo de vida, cobertura mínima de 95% via `scripts/check-coverage.php`, helpers de tenancy por host (`onHost`/`actingAsAdmin`) e pipeline CI/CD em GitHub Actions.

Conhecimento detalhado do módulo vive nos arquivos de referência abaixo —
leia apenas o que a tarefa precisa:

| Reference | Ler quando |
| --- | --- |
| `resource/architecture.md` | Visão geral da infraestrutura de testes, suítes, banco dedicado do Dusk, helpers de tenancy por host (`onHost`/`actingAsAdmin`), Quality Gate de cobertura e esteira CI/CD. |
| `resource/conventions.md` | Escrever ou editar testes PHPUnit/Dusk: cadeias de ciclo de vida, `duskTenant`, snapshot dusk-selectors, traits de banco, asserções. |
| `resource/maintenance.md` | Debug de flakiness, falha de isolamento de banco, grupo `requires-network`, pré-requisitos Dusk no container, CI/CD quebrado. |
