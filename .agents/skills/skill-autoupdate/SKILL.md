---
name: skill-autoupdate
description: Protocolo de Auto-Update e Auditoria de Skills Agenticas para Manutenção Contínua conforme SPEC-03. Inclui regra de escrita: toda skill em .agents/skills/ é escrita em caveman FULL. Use ao criar, editar ou auditar qualquer SKILL.md.
---

# Meta-Skill: Auto-Update & Auditoria de Skills (`skill-autoupdate`)

## Overview

Meta-skill **`skill-autoupdate`** define protocolo operacional e padrões obrigatórios. Agentes de IA mantêm documentação técnica acionável em `.agents/skills/` sempre sincronizada com código-fonte da Plataforma EAD.

Conformidade estrita com **SPEC-03 (Agentic Harness e Auto-Update de Skills)** e **SPEC-00 §6**: **nenhuma alteração de código, esquema de banco, regra de negócio ou rota pode ser finalizada sem auditoria e atualização da tríade de skills do módulo correspondente.**

---

## 1. Tríade Obrigatória por Feature/Módulo

Toda funcionalidade ou especificação (ex: `frontend`, `tenancy`, `testing`, `auth`, `courses`, `quizzes`, `certificates`, `forum`, `invitations`, `progress`, etc.) **DEVE** ter tríade dedicada em `.agents/skills/`:

```
.agents/skills/
├── [feature]-architecture/SKILL.md   <-- Visão Geral, Schemas, Fluxos de Dados e Regras de Arquitetura
├── [feature]-conventions/SKILL.md    <-- Padrões de Código, Code Snippets, Layout de Arquivos & Guardrails
└── [feature]-maintenance/SKILL.md    <-- Guia de Manutenção, Debug, Suíte de Testes (PHPUnit/Dusk) & Edge Cases
```

### Especificação dos Componentes da Tríade

1. **`[feature]-architecture/SKILL.md`**:
   - Mapeia visão geral do módulo, schemas de banco (tabelas, relacionamentos Eloquent, chave primária/estrangeiras), fluxos de dados, componentes arquiteturais, permissões Spatie Roles.

2. **`[feature]-conventions/SKILL.md`**:
   - Define padrões de nomenclatura, estruturas de diretórios, snippets recomendados para Controllers, Actions, Services, Repositories, Blade Components, Módulos JavaScript SOLID.

3. **`[feature]-maintenance/SKILL.md`**:
   - Guia prático de manutenção: procedimentos de debugging, comandos Sail de testes PHPUnit (`vendor/bin/sail artisan test --filter=...`) e Dusk (`vendor/bin/sail artisan dusk --filter=...`), estratégias de limpeza/reset de estado, mapa de edge cases conhecidos.

---

## 2. Gatilhos de Auto-Update (Triggers)

Agente de IA **DEVE** acionar protocolo de auto-update sempre que fizer qualquer uma destas ações no repositório:

| Ação Realizada no Código | Skill(s) a Sobrescrever / Atualizar | Elementos a Atualizar |
| :--- | :--- | :--- |
| **Migrations / Models / Database Schemas** | `[feature]-architecture/SKILL.md` | Schemas de tabela, novos campos, índices, enums, relacionamentos Eloquent. |
| **Actions / Services / Controllers / Rotas** | `[feature]-architecture/SKILL.md`<br>`[feature]-conventions/SKILL.md` | Regras de negócio, assinaturas de métodos, novas rotas Web/API, middlewares. |
| **Blade Views / JS Modules / CSS Styling** | `[feature]-architecture/SKILL.md`<br>`[feature]-conventions/SKILL.md` | Componentes UI (`<x-ui.*>`), layouts, scripts JS em `resources/js/modules/`. |
| **PHPUnit / Dusk Tests / Bugfixes** | `[feature]-maintenance/SKILL.md` | Filtros de teste PHPUnit/Dusk, tratamento de novos edge cases, logs de erro. |

---

## 3. Protocolo Operacional Passo a Passo

Ao implementar ou modificar funcionalidade na Plataforma EAD, agente segue rigorosamente esta sequência:

### Passo 1: Identificar o Módulo Alvo
Determine nome representativo da feature afetada a partir do nome da especificação ou diretório (ex: `quizzes` a partir de `08-quizzes-and-evaluations-engine.md`).

### Passo 2: Inspecionar / Criar a Tríade
Verifique existência dos três diretórios sob `.agents/skills/`:
- `.agents/skills/[feature]-architecture/SKILL.md`
- `.agents/skills/[feature]-conventions/SKILL.md`
- `.agents/skills/[feature]-maintenance/SKILL.md`

> **Regra de Criação Inicial**: Se feature for totalmente nova e diretórios não existirem, agente **DEVE** criar os 3 diretórios e popular os `SKILL.md` com base na implementação recém-construída. Conteúdo novo já nasce em caveman FULL (§5, guardrail 0).

### Passo 3: Auditar e Atualizar o Conteúdo
Para cada skill da tríade, compare conteúdo documentado com alterações mescladas no código-fonte:
- Atualize nomes de classes, tabelas, métodos, rotas, propriedades.
- Remova trechos obsoletos ou padrões substituídos.
- Documente novos cenários de erro e guardrails descobertos durante testes.

### Passo 4: Executar a Auditoria Programática
Execute script CLI de verificação para garantir conformidade estrita da harness agentica:

```bash
vendor/bin/sail php scripts/check-skills.php
```

Se script retornar código `0` (`SUCESSO AUDITORIA`), protocolo cumprido, tarefa pode concluir.

---

## 4. Auditoria Programática (`scripts/check-skills.php`)

Auditoria de skills automatizada pelo script CLI `scripts/check-skills.php`.

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

0. **Modo Caveman FULL Obrigatório**: toda skill em `.agents/skills/` é escrita e mantida em caveman FULL. Vale para skill nova e para edição de skill existente.
   - Corta artigo (o/a/um), filler (apenas/realmente/basicamente/simplesmente), gentileza, hedging. Fragmento serve. Sinônimo curto.
   - **Comprime redação, nunca conteúdo.** Nenhuma seção, item de lista, linha de tabela, path, nome de classe/rota/seletor ou failure mode desaparece.
   - Code block, comando CLI, string de erro, identificador: verbatim.
   - Idioma do arquivo preservado — arquivo em português vira caveman em português.
   - Sem emoji decorativo em heading. Sem seta causal (→) em prosa; dentro de code block e diagrama ASCII fica como está.
   - Sem abreviação inventada (cfg/impl/req). Sigla padrão (DB, API, HTTP, UI, E2E) serve.
   - `description` do frontmatter também em caveman, mas mantém as keywords de trigger (nome de arquivo, id de spec, nome de classe) — é o que faz o match da skill.
   - Exceção: passo de sequência, aviso de segurança ou ação destrutiva onde cortar artigo/conjunção deixa a ordem ou a condição ambígua. Nesses trechos, frase completa.

1. **Sintaxe PHPUnit Estrita**: Todos exemplos e instruções de testes unidade/integração nas skills usam **PHPUnit** (estendendo `Tests\TestCase` ou `DuskTestCase`). Funções Pest: estritamente proibidas.
2. **Exemplos Reais do Codebase**: Nunca usar trechos hipotéticos ou genéricos. Todo snippet e schema reflete implementação real em `app/`, `database/`, `resources/`, `tests/`.
3. **Pint Code Style**: Scripts PHP utilitários de manutenção em `scripts/` seguem padrões Laravel Pint (`declare(strict_types=1);`).
4. **Referências a Testes E2E por Cadeia, Não por Módulo**: suíte `tests/Browser/` agrupada por **cadeia de ciclo de vida (jornada do usuário)**, pode cruzar módulos. Ao documentar cobertura em skill `[feature]-maintenance`:
   - Descreva **quais cenários estão asseverados** e em qual cadeia/etapa, não "o arquivo de teste do módulo X".
   - Nunca escreva que falta cobertura só porque não existe arquivo/método dedicado ao módulo.
   - Ao renomear/consolidar métodos Dusk, atualize menções em todas as skills afetadas — inclusive de outros módulos que a jornada atravessa.
   - Nunca instrua a declarar `DatabaseMigrations`/`RefreshDatabase` em `tests/Browser/*`: `DatabaseTruncation` vive em `Tests\DuskTestCase`.
   - Regra canônica: `testing-conventions`.
