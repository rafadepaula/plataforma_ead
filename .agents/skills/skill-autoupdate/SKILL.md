---
name: skill-autoupdate
description: Protocolo de Auto-Update e Auditoria de Skills Agenticas para Manutenção Contínua. Inclui regra de escrita: toda skill em .agents/skills/ é escrita em prosa técnica concisa. Use ao criar, editar ou auditar qualquer skill de módulo (`*-maintenance`) ou seu SKILL.md.
---

# Meta-Skill: Auto-Update & Auditoria de Skills (`skill-autoupdate`)

## Overview

Meta-skill **`skill-autoupdate`** define protocolo operacional e padrões obrigatórios. Agentes de IA mantêm documentação técnica acionável em `.agents/skills/` sempre sincronizada com código-fonte da Plataforma EAD.

Regra estrita deste protocolo: **nenhuma alteração de código, esquema de banco, regra de negócio ou rota pode ser finalizada sem auditoria e atualização da skill do módulo correspondente (`[feature]-maintenance`) e das referências afetadas em `resource/`.**

---

## 1. Skill Única por Feature/Módulo (SKILL.md + referências)

Toda funcionalidade ou especificação (ex: `tenancy`, `testing`, `auth-orgs`, `courses`, `quizzes`, `certificates`, `forum`, `invitations`, `learning`, etc.) **DEVE** ter uma skill dedicada em `.agents/skills/` — um único diretório `[feature]-maintenance/` com `SKILL.md` de roteamento e o conhecimento detalhado particionado em arquivos de referência. Exceção: skills meta/genéricas sem feature própria não seguem o layout (`validate-test-quality`, `create-pull-request`, `laravel-*`, `caveman-*`, `cavecrew`, esta própria skill) — coexistem como arquivos únicos por design.

```
.agents/skills/
└── [feature]-maintenance/
    ├── SKILL.md                      <-- Descrição geral + tabela de roteamento (quando ler cada referência)
    └── resource/
        ├── architecture.md           <-- Visão Geral, Schemas, Fluxos de Dados e Regras de Arquitectura
        ├── conventions.md            <-- Padrões de Código, Code Snippets, Layout de Arquivos & Guardrails
        └── maintenance.md            <-- Guia de Manutenção, Debug, Suíte de Testes (PHPUnit/Dusk) & Edge Cases
```

### Especificação dos Componentes

1. **`SKILL.md`**:
   - `description` do frontmatter consolida os gatilhos das três referências (keywords de trigger: nomes de arquivo, módulo, classe, teste) — é o único texto sempre em contexto; mantém enxuto.
   - Corpo: visão geral do domínio (1 parágrafo) + tabela "Reference × Read when" apontando cada `resource/*.md`.

2. **`resource/architecture.md`**:
   - Mapeia visão geral do módulo, schemas de banco (tabelas, relacionamentos Eloquent, chave primária/estrangeiras), fluxos de dados, componentes arquiteturais, permissões Spatie Roles.

3. **`resource/conventions.md`**:
   - Define padrões de nomenclatura, estruturas de diretórios, snippets recomendados para Controllers, Actions, Services, Repositories, Blade Components, Módulos JavaScript SOLID.

4. **`resource/maintenance.md`**:
   - Guia prático de manutenção: procedimentos de debugging, comandos Sail de testes PHPUnit (`vendor/bin/sail artisan test --filter=...`) e Dusk (`vendor/bin/sail artisan dusk --filter=...`), estratégias de limpeza/reset de estado, mapa de edge cases conhecidos.

Referências extras vivem ao lado das obrigatórias quando o módulo pede: `resource/security.md` (tenancy), `resource/frontend-legacy.md` (histórico pré-migração no bootstrap). A auditoria exige só as três obrigatórias; extras são permitidos.

Motivo do layout: 57 skills em tríades (`[feature]-architecture`/`-conventions`/`-maintenance`) saturavam o contexto com metadados. Uma skill por módulo com progressive disclosure — `SKILL.md` carrega só o roteamento; o detalhe é lido sob demanda — corta o custo de contexto sem perder conteúdo.

---

## 2. Gatilhos de Auto-Update (Triggers)

Agente de IA **DEVE** acionar protocolo de auto-update sempre que fizer qualquer uma destas ações no repositório:

| Ação Realizada no Código | Referência(s) a Sobrescrever / Atualizar | Elementos a Atualizar |
| :--- | :--- | :--- |
| **Migrations / Models / Database Schemas** | `resource/architecture.md` | Schemas de tabela, novos campos, índices, enums, relacionamentos Eloquent. |
| **Actions / Services / Controllers / Rotas** | `resource/architecture.md`<br>`resource/conventions.md` | Regras de negócio, assinaturas de métodos, novas rotas Web/API, middlewares. |
| **Blade Views / JS Modules / CSS Styling** | `resource/architecture.md`<br>`resource/conventions.md` | Componentes UI (`<x-ui.*>`), layouts, scripts JS em `resources/js/modules/`. |
| **PHPUnit / Dusk Tests / Bugfixes** | `resource/maintenance.md` | Filtros de teste PHPUnit/Dusk, tratamento de novos edge cases, logs de erro. |

Quando o próprio `SKILL.md` ficar desincronizado (domínio mudou de escopo, gatilho deixou de casar), atualize também sua `description` e a tabela de roteamento.

---

## 3. Protocolo Operacional Passo a Passo

Ao implementar ou modificar funcionalidade na Plataforma EAD, agente segue rigorosamente esta sequência:

### Passo 1: Identificar o Módulo Alvo
Determine nome representativo da feature afetada a partir do código e dos diretórios afetados (ex: `quizzes` a partir de controllers, models e views de quizzes).

### Passo 2: Inspecionar / Criar a Skill
Verifique existência de `.agents/skills/[feature]-maintenance/` com `SKILL.md` e `resource/{architecture,conventions,maintenance}.md`:
- Referências cruzadas dentro da própria skill usam o caminho relativo: `resource/conventions.md`.
- Referências a outro módulo usam: `[feature]-maintenance` (`resource/{role}.md`).

> **Regra de Criação Inicial**: Se feature for totalmente nova e o diretório não existir, agente **DEVE** criar o diretório, o `SKILL.md` (description + tabela de roteamento) e as três referências com base na implementação recém-construída. Conteúdo novo já nasce em prosa técnica concisa (§5, guardrail 0).

### Passo 3: Auditar e Atualizar o Conteúdo
Para cada referência afetada, compare conteúdo documentado com alterações mescladas no código-fonte:
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

Auditoria de skills automatizada pelo script CLI `scripts/check-skills.php`. Auto-descobre módulos como diretórios `*-maintenance/` com `resource/` e exige `SKILL.md` + as três referências obrigatórias não-vazias.

### Comandos Principais

```bash
# Execução padrão (audita todos os módulos auto-descobertos em .agents/skills/)
php scripts/check-skills.php

# Execução no ambiente Sail
vendor/bin/sail php scripts/check-skills.php

# Auditoria de módulos específicos
php scripts/check-skills.php --modules=tenancy,testing

# Especificação de diretório customizado
php scripts/check-skills.php --dir=.agents/skills
```

---

## 5. Guardrails de Qualidade das Skills

0. **Prosa Técnica Concisa**: toda skill em `.agents/skills/` é escrita e mantida em prosa técnica concisa (aspiração, não-enforçada: skills legadas em outros estilos coexistem e continuam válidas).
   - Corta artigo (o/a/um), filler (apenas/realmente/basicamente/simplesmente), gentileza, hedging. Fragmento serve. Sinônimo curto.
   - **Comprime redação, nunca conteúdo.** Nenhuma seção, item de lista, linha de tabela, path, nome de classe/rota/seletor ou failure mode desaparece.
   - Code block, comando CLI, string de erro, identificador: verbatim.
   - Idioma do arquivo preservado — arquivo em português vira caveman em português.
   - Sem emoji decorativo em heading. Sem seta causal (→) em prosa; dentro de code block e diagrama ASCII fica como está.
   - Sem abreviação inventada (cfg/impl/req). Sigla padrão (DB, API, HTTP, UI, E2E) serve.
   - `description` do frontmatter também em caveman, mas mantém as keywords de trigger (nome de arquivo, nome de módulo, nome de classe) — é o que faz o match da skill.
   - Exceção: passo de sequência, aviso de segurança ou ação destrutiva onde cortar artigo/conjunção deixa a ordem ou a condição ambígua. Nesses trechos, frase completa.

1. **Sintaxe PHPUnit Estrita**: Todos exemplos e instruções de testes unidade/integração nas skills usam **PHPUnit** (estendendo `Tests\TestCase` ou `DuskTestCase`). Funções Pest: estritamente proibidas.
2. **Exemplos Reais do Codebase**: Nunca usar trechos hipotéticos ou genéricos. Todo snippet e schema reflete implementação real em `app/`, `database/`, `resources/`, `tests/`.
3. **Pint Code Style**: Scripts PHP utilitários de manutenção em `scripts/` seguem padrões Laravel Pint (`declare(strict_types=1);`).
4. **Referências a Testes E2E por Cadeia, Não por Módulo**: suíte `tests/Browser/` agrupada por **cadeia de ciclo de vida (jornada do usuário)**, pode cruzar módulos. Ao documentar cobertura em `resource/maintenance.md` do módulo:
   - Descreva **quais cenários estão asseverados** e em qual cadeia/etapa, não "o arquivo de teste do módulo X".
   - Nunca escreva que falta cobertura só porque não existe arquivo/método dedicado ao módulo.
   - Ao renomear/consolidar métodos Dusk, atualize menções em todas as skills afetadas — inclusive de outros módulos que a jornada atravessa.
   - Nunca instrua a declarar `DatabaseMigrations`/`RefreshDatabase` em `tests/Browser/*`: `DatabaseTruncation` vive em `Tests\DuskTestCase`.
   - Regra canônica: `testing-maintenance` (`resource/conventions.md`).
