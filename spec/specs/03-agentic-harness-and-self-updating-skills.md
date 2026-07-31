# **03. Agentic Harness, Skills de Manutenção e Protocolo de Auto-Update das Especificações**

---

## **1. Visão Geral e Objetivo da Harness Agentica**

A especificação de **Agentic Harness e Auto-Update de Skills** define as diretrizes obrigatórias para que a base de código da Plataforma EAD possua documentação acionável e contextual via **Skills Agenticas** (localizadas em `.agents/skills/` ou `spec/skills/`).

O objetivo central é garantir a **autossuficiência contínua dos agentes de Inteligência Artificial** na manutenção do sistema:
1. **Cobrança por Feature (Mínimo 3 Skills):** Toda feature desenvolvida no sistema deve, **obrigatoriamente**, vir acompanhada de no mínimo **3 Skills de Harness** detalhando o funcionamento, padrões de código e procedimentos de manutenção daquela funcionalidade.
2. **Protocolo Obrigatório de Auto-Update:** Sempre que um agente realizar uma alteração, refatoração ou extensão de código que modifique comportamentos, assinaturas, schemas de banco ou rotas de uma feature, ele **DEVE atualizar e sobrescrever automaticamente as Skills correspondentes** antes de finalizar a tarefa.

---

## **2. Estrutura Obrigatória de 3 Skills por Feature**

Para cada módulo ou feature implementada, o desenvolvedor/agente deve construir e manter exatamente 3 tipos de skills:

```
  .agents/skills/
  ├── [feature]-architecture/SKILL.md   <-- 1. Visão Geral, Schemas, Fluxos & Regras
  ├── [feature]-conventions/SKILL.md    <-- 2. Padrões de Código, Code Snippets & Guardrails
  └── [feature]-maintenance/SKILL.md    <-- 3. Guia de Manutenção, Debug, Testes & Edge Cases
```

---

## **3. Protocolo Obrigatório de Auto-Update de Skills**

- **Sobrescreção Automática de Skills:** Sempre que um agente alterar código, esquemas de banco de dados, assinaturas de métodos ou regras de negócio que afetem uma funcionalidade, ele **DEVE atualizar e sobrescrever automaticamente as Skills correspondentes** daquela feature antes de marcar a tarefa como concluída.
- **Meta-Skill `skill-autoupdate`:** Implementação da meta-skill `.agents/skills/skill-autoupdate/SKILL.md` que instrui qualquer agente a auditar e atualizar as skills após alterações de código.

---

## **4. Checklist de Implementação & Testes**

- [ ] Meta-Skill `skill-autoupdate` em `.agents/skills/skill-autoupdate/SKILL.md`
- [ ] Auditar presença das 3 skills obrigatórias para todas as especificações funcionais do projeto
- [ ] Integrar checagem no pipeline de CI/CD para garantir documentação sincronizada com a base de código.
