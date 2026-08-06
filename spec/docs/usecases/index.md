# **Índice Mestre de Casos de Uso, Matriz de Rastreabilidade e Engenharia de Requisitos**

---

## **1. Visão Geral**

Este diretório contém a documentação exata de todos os **Casos de Uso (UC01 a UC23)** da Plataforma EAD Multitenant, elaborada segundo os princípios rigorosos da Engenharia de Requisitos e Engenharia de Software. Cada Caso de Uso reflete a engenharia reversa do código-fonte da aplicação (rotas, controllers, actions, middleware, form requests, blade views e componentes JS/AJAX) para garantir 100% de consistência entre a especificação e o comportamento real do sistema.

---

## **2. Catálogo de Casos de Uso por Módulo**

### **Módulo 1: Autenticação, Perfil e Multitenancy**
1. **[`UC01 — Autenticar, Encerrar Sessão e Recuperar Senha`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC01-autenticacao-logout-e-recuperacao-de-senha.md)** *(RF01, RF02 | RN08, RN12, RN13, RN14)*
2. **[`UC02 — Gestão de Perfil do Usuário`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC02-gestao-de-perfil-do-usuario.md)** *(RF01, RF04 | RN08, RN12, RN14)*
3. **[`UC03 — Gestão de Organizações e Impersonate Org`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC03-gestao-de-organizacoes-e-impersonate-org.md)** *(RF23, RF24 | RN12, RN14)*
4. **[`UC04 — Gestão de Usuários e Matrículas Manuais`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC04-gestao-de-usuarios-e-matriculas-manuais.md)** *(RF04, RF21 | RN08, RN12, RN14)*
5. **[`UC05 — Importação em Lote de Usuários via CSV`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC05-importacao-em-lote-de-usuarios-via-csv.md)** *(RF05 | RN08, RN12, RN14)*
6. **[`UC06 — Auto-cadastro e Convite Adaptativo Multi-Org`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC06-auto-cadastro-e-convite-inteligente-adaptativo.md)** *(RF03, RF21, RF25 | RN08, RN09, RN12, RN13, RN14)*

### **Módulo 2: Gestão Pedagógica e Experiência do Aluno**
7. **[`UC07 — Gestão Multitenant de Cursos, Módulos e Reordenacao`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC07-gestao-de-cursos-modulos-e-reordenacao.md)** *(RF06 | RN08, RN11, RN12, RN14)*
8. **[`UC08 — Gestão de Lições Multimídia e Sanitização`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC08-gestao-de-licoes-multimidia-e-sanitizacao.md)** *(RF07 | RN08, RN12, RN14)*
9. **[`UC09 — Consumo de Aulas, Sala de Aula e Players`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC09-consumo-de-aulas-sala-de-aula-e-players.md)** *(RF13, RF14, RF20 | RN08)*
10. **[`UC10 — Registro e Rastreamento de Progresso`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC10-registro-e-rastreamento-de-progresso.md)** *(RF15 | RN01, RN08, RN14)*

### **Módulo 3: Avaliações e Certificados**
11. **[`UC11 — Gestão de Questionários, Provas e Correção Manual`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC11-gestao-de-questionarios-e-provas.md)** *(RF08, RF28 | RN02, RN03, RN04, RN08, RN12, RN14)*
12. **[`UC12 — Realização de Questionários pelo Aluno`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC12-realizacao-de-questionarios-pelo-aluno.md)** *(RF09 | RN02, RN03, RN04, RN08, RN14)*
13. **[`UC13 — Configuração de Regras e Emissão de Certificado`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC13-configuracao-de-regras-e-emissao-de-certificado.md)** *(RF10, RF16, RF25 | RN01, RN07, RN08, RN12, RN13, RN14)*
14. **[`UC14 — Validação Pública e Revogação de Certificados`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC14-validacao-publica-e-revogacao-de-certificados.md)** *(RF17 | RN07, RN08, RN12, RN14)*

### **Módulo 4: Comunicação, Ajuda e Analytics**
15. **[`UC15 — Fórum de Discussão, Histórico e Moderação`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC15-forum-de-discussao-historico-e-moderacao.md)** *(RF22, RF25, RF30 | RN08, RN10, RN12, RN13, RN14, RN15)*
16. **[`UC16 — Landing Page e Central de Ajuda Integral`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC16-landing-page-e-central-de-ajuda-integral.md)** *(RF11, RF12 | RN05, RN08, RN12)*
17. **[`UC17 — Dashboard Gerencial e Exportação CSV Streaming`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC17-dashboard-gerencial-e-exportacao-csv-streaming.md)** *(RF18 | RN08, RN12, RN14)*
18. **[`UC18 — Configurações do Sistema Globais e por Org`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC18-configuracoes-do-sistema-globais-e-por-org.md)** *(RF27 | RN12, RN14)*
19. **[`UC19 — Central de Notificações In-App e E-mail`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC19-central-de-notificacoes-in-app-e-email.md)** *(RF25 | RN08, RN12, RN13, RN14)*

### **Módulo 5: Infraestrutura, Segurança e Governança**
20. **[`UC20 — Logs de Auditoria, Monitoramento e Expurgo`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC20-logs-de-auditoria-monitoramento-e-expurgo.md)** *(RF31, RF32, RF33 | RN14)*
21. **[`UC21 — Suíte de Testes, Environment Dusk e CI/CD`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC21-suite-de-testes-environment-dusk-e-ci-cd.md)** *(RF19 | RN06)*
22. **[`UC22 — Povoamento Automatizado do Banco (Seeders)`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC22-povoamento-automatizado-do-banco-seeders.md)** *(RF29 | RN16)*
23. **[`UC23 — Menu de Navegação Dinâmico e Controle de Acesso`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC23-menu-de-navegacao-dinamico-e-controle-de-acesso.md)** *(RF26 | RN08, RN12)*

---

## **3. Matriz Completa de Rastreabilidade Cruzada (RF vs RN vs UC)**

> **Garantia de Cobertura 100%:** Cada Requisito Funcional (RF) está vinculado a NO MÍNIMO uma Regra de Negócio (RN) e coberto por NO MÍNIMO um Caso de Uso (UC).

| Requisito Funcional (RF) | Regras de Negócio Vinculadas (RN) | Casos de Uso Associados (UC) |
| :--- | :--- | :--- |
| **RF01** (Autenticação) | RN08, RN12, RN14 | UC01, UC02 |
| **RF02** (Recuperação de Senha) | RN13, RN14 | UC01 |
| **RF03** (Convites Adaptativos) | RN08, RN09, RN12, RN14 | UC06 |
| **RF04** (Gestão Usuários) | RN08, RN12, RN14 | UC02, UC04 |
| **RF05** (Importação CSV) | RN08, RN12, RN14 | UC05 |
| **RF06** (Cursos e Módulos) | RN08, RN11, RN12, RN14 | UC07 |
| **RF07** (Conteúdo Multimídia) | RN08, RN12, RN14 | UC08 |
| **RF08** (Gestão Questionários) | RN02, RN03, RN04, RN08, RN12, RN14 | UC11 |
| **RF09** (Execução Questionários) | RN02, RN03, RN04, RN08, RN14 | UC12 |
| **RF10** (Regras Certificado) | RN01, RN08, RN12, RN14 | UC13 |
| **RF11** (Landing Page) | RN05, RN08 | UC16 |
| **RF12** (Central de Ajuda 100%) | RN05, RN08, RN12 | UC16 |
| **RF13** (Player Vídeo) | RN08 | UC09 |
| **RF14** (Leitor PDF) | RN08 | UC09 |
| **RF15** (Registro Progresso) | RN01, RN08, RN14 | UC10 |
| **RF16** (Emissão Certificado PDF) | RN01, RN07, RN08, RN12, RN13, RN14 | UC13 |
| **RF17** (Validação Pública & Revogação) | RN07, RN08, RN12, RN14 | UC14 |
| **RF18** (Dashboard & Exportação Streaming) | RN08, RN12, RN14, RNF06 | UC17 |
| **RF19** (Suíte Testes & Quality Gate) | RN06 | UC21 |
| **RF20** (Controle Matrícula/Org) | RN08, RN12 | UC09 |
| **RF21** (Matrículas Manuais) | RN08, RN12, RN14 | UC04, UC06 |
| **RF22** (Fórum Discussão) | RN08, RN10, RN12, RN14, RN15 | UC15 |
| **RF23** (Gestão Organizações) | RN12, RN14 | UC03 |
| **RF24** (Impersonate Org) | RN12, RN14 | UC03 |
| **RF25** (Central Notificações) | RN08, RN12, RN13, RN14 | UC06, UC13, UC15, UC19 |
| **RF26** (Menu Navegação Dinâmico) | RN08, RN12 | UC23 |
| **RF27** (Configurações do Sistema) | RN12, RN14 | UC18 |
| **RF28** (Correção Manual Dissertativas) | RN02, RN08, RN12, RN14 | UC11 |
| **RF29** (Database Seeders) | RN16 | UC22 |
| **RF30** (Histórico Edições Fórum) | RN08, RN10, RN15 | UC15 |
| **RF31** (Auditoria Mutações) | RN14 | UC20 |
| **RF32** (Auditoria Eventos Críticos & LGPD) | RN14 | UC20 |
| **RF33** (Painel Auditoria & Expurgo) | RN14 | UC20 |

---

## **4. Matriz de Cobertura por Regra de Negócio (RN)**

| Regra de Negócio (RN) | Requisitos Funcionais Associados | Casos de Uso que Enforcam a Regra |
| :--- | :--- | :--- |
| **RN01** (Liberação Certificado) | RF10, RF15, RF16 | UC10, UC13 |
| **RN02** (Cálculo Nota & Dissertativas) | RF08, RF09, RF28 | UC11, UC12 |
| **RN03** (Tentativas Questionários) | RF08, RF09 | UC11, UC12 |
| **RN04** (Exibição Gabarito) | RF08, RF09 | UC11, UC12 |
| **RN05** (Ajuda 100% Telas & Fallback) | RF11, RF12 | UC16 |
| **RN06** (Guardrail Cobertura 95%) | RF19 | UC21 |
| **RN07** (Hash SHA-256 & Revogação Lógica) | RF16, RF17 | UC13, UC14 |
| **RN08** (Restrição Matrícula & Org) | RF01, RF03, RF04, RF05, RF06, RF07, RF08, RF09, RF10, RF11, RF12, RF13, RF14, RF15, RF16, RF17, RF18, RF20, RF21, RF22, RF25, RF26, RF28, RF30 | UC01, UC02, UC04, UC05, UC06, UC07, UC08, UC09, UC10, UC11, UC12, UC13, UC14, UC15, UC16, UC17, UC19, UC23 |
| **RN09** (Convite Adaptativo Multi-Org) | RF03 | UC06 |
| **RN10** (Isolamento Fórum) | RF22, RF30 | UC15 |
| **RN11** (Guard Exclusão Cursos) | RF06 | UC07 |
| **RN12** (Impersonate Org & `UnresolvedOrgContextException`) | RF01, RF03, RF04, RF05, RF06, RF07, RF08, RF10, RF12, RF16, RF17, RF18, RF20, RF21, RF22, RF23, RF24, RF25, RF26, RF27, RF28 | UC01, UC02, UC03, UC04, UC05, UC06, UC07, UC08, UC11, UC13, UC14, UC15, UC16, UC17, UC18, UC19, UC23 |
| **RN13** (Isolamento E-mail try/catch) | RF02, RF16, RF22, RF25 | UC01, UC06, UC13, UC15, UC19 |
| **RN14** (Mascaramento LGPD & Retenção Auditoria) | RF01, RF02, RF03, RF04, RF05, RF06, RF07, RF08, RF09, RF10, RF15, RF16, RF17, RF18, RF21, RF22, RF23, RF24, RF25, RF27, RF28, RF31, RF32, RF33 | UC01, UC02, UC03, UC04, UC05, UC06, UC07, UC08, UC10, UC11, UC12, UC13, UC14, UC15, UC17, UC18, UC19, UC20 |
| **RN15** (Histórico Edições Fórum) | RF22, RF30 | UC15 |
| **RN16** (Idempotência & Seeders) | RF29 | UC22 |
