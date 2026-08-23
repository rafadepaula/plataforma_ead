# **Índice Geral de Especificações Técnicas Multitenant, Ordem Sistemática TDD e Roadmap**

---

## **1. Ordem Sistemática TDD (Test-Driven Development) & Novo Design System**

A ordem de execução foi configurada com base no princípio cardinal do **TDD (Test-Driven Development)**. A infraestrutura de testes, regras de negócio e a migração completa para o **Plataforma EAD Design System (Material Bootstrap)** estão mapeadas sequencialmente:

```mermaid
graph TD
    subgraph Fase 0: Arquitetura, DB e Fundação de Testes
        S00[SPEC-00: Arquitetura, DB Multitenant & Guardrails] --> S01[SPEC-01: Infraestrutura TDD, Dusk E2E & CI/CD]
        S01 --> S03[SPEC-03: Agentic Harness & Auto-Update Skills]
        S01 --> S14[SPEC-14: Ambiente Dusk & Banco MySQL Dedicado]
        S00 --> S16[SPEC-16: Povoamento DB Seeders por Ambiente]
    end

    subgraph Fase 1: Módulos Backend & Domínio Base
        S03 --> S04[SPEC-04: Auth, Organizações, Impersonate & Usuários]
        S04 --> S05[SPEC-05: Gestão de Cursos, Módulos & Lições]
        S05 --> S06[SPEC-06: Convites Inteligentes & Matrículas Multi-Org]
        S05 --> S07[SPEC-07: Experiência do Aluno, Sala de Aula & Progresso]
        S07 --> S08[SPEC-08: Motor de Questionários & Provas]
        S08 --> S09[SPEC-09: Certificados Multitenant & Validação Pública]
        S05 --> S10[SPEC-10: Fórum de Discussão com Polling AJAX]
        S04 --> S11[SPEC-11: Landing Page & Central de Ajuda 100% Telas]
        S09 --> S12[SPEC-12: Dashboard Gerencial, Exportação CSV & Settings]
        S06 --> S13[SPEC-13: Notificações Multitenant E-mail + In-App]
        S04 --> S15[SPEC-15: Logs de Auditoria & Monitoramento Multitenant]
        S04 --> S18[SPEC-18: Gestão de Perfil do Usuário]
    end

    subgraph Fase 2: Design System Material Bootstrap & Refatoração de Telas
        S01 --> S19[SPEC-19: Tokens CSS, Build & 30 Componentes Blade]
        S19 --> S20[SPEC-20: Shell Global, App Bar, Drawer Mobile & Notificações]
        
        S20 --> S21[SPEC-21: Dashboard Gerencial, Métricas & Exportação CSV]
        S20 --> S22[SPEC-22: Catálogo de Cursos & Gestão com Proteção de Matrículas]
        S22 --> S23[SPEC-23: Trilha de Aprendizado: Módulos, Lições & Drag-and-Drop]
        S23 --> S24[SPEC-24: Autoria de Provas, Regras & Construtor de Questões]
        S24 --> S25[SPEC-25: Fila de Correção de Dissertativas & Painel de Avaliação]

        S20 --> S26[SPEC-26: Meus Cursos: Abas Segmentadas & Cards Ricos]
        S26 --> S27[SPEC-27: Sala de Aula: Visão da Trilha, Progresso & Certificado]
        S27 --> S28[SPEC-28: Player de Lições Unificado: Vídeo, PDF, Texto & AJAX]
        S28 --> S29[SPEC-29: Realização de Provas: Timer ao Vivo & Submissão Atômica]
        S27 --> S30[SPEC-30: Fórum do Curso: Tópicos, FAB & Polling em Tempo Real]

        S20 --> S31[SPEC-31: Landing Page Pública & Vitrine de Componentes]
        S20 --> S32[SPEC-32: Shell de Acesso, Login & Convite Inteligente Adaptativo]
    end
```

---

## **2. Repositório de Especificações Técnicas (`spec/specs/`)**

### 2.1 Backend, Domínio e Infraestrutura
1. **[`00-architecture-database-and-guardrails.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/00-architecture-database-and-guardrails.md)**: Arquitetura Geral Multitenant, tabela `organizations`, esquema de banco de dados (19 tabelas), Trait `OrgScope`, Roles Spatie (`admin`, `gestor`, `aluno`), 95%+ de Cobertura e **Testes E2E com Laravel Dusk (100% de aprovação)**.
2. **[`01-testing-guardrails-dusk-e2e-and-ci-cd.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/01-testing-guardrails-dusk-e2e-and-ci-cd.md)**: **TDD Infraestructure & CI/CD Quality Gate**: Configuração do ambiente de testes em memória (SQLite), drivers do **Laravel Dusk E2E**, script auditor CLI (`scripts/check-coverage.php`) e esteira CI/CD.
3. **[`02-frontend-design-system-blade-components-and-layout.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/02-frontend-design-system-blade-components-and-layout.md)**: Frontend Architecture legada (Modernist).
4. **[`03-agentic-harness-and-self-updating-skills.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/03-agentic-harness-and-self-updating-skills.md)**: **Agentic Harness & Auto-Update Protocol**: Exigência de no mínimo 3 Skills por feature (`architecture`, `conventions`, `maintenance`) e protocolo de auto-update.
5. **[`04-auth-profile-organizations-and-user-management.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/04-auth-profile-organizations-and-user-management.md)**: Autenticação, Perfil, CRUD de Organizações, Impersonate Org pelo Admin, CRUD de Alunos/Gestores e Importação CSV em Lote.
6. **[`05-courses-modules-and-content-management.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/05-courses-modules-and-content-management.md)**: Gestão Multitenant de Cursos, Módulos com reordenação AJAX e Lições Multimídia.
7. **[`06-smart-invitation-and-enrollment-system.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/06-smart-invitation-and-enrollment-system.md)**: Gerenciamento de Links de Convite por Org, Auto-cadastro e Fluxo Adaptativo Multi-Org para Alunos (RN09).
8. **[`07-student-learning-experience-and-progress.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/07-student-learning-experience-and-progress.md)**: Área do Aluno "Meus Cursos" (Multi-Org), Sala de Aula, Players, Conclusão de Aulas e Middleware `EnsureStudentIsEnrolled`.
9. **[`08-quizzes-and-evaluations-engine.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/08-quizzes-and-evaluations-engine.md)**: Motor de Questionários, Player de Provas, Correção Automática, Retries e Gabarito Condicional.
10. **[`09-certificates-and-public-verification.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/09-certificates-and-public-verification.md)**: Regras de Liberação de Certificados, Geração de PDF com marca da Organização, Hash SHA-256 Imutável e Validação Pública Global (`/validar-certificado`).
11. **[`10-course-discussion-forum.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/10-course-discussion-forum.md)**: Fórum de Discussão do Curso isolado por `org_id` e matrícula, Sanitização XSS e Polling AJAX.
12. **[`11-landing-page-and-contextual-help-center.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/11-landing-page-and-contextual-help-center.md)**: Landing Page Pública, Central de Ajuda Integral com componente `<x-help-button key="..." />` cobrindo 100% das telas.
13. **[`12-admin-dashboard-analytics-and-system-settings.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/12-admin-dashboard-analytics-and-system-settings.md)**: Dashboard Gerencial por Org, Central de Exportação CSV em Streaming $O(1)$ de RAM e Configurações Globais/Por Org.
14. **[`13-notifications-and-alerts.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/13-notifications-and-alerts.md)**: Notificações Multitenant (E-mail + In-App/sino), gatilhos fechados.
15. **[`14-dusk-testing-environment-and-dedicated-database.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/14-dusk-testing-environment-and-dedicated-database.md)**: Ambiente de Testes Laravel Dusk e Banco de Dados MySQL Dedicado (`testing`).
16. **[`15-system-audit-logging-and-monitoring.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/15-system-audit-logging-and-monitoring.md)**: Sistema de Logs de Auditoria e Monitoramento Multitenant.
17. **[`16-database-seeders-and-environment-seeding.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/16-database-seeders-and-environment-seeding.md)**: Povoamento Automatizado do Banco de Dados (Database Seeders) por Ambiente com Isolamento Multitenant.
18. **[`17-dynamic-navigation-menu-and-access-control.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/17-dynamic-navigation-menu-and-access-control.md)**: Menu de Navegação Dinâmico e Controle de Acesso por Role.
19. **[`18-user-profile-management.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/18-user-profile-management.md)**: Gestão de Perfil do Usuário (UC02) e regra `App\Rules\Cpf`.

---

### 2.2 Novo Design System (Material Bootstrap) & Refatoração Completa de Telas
20. **[`19-design-system-foundation-and-components.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/19-design-system-foundation-and-components.md)**: Fundação de Tokens CSS, Build Pipeline e Inventário dos 30 Componentes Blade padronizados (sem vermelho/laranja/amarelo, Nunito Sans 16px, cantos suaves, sombras azuis).
21. **[`20-application-shell-and-navigation-drawer.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/20-application-shell-and-navigation-drawer.md)**: Shell Global da Aplicação, Topbar de 76px (60px mobile), Drawer Lateral de 280px (gaveta deslizante de 264px com 220ms no mobile) e Dropdown de Notificações.
22. **[`21-gestor-dashboard-and-analytics.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/21-gestor-dashboard-and-analytics.md)**: Dashboard Gerencial do Gestor, 4 StatCards com deltas comparativos, grade 2fr/1fr com matrículas recentes (progresso 8px), card de atenção a pendências e exportação CSV em streaming.
23. **[`22-gestor-courses-catalog-and-management.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/22-gestor-courses-catalog-and-management.md)**: Catálogo de Cursos do Gestor (`courses/index.blade.php`), DataTable responsiva, 4 ações de linha, FilterBar e proteção rígida contra exclusão de cursos com alunos matriculados.
24. **[`23-trail-builder-modules-and-lessons.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/23-trail-builder-modules-and-lessons.md)**: Montador de Trilhas e Lições, reordenação drag-and-drop AJAX contínua (`ModuleReorder.js`), dropzone multi-arquivos com preview e remoção, e preview live 16:9 de YouTube em pastel wash.
25. **[`24-quiz-authoring-and-question-builder.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/24-quiz-authoring-and-question-builder.md)**: Autoria de Provas e Questões (`quizzes/edit.blade.php`), modais elevados in-place, 4 tipos de questões (*Única*, *Múltipla*, *V/F*, *Dissertativa*) e clonagem dinâmica de opções via `<template>` com `__INDEX__`.
26. **[`25-essay-grading-queue-and-evaluation.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/25-essay-grading-queue-and-evaluation.md)**: Fila de Correção de Dissertativas (FIFO) e Painel de Avaliação (`quizzes/attempts/show.blade.php`), superfície afundada de resposta, vereditos rádio menta/slate e barra dinâmica de progresso de correção.
27. **[`26-student-course-catalog-meus-cursos.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/26-student-course-catalog-meus-cursos.md)**: Catálogo do Aluno "Meus Cursos" (`student/courses/index.blade.php`), abas segmentadas (*Em andamento*, *Concluídos*, *Todos*), cards ricos com pastel wash de 168px e botões de ação contextual (*Continuar*, *Começar*, *Baixar certificado*).
28. **[`27-classroom-overview-and-progression.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/27-classroom-overview-and-progression.md)**: Sala de Aula do Aluno (`classroom/show.blade.php`), grade 8/4 responsiva, ícones circulares de estado de 44px (menta com check / azul com mídia), card lateral de progresso e cartão de certificado.
29. **[`28-unified-lesson-player-and-multimedia.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/28-unified-lesson-player-and-multimedia.md)**: Player de Lições Unificado (`classroom/lesson.blade.php`), despacho estrito para 4 formatos (*Vídeo*, *PDF*, *Texto/Imagem*, *Prova*), rastreamento assíncrono de vídeo a cada 5s com auto-conclusão a 90% (`LessonPlayer.js`) e conclusão manual via classes CSS.
30. **[`29-student-quiz-taking-and-realtime-evaluation.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/29-student-quiz-taking-and-realtime-evaluation.md)**: Realização de Prova pelo Aluno (`student/quizzes/show.blade.php`), cronômetro em tempo real `QuizTimer.js`, coluna focada de 760px, opções de clique amplo e submissão atômica única em `POST student.quizzes.submit`.
31. **[`30-course-discussion-forum-and-realtime-polling.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/30-course-discussion-forum-and-realtime-polling.md)**: Fórum de Discussão do Curso (`forum/index.blade.php`), cards interativos, FAB flutuante no mobile, modal de publicação rápida e polling incremental assíncrono a cada 10s via `since_id` (`ForumPolling.js`).
32. **[`31-public-landing-page-and-showcase.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/31-public-landing-page-and-showcase.md)**: Landing Page Pública (`landing/show.blade.php`), faixas alternadas branco/azul `--blue-50`, Hero de 36px de raio, vitrine com componentes reais do sistema e fluxo em 4 passos.
33. **[`32-auth-guest-layout-and-smart-invitation.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/specs/32-auth-guest-layout-and-smart-invitation.md)**: Autenticação, Shell de Visitante e Convite Inteligente Adaptativo (`auth/login.blade.php`, `convite/show.blade.php`), split screen institucional em `--blue-100` e formulário adaptativo com verificação assíncrona de e-mail (`SmartInvitationForm.js`).

---

## **3. Documentação da Arquitetura Multitenant**

O estudo completo e o alinhamento de design do Multitenancy estão salvos em:
👉 **[`spec/docs/multitenancy.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/multitenancy.md)**
