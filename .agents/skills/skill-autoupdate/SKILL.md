---
name: skill-autoupdate
description: Protocolo de Auto-Update e Auditoria de Skills Agenticas para Manutenção Contínua conforme SPEC-03.
---

# Meta-Skill: Auto-Update & Auditoria de Skills (`skill-autoupdate`)

## Overview

A meta-skill **`skill-autoupdate`** estabelece o protocolo operacional e os padrões obrigatórios para que os agentes de Inteligência Artificial mantenham a documentação técnica acionável em `.agents/skills/` permanentemente sincronizada com o código-fonte da Plataforma EAD.

Em conformidade estrita com a **SPEC-03 (Agentic Harness e Auto-Update de Skills)** e **SPEC-00 §6**, **nenhuma alteração de código, esquema de banco de dados, regra de negócio ou rota pode ser finalizada sem a devida auditoria e atualização da tríade de skills do módulo correspondente.**

---

## 1. Tríade Obrigatória por Feature/Módulo

Toda funcionalidade ou especificação do sistema (ex: `frontend`, `tenancy`, `testing`, `auth`, `courses`, `quizzes`, `certificates`, `forum`, `invitations`, `progress`, etc.) **DEVE** possuir uma tríade dedicada de skills no diretório `.agents/skills/`:

```
.agents/skills/
├── [feature]-architecture/SKILL.md   <-- Visão Geral, Schemas, Fluxos de Dados e Regras de Arquitetura
├── [feature]-conventions/SKILL.md    <-- Padrões de Código, Code Snippets, Layout de Arquivos & Guardrails
└── [feature]-maintenance/SKILL.md    <-- Guia de Manutenção, Debug, Suíte de Testes (PHPUnit/Dusk) & Edge Cases
```

### Especificação dos Componentes da Tríade

1. **`[feature]-architecture/SKILL.md`**:
   - Mapeia a visão geral do módulo, schemas de banco de dados (tabelas, relacionamentos Eloquent, chave primária/estrangeiras), fluxos de dados, componentes arquiteturais e permissões Spatie Roles.

2. **`[feature]-conventions/SKILL.md`**:
   - Define padrões de nomenclatura, estruturas de diretórios, snippets de código recomendados para Controllers, Actions, Services, Repositories, Blade Components e Módulos JavaScript SOLID.

3. **`[feature]-maintenance/SKILL.md`**:
   - Guia prático de manutenção contendo procedimentos de debugging, comandos Sail de testes PHPUnit (`vendor/bin/sail artisan test --filter=...`) e Dusk (`vendor/bin/sail artisan dusk --filter=...`), estratégias de limpeza/reset de estado, e mapa de edge cases conhecidos.

---

## 2. Gatilhos de Auto-Update (Triggers)

O agente de IA **DEVE** acionar o protocolo de auto-update sempre que realizar qualquer uma das seguintes ações no repositório:

| Ação Realizada no Código | Skill(s) a Sobrescrever / Atualizar | Elementos a Atualizar |
| :--- | :--- | :--- |
| **Migrations / Models / Database Schemas** | `[feature]-architecture/SKILL.md` | Schemas de tabela, novos campos, índices, enums, relacionamentos Eloquent. |
| **Actions / Services / Controllers / Rotas** | `[feature]-architecture/SKILL.md`<br>`[feature]-conventions/SKILL.md` | Regras de negócio, assinaturas de métodos, novas rotas Web/API, middlewares. |
| **Blade Views / JS Modules / CSS Styling** | `[feature]-architecture/SKILL.md`<br>`[feature]-conventions/SKILL.md` | Componentes UI (`<x-ui.*>`), layouts, scripts JS em `resources/js/modules/`. |
| **PHPUnit / Dusk Tests / Bugfixes** | `[feature]-maintenance/SKILL.md` | Filtros de teste PHPUnit/Dusk, tratamento de novos edge cases, logs de erro. |

---

## 3. Protocolo Operacional Passo a Passo

Ao implementar ou modificar qualquer funcionalidade na Plataforma EAD, o agente deve seguir rigorosamente a sequência:

### Passo 1: Identificar o Módulo Alvo
Determine o nome representativo da feature afetada a partir do nome da especificação ou diretório (ex: `quizzes` a partir de `08-quizzes-and-evaluations-engine.md`).

### Passo 2: Inspecionar / Criar a Tríade
Verifique a existência dos três diretórios sob `.agents/skills/`:
- `.agents/skills/[feature]-architecture/SKILL.md`
- `.agents/skills/[feature]-conventions/SKILL.md`
- `.agents/skills/[feature]-maintenance/SKILL.md`

> **Regra de Criação Inicial**: Caso a feature seja totalmente nova e os diretórios não existam, o agente **DEVE** criar os 3 diretórios e popular os arquivos `SKILL.md` com base na implementação recém-construída.

### Passo 3: Auditar e Atualizar o Conteúdo
Para cada skill da tríade, compare o conteúdo documentado com as alterações mescladas no código-fonte:
- Atualize nomes de classes, tabelas, métodos, rotas e propriedades.
- Remova trechos obsoletos ou padrões substituídos.
- Documente novos cenários de erro e guardrails específicos descobertos durante os testes.

### Passo 4: Executar a Auditoria Programática
Execute o script CLI de verificação para garantir a conformidade estrita da harness agentica:

```bash
vendor/bin/sail php scripts/check-skills.php
```

Se o script retornar código `0` (`SUCESSO AUDITORIA`), o protocolo foi cumprido e a tarefa pode ser concluída.

---

## 4. Auditoria Programática (`scripts/check-skills.php`)

A auditoria de skills é automatizada pelo script CLI `scripts/check-skills.php`.

### Comandos Principais

```bash
# Execução padrão (audita todos os módulos auto-descobertos em .agents/skills/)
php scripts/check-skills.php

# Execução no ambiente Sail
vendor/bin/sail php scripts/check-skills.php

# Auditoria de módulos específicos
php scripts/check-skills.php --modules=frontend,tenancy,testing

# Especificação de diretório customizado
php scripts/check-skills.php --dir=.agents/skills
```

---

## 5. Guardrails de Qualidade das Skills

1. **Sintaxe PHPUnit Estrita**: Todos os exemplos e instruções de testes de unidade/integração nas skills devem obrigatoriamente utilizar **PHPUnit** (estendendo `Tests\TestCase` ou `DuskTestCase`). O uso de funções Pest é estritamente proibido.
2. **Exemplos Reais do Codebase**: Nunca utilizar trechos hipotéticos ou genéricos. Todos os snippets de código e schemas devem refletir a implementação real em `app/`, `database/`, `resources/` e `tests/`.
3. **Pint Code Style**: Scripts PHP utilitários de manutenção em `scripts/` devem estar formatados de acordo com os padrões Laravel Pint (`declare(strict_types=1);`).
