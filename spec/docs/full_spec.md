# **Documento de Especificação Técnica, Requisitos e Casos de Uso: Plataforma EAD Multitenant**

---

## **1. Análise de Escopo e Visão Geral do Sistema**

O objetivo deste projeto é o desenvolvimento de uma plataforma de **Ensino a Distância (EAD) Multitenant (Single-Database)** de alta eficiência, arquitetada para a capacitação continuada e gestão educacional por **Organizações (`organizations`)**. A solução combina baixo custo de infraestrutura (compatível 100% com hospedagem compartilhada PHP 8.5+ e MariaDB/MySQL) com um rígido controle de isolamento tenant por organização, gestão de papéis/permissões granular (Admin Global, Gestor de Organização e Aluno Capacitando), acompanhamento pedagógico detalhado, motor de avaliações flexível (incluindo questões objetivas e dissertativas), fórum de discussão interativo com histórico de edições e fila de moderação, notificações in-app e por e-mail, suporte contextual integral, auditoria contínua de segurança e emissão automatizada de certificados com verificação pública e revogação lógica.

### **1.1. Pilares Fundamentais do Escopo Atualizado**

1. **Multitenancy Single-Database com Trait `OrgScope` & Impersonation:**
   * A aplicação suporta múltiplas Organizações (`organizations`). Cada tenant possui seus próprios cursos, convites, alunos/gestores vinculados, fóruns, logs de auditoria e configurações.
   * O isolamento de dados é garantido automaticamente no ORM Eloquent através da Trait **`OrgScope`**.
   * O Administrador Global (`role:admin`) possui acesso cross-tenant e pode alternar seu contexto operacional para qualquer organização cadastrada via **Impersonate Org** (armazenado em `session('active_org_id')`). Tentar criar registros escopados sem contexto ativo lança a exceção `UnresolvedOrgContextException` (traduzida em erro HTTP 422).

2. **Matrícula Inteligente, Controle de Acesso e Links Adaptativos:**
   * O acesso a cursos e salas de aula é estritamente restrito por matrícula ativa na tabela pivô `course_user` e validado pelo Middleware `EnsureStudentIsEnrolled`.
   * Matrículas ocorrem por vinculação manual (realizada por Gestor/Admin) ou via **Links de Convite (`invitation_links`)**.
   * **Fluxo Adaptativo Multi-Org (RN09):** Quando um aluno clica em um convite e informa um e-mail já existente no sistema, a interface adapta-se para solicitar a senha atual da conta. Ao autenticar, o sistema realiza a vinculação do aluno ao novo curso daquela Organização **sem duplicar o registro na tabela `users`**.

3. **Sala de Aula Virtual, Player Sanitizado e Progresso em Tempo Real:**
   * Suporte a conteúdos em Texto (Rich Text), Imagens, Documentos PDF (exibidos em iframe incorporado com download restrito) e Vídeos do YouTube.
   * As URLs de vídeo são sanitizadas pela classe `YouTubeSanitizerService` para injetar parâmetros de privacidade e controle (`?modestbranding=1&rel=0&controls=1&disablekb=1`).
   * O progresso do aluno é calculated dinamicamente em tempo real via AJAX e gravado na tabela `lesson_progress` através de 3 fontes de conclusão (`completion_source`): clique manual, visualização de 90% do vídeo (`video_threshold`) ou aprovação em questionário vinculado (`quiz_passed`).

4. **Motor de Questionários, Avaliações e Correção Manual:**
   * Suporte a 4 tipos de questões em `quiz_questions`: Escolha Única (`single_choice`), Múltipla Escolha (`multiple_choice`), Verdadeiro/Falso (`true_false`) e Dissertativa (`essay`).
   * Correção automática para questões objetivas e V/F. Questões dissertativas alteram o status da tentativa para `awaiting_manual_grading`, disponibilizando painel para correção e atribuição de nota manual pelo Gestor ou Admin.
   * Controle de tentativas máximas (`max_attempts`), limite de tempo para execução (`time_limit_minutes`), permissão de refaça (`allow_retries`) e exibição condicional de gabarito (`show_correct_answers`).

5. **Certificação Automatizada, Hash SHA-256 e Validação Pública Global:**
   * Regras flexíveis configuráveis em `course_completion_rules` (`all_lessons`, `min_quiz_score`, `specific_module`).
   * Quando todas as regras são satisfeitas, o sistema gera o certificado em PDF via `barryvdh/laravel-dompdf` com a identidade visual (logo e cores) da Organização responsável.
   * Cada certificado recebe um código único **Hash SHA-256 imutável** calculado pela fórmula: `hash('sha256', "cert_{user_id}_{course_id}_{issued_at_ISO}_{APP_KEY}")`.
   * A validação pública ocorre em `/validar-certificado/{hash}` por consulta direta ao código ou leitura do **QR Code**. Certificados revogados possuem revogação lógica imutável (`revoked_at`, `revoked_by`, `revoke_reason`), permanecendo consultáveis com tarja de revogado (jamais retornam erro 404).

6. **Fórum de Discussão do Curso, Histórico Público de Edições e Denúncias:**
   * Fórum integrado por curso e isolado por `org_id` e matrícula ativa.
   * Sanitização contra ataques XSS em todas as publicações via `ForumContentSanitizerService`.
   * **Histórico Público de Edições (RN15):** Qualquer alteração em tópico ou resposta gera um snapshot na tabela `forum_post_edits`, permitindo que os alunos consultem a versão original e o histórico de alterações.
   * **Fila de Moderação:** Alunos podem denunciar publicações inadequadas (`forum_reports`). Gestores e Admins possuem painel de moderação para analisar e remover conteúdos impróprios.

7. **Landing Page Pública e Central de Ajuda Integral (100% Cobertura):**
   * Landing Page pública (`/`) apresentando a plataforma, seus benefícios e atalhos de acesso.
   * **Central de Ajuda (RN05):** Cobertura obrigatória de 100% das rotas e páginas da aplicação através do componente Blade `<x-help-button key="..." />`. O serviço `HelpArticleResolverService` resolve dinamicamente artigos específicos da Organização ou recorre ao artigo global (fallback).

8. **Dashboard Gerencial, Métricas e Central de Exportação em Streaming:**
   * Dashboards dedicados (`/admin/dashboard` e `/gestor/dashboard`) com 4 cards de métricas (Cursos, Alunos, Matrículas e Certificados Emitidos) e tabela de matrículas recentes.
   * **Exportação CSV em Streaming (RNF06):** Relatórios de alunos, matrículas e certificados são gerados via `StreamedResponse` com consumo de memória constante $O(1)$, evitando estouro de memória em datasets volumosos em hospedagem compartilhada.

9. **Central de Notificações Multitenant In-App e por E-mail:**
   * Notificações In-App (dropdown no sino do topbar com contador de não lidas e polling AJAX) e envios por e-mail via SMTP.
   * 4 gatilhos fechados: Convite de Matrícula Recebido, Certificado Emitido, Nova Resposta no Fórum e Matrícula Confirmada.
   * Envio de e-mail isolado em blocos `try/catch` para garantir que falhas de servidor SMTP jamais causem rollback em transações do banco de dados (RN13).

10. **Logs de Auditoria, Monitoramento e Mascaramento LGPD:**
    * Registro de mutações de banco de dados (`AuditableTrait` / `AuditObserver`) e eventos críticos (`login.success`, `login.failed`, `impersonate.start`, `csv.import`, `essay.graded`, `certificate.issued`, `certificate.revoked`).
    * **Mascaramento de Credenciais (RN14):** Senhas são obrigatoriamente mascaradas como `[REDACTED]`.
    * Duplo armazenamento: Tabela MySQL `audit_logs` + arquivo de log Monolog em `storage/logs/audit.log`. Expurgo automático de registros antigos via `php artisan audit-logs:prune`.

11. **Navegação Dinâmica, Componentes Blade e Seeders por Ambiente:**
    * Menu lateral e topbar construídos dinamicamente via `NavigationRegistry`, `NavigationService` e `NavigationComposer`, filtrando itens estritamente conforme a Role (`admin`, `gestor`, `aluno`).
    * Coleção de componentes Blade reutilizáveis (`<x-ui.card>`, `<x-ui.button>`, `<x-ui.badge>`, `<x-ui.stat-card>`, `<x-ui.table>`) baseados no Modernist Design System.
    * Povoamento do banco de dados via `DatabaseSeeder` idempotente (`firstOrCreate`/`updateOrCreate`), com isolamento estrito entre ambientes (dados fictícios gerados apenas em desenvolvimento/testes).

12. **Guardrails de Qualidade, Suíte de Testes (95%+) e Environment Dusk:**
    * Suíte completa de testes backend (PHPUnit) e testes E2E de navegador (Laravel Dusk).
    * Execução dos testes Dusk em ambiente MySQL isolado (`testing`) via `.env.dusk.local`.
    * Script auditor CLI (`scripts/check-coverage.php`) que bloqueia a esteira CI/CD caso a cobertura global de código fique abaixo de **95%**.

13. **Escopo Explícito — Ausência de Módulo Financeiro:**
    * A plataforma **não possui módulo de pagamentos, cobrança, billing ou assinaturas**. A coluna `organizations.cnpj` é mantida exclusivamente como dado cadastral/institucional (exibido na assinatura do certificado).

---

## **2. Arquitetura Técnica, Modelo de Dados e Infraestrutura**

### **2.1. Arquitetura da Aplicação**

* **Backend:** **Laravel 13** executando sobre **PHP 8.5**, estruturado no padrão MVC tradicional com Form Requests para validação, sessões nativas com cookies sanitizados, Laravel Policies/Gates combinados com **`spatie/laravel-permission` (`^6.10`)** para autorização de acesso, e Eloquent ORM com a trait global **`OrgScope`**.
* **Frontend:** **Laravel Blade + JavaScript Vanilla (ES6+) & jQuery 3.7+**.
  * Blade Templates para renderização server-side (SSR) das páginas e layouts.
  * jQuery e JavaScript puro para componentes interativos, requisições AJAX, controle do player de vídeo, modais, player de provas e polling do fórum/notificações.
  * Estilização baseada em **Bootstrap 5.3** customizado com tokens CSS do **Modernist Design System** (`:root`).
* **Processamento de Documentos:** `barryvdh/laravel-dompdf` para renderização server-side de certificados PDF e `simplesoftwareio/simple-qrcode` para geração de QR Codes.
* **Infraestrutura e Hospedagem Compartilhada:**
  * Execução compatível com servidores Apache/Nginx em hospedagem compartilhada.
  * Fila de tarefas leve: `QUEUE_CONNECTION=sync` ou `database` via Cron.
  * Proteção do diretório de uploads (`storage/app/public`) através de `.htaccess` bloqueando listagem e execução de scripts PHP.

---

### **2.2. Modelo de Banco de Dados Relacional (23 Tabelas)**

1. **`organizations`** 🗑️: `id` PK, `name`, `slug` UNIQUE, `cnpj` UNIQUE (cadastral), `logo_path`, `primary_color`, `secondary_color`, `status`, `deleted_at`, `timestamps`.
2. **`users`**: `id` PK, `org_id` FK -> `organizations.id` (nullable), `name`, `email` UNIQUE, `cpf` UNIQUE, `password` (bcrypt), `status`, `remember_token`, `timestamps`.
3. **`courses`** 🗑️: `id` PK, `org_id` FK -> `organizations.id`, `title`, `description`, `workload_hours`, `is_published`, `deleted_at`, `timestamps`.
4. **`course_user`**: `id` PK, `user_id` FK -> `users.id`, `course_id` FK -> `courses.id`, `enrolled_at`, `status`, `progress_percentage`, `completed_at`, `timestamps`. UNIQUE(`user_id`, `course_id`).
5. **`modules`** 🗑️: `id` PK, `course_id` FK -> `courses.id`, `title`, `description`, `order_index`, `deleted_at`, `timestamps`.
6. **`lessons`** 🗑️: `id` PK, `module_id` FK -> `modules.id`, `title`, `type`, `content_text`, `youtube_url`, `pdf_path`, `image_path`, `order_index`, `is_published`, `deleted_at`, `timestamps`.
7. **`quizzes`**: `id` PK, `lesson_id` FK -> `lessons.id` UNIQUE, `title`, `instructions`, `allow_retries`, `max_attempts`, `time_limit_minutes`, `show_correct_answers`, `min_score_percentage`, `timestamps`.
8. **`quiz_questions`**: `id` PK, `quiz_id` FK -> `quizzes.id`, `question_text`, `type`, `order_index`, `timestamps`.
9. **`quiz_options`**: `id` PK, `question_id` FK -> `quiz_questions.id`, `option_text`, `is_correct`, `timestamps`.
10. **`quiz_attempts`**: `id` PK, `quiz_id` FK -> `quizzes.id`, `user_id` FK -> `users.id`, `score_percentage`, `is_passed`, `status`, `started_at`, `completed_at`, `timestamps`.
11. **`quiz_answers`**: `id` PK, `attempt_id` FK -> `quiz_attempts.id`, `question_id` FK -> `quiz_questions.id`, `selected_option_ids`, `essay_answer`, `is_correct`, `graded_by`, `graded_at`, `timestamps`.
12. **`lesson_progress`**: `id` PK, `user_id` FK -> `users.id`, `lesson_id` FK -> `lessons.id`, `is_completed`, `completion_source`, `watched_seconds`, `completed_at`, `timestamps`. UNIQUE(`user_id`, `lesson_id`).
13. **`invitation_links`**: `id` PK, `org_id` FK -> `organizations.id`, `token` CHAR(64) UNIQUE, `course_id` FK -> `courses.id`, `max_uses`, `current_uses`, `expires_at`, `revoked_at`, `created_by`, `timestamps`.
14. **`certificates`**: `id` PK, `user_id` FK -> `users.id`, `course_id` FK -> `courses.id`, `validation_hash` CHAR(64) UNIQUE, `issued_at`, `revoked_at`, `revoked_by`, `revoke_reason`, `timestamps`. UNIQUE(`user_id`, `course_id`).
15. **`course_completion_rules`**: `id` PK, `course_id` FK -> `courses.id`, `rule_type`, `target_id`, `required_percentage`, `timestamps`.
16. **`forum_topics`**: `id` PK, `org_id` FK -> `organizations.id`, `course_id` FK -> `courses.id`, `user_id` FK -> `users.id`, `title`, `content`, `is_pinned`, `edited_at`, `timestamps`.
17. **`forum_replies`**: `id` PK, `topic_id` FK -> `forum_topics.id`, `user_id` FK -> `users.id`, `content`, `edited_at`, `timestamps`.
18. **`forum_post_edits`**: `id` PK, `postable_type`, `postable_id`, `editor_user_id` FK -> `users.id`, `previous_content`, `edited_at`, `timestamps`.
19. **`forum_reports`**: `id` PK, `postable_type`, `postable_id`, `reported_by` FK -> `users.id`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `timestamps`.
20. **`help_articles`**: `id` PK, `org_id` FK -> `organizations.id` (nullable), `title`, `slug` UNIQUE, `category`, `target_page_key`, `content`, `timestamps`.
21. **`system_settings`**: `setting_key`, `org_id` (nullable), `setting_value`, `timestamps`. PK(`setting_key`, `org_id`).
22. **`notifications`**: `id` UUID PK, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `timestamps`.
23. **`audit_logs`**: `id` PK, `org_id` (nullable), `user_id` (nullable), `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `url`, `timestamps`.

---

## **3. Matriz de Requisitos Funcionais (RF)**

> **Regra Cardinal de Requisitos:** Todos os Requisitos Funcionais estão vinculados a NO MÍNIMO uma Regra de Negócio (RN) e a pelo menos um Caso de Uso (UC).

| ID | Módulo | Descrição do Requisito | Regra(s) de Negócio Vinculada(s) | Casos de Uso (UC) |
| :--- | :--- | :--- | :--- | :--- |
| **RF01** | Autenticação | Login via e-mail/senha bcrypt com sanitização de sessão e redirecionamento por perfil. | RN08, RN12, RN14 | UC01, UC02 |
| **RF02** | Recuperação de Senha | Enviar e-mail de redefinição de senha com token temporário de uso único via SMTP. | RN13, RN14 | UC01 |
| **RF03** | Auto-cadastro e Convite | Auto-cadastro de novo aluno ou vínculo de aluno existente via `invitation_links` sem duplicidade. | RN08, RN09, RN12, RN14 | UC06 |
| **RF04** | Gestão de Alunos/Gestores | Admin/Gestor cadastrar, listar, buscar, editar e inativar/ativar alunos e gestores. | RN08, RN12, RN14 | UC02, UC04 |
| **RF05** | Importação em Lote CSV | Upload de arquivo CSV com processamento assíncrono/chunked via `UserImportService`. | RN08, RN12, RN14 | UC05 |
| **RF06** | Gestão de Cursos e Módulos | Criar, editar, reordenar por drag-and-drop AJAX e soft-deletar Cursos e Módulos com guard de matrículas. | RN08, RN11, RN12, RN14 | UC07 |
| **RF07** | Conteúdo Multimídia | Cadastrar aulas com Rich Text, Imagem, PDF e Vídeo YouTube sanitizado via `YouTubeSanitizerService`. | RN08, RN12, RN14 | UC08 |
| **RF08** | Gestão de Questionários | Criar questionários com opções, tipos (única, múltipla escolha, V/F, dissertativa), gabarito e tempo limite. | RN02, RN03, RN04, RN08, RN12, RN14 | UC11 |
| **RF09** | Execução de Questionários | Player de prova para o aluno com correção automática (objetivas) e submissão para correção manual. | RN02, RN03, RN04, RN08, RN14 | UC12 |
| **RF10** | Regras de Certificado | Configurar pré-requisitos customizados em `course_completion_rules` (`all_lessons`, `min_quiz_score`). | RN01, RN08, RN12, RN14 | UC13 |
| **RF11** | Landing Page Pública | Página inicial pública (`/`) apresentando o sistema, objetivos institucionais e atalhos de login. | RN05, RN08 | UC16 |
| **RF12** | Central de Ajuda 100% | Cobrir 100% das telas com `<x-help-button key="..." />` e resolução org->global (`HelpArticleResolverService`). | RN05, RN08, RN12 | UC16 |
| **RF13** | Player de Vídeo Responsivo | Renderizar player do YouTube incorporado com parâmetros restritivos na Sala de Aula. | RN08 | UC09 |
| **RF14** | Leitor de PDF Integrado | Exibir PDFs diretamente na página da aula com iframe HTML e botão de download restrito. | RN08 | UC09 |
| **RF15** | Registro de Progresso | Registrar conclusão de lições via clique manual, threshold de vídeo (90%) ou quiz aprovado, recalculando via AJAX. | RN01, RN08, RN14 | UC10 |
| **RF16** | Emissão e PDF Certificado | Gerar arquivo PDF do certificado com marca da Organização via `barryvdh/laravel-dompdf` e QR Code. | RN01, RN07, RN08, RN12, RN13, RN14 | UC13 |
| **RF17** | Validação Pública & Revogação | Consultar autenticidade pública em `/validar-certificado/{hash}`; revogação lógica imutável com motivo. | RN07, RN08, RN12, RN14 | UC14 |
| **RF18** | Dashboard & Exportação CSV | Dashboard gerencial com 4 stat cards e exportação de dados em CSV via streaming $O(1)$ de RAM. | RN08, RN12, RN14 | UC17 |
| **RF19** | Suíte de Testes e Quality Gate | Cobertura mínima obrigatória de **95%** de testes (PHPUnit + Dusk E2E em banco MySQL `testing` dedicado). | RN06 | UC21 |
| **RF20** | Controle Rígido por Matrícula | Garantir que o aluno acesse **apenas** conteúdos em que possui matrícula ativa na Org (`EnsureStudentIsEnrolled`). | RN08, RN12 | UC09 |
| **RF21** | Gestão Manual Matrículas | Admin/Gestor vincular ou desmatricular manualmente alunos em cursos da Organização. | RN08, RN12, RN14 | UC04, UC06 |
| **RF22** | Fórum de Discussão | Fórum por curso/org com sanitização XSS, histórico público de edições e fila de denúncias/moderação. | RN08, RN10, RN12, RN14, RN15 | UC15 |
| **RF23** | Gestão de Organizações | Admin Global criar, listar, editar e inativar Organizações com nome, slug, CNPJ, logo e cores. | RN12, RN14 | UC03 |
| **RF24** | Impersonate Org | Admin Global selecionar Organização ativa em `session('active_org_id')` para operar contexto do tenant. | RN12, RN14 | UC03 |
| **RF25** | Central de Notificações | Enviar notificações in-app (sino no topbar com contador e polling) e por e-mail para 4 gatilhos do sistema. | RN08, RN12, RN13, RN14 | UC06, UC13, UC15, UC19 |
| **RF26** | Menu Navegação Dinâmico | Renderizar a estrutura de navegação lateral/topbar via `NavigationRegistry` adaptando por Role. | RN08, RN12 | UC23 |
| **RF27** | Configurações do Sistema | Permitir o gerenciamento de configurações em `system_settings` com suporte a override por Organização. | RN12, RN14 | UC18 |
| **RF28** | Correção Manual Dissertativas | Interface para Gestor/Admin avaliar e dar nota a respostas dissertativas pendentes de correção. | RN02, RN08, RN12, RN14 | UC11 |
| **RF29** | Database Seeders | Povoar o banco de dados via `DatabaseSeeder` idempotente com isolamento por ambiente dev vs prod. | RN16 | UC22 |
| **RF30** | Histórico Edições Fórum | Gravar snapshots imutáveis em `forum_post_edits` permitindo visualização de alterações passadas no fórum. | RN08, RN10, RN15 | UC15 |
| **RF31** | Auditoria Mutações Eloquent | Interceptar automaticamente mutações (`AuditableTrait` / `AuditObserver`) e gravar em `audit_logs`. | RN14 | UC20 |
| **RF32** | Auditoria Ações Críticas LGPD | Registrar eventos críticos com mascaramento `[REDACTED]` de senhas em logs e arquivos Monolog. | RN14 | UC20 |
| **RF33** | Painel Auditoria & Expurgo | Tela de consulta de auditorias com modal de diff JSON e expurgo agendado via `audit-logs:prune`. | RN14 | UC20 |

---

## **4. Matriz de Requisitos Não-Funcionais (RNF)**

| ID | Categoria | Descrição do Requisito |
| :--- | :--- | :--- |
| **RNF01** | Stack & Compatibilidade | Backend em Laravel 13 e PHP 8.5 com Blade, JS ES6+ e jQuery 3.7+. Compatível 100% com hospedagem compartilhada. |
| **RNF02** | Ausência de Daemons Pesados | Execução sem daemons ou WebSockets; e-mails e filas leves via `QUEUE_CONNECTION=sync` ou `database` via Cron. |
| **RNF03** | Quality Gate e Dusk | Cobertura mínima de 95% auditada por `scripts/check-coverage.php`; testes Dusk E2E em banco MySQL `testing`. |
| **RNF04** | Responsividade e Design System | Layout *Mobile-First* com Bootstrap 5.3 e tokens do Modernist Design System (`<x-ui.*>`). |
| **RNF05** | Sanitização, Segurança e LGPD | Proteção contra XSS em fórum/quizzes, CSRF nativo, `OrgScope` e mascaramento `[REDACTED]` de senhas. |
| **RNF06** | Streaming $O(1)$ de RAM | Exportações CSV geradas via `StreamedResponse` com consumo constante de memória. |
| **RNF07** | Desempenho e Cache | Índices compostos no banco (`org_id`, `status`), limite de memória de 128MB e cache nativo. |

---

## **5. Regras de Negócio (RN)**

| ID | Descrição da Regra | Requisitos Funcionais Cobertos | Casos de Uso (UC) |
| :--- | :--- | :--- | :--- |
| **RN01** | **Liberação do Certificado:** O certificado só é emitido quando 100% das regras em `course_completion_rules` forem atingidas. | RF10, RF15, RF16 | UC10, UC13 |
| **RN02** | **Cálculo da Nota & Dissertativas:** Nota = acertos / total. Se houver questão dissertativa pendente, a prova trava em `awaiting_manual_grading`. | RF08, RF09, RF28 | UC11, UC12 |
| **RN03** | **Repetição e Tentativas:** `allow_retries=false` permite 1 tentativa; `true` permite retries até o limite `max_attempts`. | RF08, RF09 | UC11, UC12 |
| **RN04** | **Exibição do Gabarito:** Gabarito exibido pós-submissão apenas se `show_correct_answers = true`. | RF08, RF09 | UC11, UC12 |
| **RN05** | **Cobertura de Ajuda 100%:** Todas as telas possuem `<x-help-button key="..." />` com resolução org-específica e fallback global. | RF11, RF12 | UC16 |
| **RN06** | **Guardrail de Cobertura (95%):** Pipeline CI bloqueia deploy se a cobertura for < 95% ou se qualquer teste falhar. | RF19 | UC21 |
| **RN07** | **Hash SHA-256 e Revogação Lógica:** Hash gerado via `hash('sha256', "cert_{user_id}_{course_id}_{issued_at_ISO}_{APP_KEY}")`. Revogações são lógicas e mantêm a consulta pública ativa avisando sobre a revogação (jamais 404). | RF16, RF17 | UC13, UC14 |
| **RN08** | **Restrição Estrita de Matrícula e Org:** Alunos sem vínculo em `course_user` ou fora da Organização recebem HTTP 403 (Forbidden). | RF01, RF03, RF04, RF05, RF06, RF07, RF08, RF09, RF10, RF11, RF12, RF13, RF14, RF15, RF16, RF17, RF18, RF20, RF21, RF22, RF25, RF26, RF28, RF30 | UC01, UC02, UC04, UC05, UC06, UC07, UC08, UC09, UC10, UC11, UC12, UC13, UC14, UC15, UC16, UC17, UC19, UC23 |
| **RN09** | **Convite Adaptativo sem Duplicidade:** Se o e-mail em `/convite/{token}` já pertencer a uma conta existente, solicita a senha e vincula a nova matrícula sem duplicar o usuário em `users`. | RF03 | UC06 |
| **RN10** | **Isolamento das Discussões do Fórum:** Apenas alunos matriculados ativos e gestores/admins da Org podem visualizar/postar no fórum do curso. | RF22, RF30 | UC15 |
| **RN11** | **Guard de Exclusão de Cursos:** A exclusão de um curso é bloqueada (HTTP 422) se houver matrículas ativas em `course_user`. | RF06 | UC07 |
| **RN12** | **Contexto Impersonate Org & `UnresolvedOrgContextException`:** Admin sem Impersonate Org ativo tentando criar registro em tabela escopada recebe a exceção HTTP 422. | RF01, RF03, RF04, RF05, RF06, RF07, RF08, RF10, RF12, RF16, RF17, RF18, RF20, RF21, RF22, RF23, RF24, RF25, RF26, RF27, RF28 | UC01, UC02, UC03, UC04, UC05, UC06, UC07, UC08, UC11, UC13, UC14, UC15, UC16, UC17, UC18, UC19, UC23 |
| **RN13** | **Isolamento de Falha de E-mail:** Envio de e-mail envolvido em `try/catch` para garantir que falhas SMTP jamais causem rollback na transação principal de banco. | RF02, RF16, RF22, RF25 | UC01, UC06, UC13, UC15, UC19 |
| **RN14** | **Mascaramento e Retenção de Auditoria:** Eventos de autenticação mascaram senhas como `[REDACTED]`. Retenção por `AUDIT_LOG_RETENTION_DAYS=365` limpa via `audit-logs:prune`. | RF01, RF02, RF03, RF04, RF05, RF06, RF07, RF08, RF09, RF10, RF15, RF16, RF17, RF18, RF21, RF22, RF23, RF24, RF25, RF27, RF28, RF31, RF32, RF33 | UC01, UC02, UC03, UC04, UC05, UC06, UC07, UC08, UC10, UC11, UC12, UC13, UC14, UC15, UC17, UC18, UC19, UC20 |
| **RN15** | **Preservação de Histórico no Fórum:** Edições em tópicos/respostas geram snapshots imutáveis em `forum_post_edits` acessíveis publicamente. | RF22, RF30 | UC15 |
| **RN16** | **Povoamento Idempotente de Seeders:** O `DatabaseSeeder` executa de forma idempotente (`firstOrCreate`/`updateOrCreate`) e impede a geração de dados fictícios em produção. | RF29 | UC22 |

---

## **6. Mapeamento e Links dos Casos de Uso (UC)**

A documentação detalhada e passo a passo de cada Caso de Uso foi estruturada em arquivos individuais na pasta [`spec/docs/usecases/`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/index.md):

* **[`UC01 — Autenticar, Encerrar Sessão e Recuperar Senha`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC01-autenticacao-logout-e-recuperacao-de-senha.md)**
* **[`UC02 — Gestão de Perfil do Usuário`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC02-gestao-de-perfil-do-usuario.md)**
* **[`UC03 — Gestão de Organizações e Impersonate Org`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC03-gestao-de-organizacoes-e-impersonate-org.md)**
* **[`UC04 — Gestão de Usuários e Matrículas Manuais`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC04-gestao-de-usuarios-e-matriculas-manuais.md)**
* **[`UC05 — Importação em Lote de Usuários via CSV`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC05-importacao-em-lote-de-usuarios-via-csv.md)**
* **[`UC06 — Auto-cadastro e Convite Adaptativo Multi-Org`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC06-auto-cadastro-e-convite-inteligente-adaptativo.md)**
* **[`UC07 — Gestão Multitenant de Cursos, Módulos e Reordenacao`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC07-gestao-de-cursos-modulos-e-reordenacao.md)**
* **[`UC08 — Gestão de Lições Multimídia e Sanitização`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC08-gestao-de-licoes-multimidia-e-sanitizacao.md)**
* **[`UC09 — Consumo de Aulas, Sala de Aula e Players`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC09-consumo-de-aulas-sala-de-aula-e-players.md)**
* **[`UC10 — Registro e Rastreamento de Progresso`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC10-registro-e-rastreamento-de-progresso.md)**
* **[`UC11 — Gestão de Questionários, Provas e Correção Manual`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC11-gestao-de-questionarios-e-provas.md)**
* **[`UC12 — Realização de Questionários pelo Aluno`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC12-realizacao-de-questionarios-pelo-aluno.md)**
* **[`UC13 — Configuração de Regras e Emissão de Certificado`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC13-configuracao-de-regras-e-emissao-de-certificado.md)**
* **[`UC14 — Validação Pública e Revogação de Certificados`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC14-validacao-publica-e-revogacao-de-certificados.md)**
* **[`UC15 — Fórum de Discussão, Histórico e Moderação`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC15-forum-de-discussao-historico-e-moderacao.md)**
* **[`UC16 — Landing Page e Central de Ajuda Integral`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC16-landing-page-e-central-de-ajuda-integral.md)**
* **[`UC17 — Dashboard Gerencial e Exportação CSV Streaming`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC17-dashboard-gerencial-e-exportacao-csv-streaming.md)**
* **[`UC18 — Configurações do Sistema Globais e por Org`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC18-configuracoes-do-sistema-globais-e-por-org.md)**
* **[`UC19 — Central de Notificações In-App e E-mail`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC19-central-de-notificacoes-in-app-e-email.md)**
* **[`UC20 — Logs de Auditoria, Monitoramento e Expurgo`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC20-logs-de-auditoria-monitoramento-e-expurgo.md)**
* **[`UC21 — Suíte de Testes, Environment Dusk e CI/CD`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC21-suite-de-testes-environment-dusk-e-ci-cd.md)**
* **[`UC22 — Povoamento Automatizado do Banco (Seeders)`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC22-povoamento-automatizado-do-banco-seeders.md)**
* **[`UC23 — Menu de Navegação Dinâmico e Controle de Acesso`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/UC23-menu-de-navegacao-dinamico-e-controle-de-acesso.md)**

---

## **7. Matriz Completa de Rastreabilidade**

Ver índice mestre em [`spec/docs/usecases/index.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/usecases/index.md).

---

## **8. Design System, Especificação Visual e Diretrizes de UI/UX**

### **8.1. Guia de Fundamentos Visuais (Modernist Design Tokens)**

* **Azul Elétrico Principal (Primary Brand):** `#004080` (`--color-primary`).
* **Amarelo/Dourado de Destaque (Secondary Accent):** `#EAB308` (`--color-secondary`).
* **Fundo de Tela (Background Neutral):** `#F8FAFC` (Slate 50).
* **Superfície/Cards (Surface Neutral):** `#FFFFFF`.
* **Texto Primário:** `#1E293B` (Slate 800) | **Texto Secundário:** `#64748B` (Slate 500).
* **Sucesso (Success Green):** `#10B981` | **Erro/Perigo (Danger Red):** `#EF4444`.

---

### **8.2. Coleção de Micro-Componentes Blade (`<x-ui.*>`)**

1. `<x-ui.card>`: Container para agrupamento de conteúdos.
2. `<x-ui.button>`: Botão padronizado com variantes (`primary`, `secondary`, `danger`, `outline`).
3. `<x-ui.badge>`: Tag visual para status (`active`, `cancelled`, `completed`, `pending`).
4. `<x-ui.stat-card>`: Card de estatística para Dashboards.
5. `<x-ui.table>`: Tabela responsiva padronizada.
6. `<x-help-button key="..." />`: Botão flutuante contextual acionador da Central de Ajuda.