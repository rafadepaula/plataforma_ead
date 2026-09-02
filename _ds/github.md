# Fonte do projeto

repo: rafadepaula/plataforma_ead
branch: main
path: (raiz — `spec/` e `resources/views/`)

## Last sync

date: 2026-08-22T03:22:00Z

### Updated in this project

- `Fazer a prova - Anatomia.dc.html`: canvas de especificação da tela do aluno (cabeçalho, cronômetro com contrato rígido de nó, faixa de avisos, bloco de questão, opção, dissertativa, envio único e gabarito), lido de `student/quizzes/show.blade.php` e `resources/js/modules/QuizTimer.js`.

- `Criar a prova - Anatomia.dc.html`: canvas de especificação da tela de autoria da prova (cabeçalho, regras, lista de questões arrastável, diálogo por questão, select de tipo, lista e linha de opção, estados e contrato), lido de `quizzes/edit.blade.php`, `quizzes/partials/_question-form.blade.php`, `_question-list.blade.php` e `resources/js/modules/QuizBuilder.js`.

- `Montar a trilha - Anatomia.dc.html`: canvas de especificação componente por componente da tela de módulos e lições (cabeçalho, lista arrastável, linha, remoção com confirmação, formulário da lição, uploads, campo de YouTube, publicação), lido de `courses/modules/index.blade.php`, `courses/modules/_list.blade.php`, `modules/lessons/index.blade.php`, `modules/lessons/_form.blade.php` e `resources/js/modules/ModuleReorder.js`.
- `Meus cursos - Anatomia dos cards.dc.html`: canvas de especificação dos cards de progresso e do filtro de abas.

## Sync history

### 2026-08-21T19:56:04Z

- Deck de 22 slides com as telas principais na nova linguagem visual (`Novo Design System - Telas.dc.html`).
- `DESIGN.md`: tokens, mapeamento `x-ui.*` → novos componentes, especificação tela por tela, tom de voz, acessibilidade, plano de fases e contrato `dusk`.
- `Landing Page.dc.html`: canvas com o design completo da landing (1440px) e a especificação da tela ao lado (conteúdo faixa a faixa, tokens, espaçamentos, estados, responsivo, contrato `dusk`, acessibilidade), a partir de `resources/views/landing/show.blade.php`.
- Diagnóstico e plano de migração resumidos a partir de `spec/front_migration/`.

## Screen map

| Tela (slide) | Arquivos de origem |
|---|---|
| Shell (08) | `resources/views/layouts/app.blade.php`, `components/layout/topbar.blade.php`, `sidebar.blade.php`, `app/Services/Navigation/NavigationRegistry.php` |
| Dashboard (09) | `resources/views/dashboard/index.blade.php` |
| Cursos e módulos (10) | `resources/views/courses/index.blade.php` |
| Módulos e lições (11) | `resources/views/courses/modules/_list.blade.php`, `modules/lessons/_form.blade.php` |
| Fazer a prova — canvas de especificação | `resources/views/student/quizzes/show.blade.php`, `resources/js/modules/QuizTimer.js` |
| Criar a prova — canvas de especificação | `resources/views/quizzes/edit.blade.php`, `quizzes/partials/_question-form.blade.php`, `quizzes/partials/_question-list.blade.php`, `resources/js/modules/QuizBuilder.js` |
| Montar a trilha — canvas de especificação | `resources/views/courses/modules/index.blade.php`, `courses/modules/_list.blade.php`, `modules/lessons/index.blade.php`, `modules/lessons/_form.blade.php`, `resources/js/modules/ModuleReorder.js` |
| Meus cursos — canvas de especificação | `resources/views/student/courses/index.blade.php` |
| Autoria de prova (12) | `resources/views/quizzes/edit.blade.php`, `quizzes/partials/_question-form.blade.php`, `_question-list.blade.php` |
| Correção de redações (13) | `resources/views/quizzes/attempts/pending.blade.php`, `attempts/show.blade.php` |
| Meus cursos (14) | `resources/views/student/courses/index.blade.php` |
| Sala de aula (15) | `resources/views/classroom/show.blade.php` |
| Aula em vídeo (16) | `resources/views/classroom/lesson.blade.php`, `classroom/partials/_video.blade.php` |
| Prova do aluno (17) | `resources/views/student/quizzes/show.blade.php` |
| Fórum (18) | `resources/views/forum/index.blade.php`, `forum/partials/_topic.blade.php` |
| Landing (19) | `resources/views/landing/show.blade.php` |
| Landing — canvas de especificação | `resources/views/landing/show.blade.php` |
| Login e convite (20) | `resources/views/auth/login.blade.php`, `layouts/guest.blade.php`, `convite/show.blade.php` |
| Mobile (21) | `resources/views/components/layout/sidebar.blade.php` |
| Fundamentos e plano (02–07, 22) | `spec/front_migration/00-index.md`, `01-diagnostico-e-mapa-mental.md`, `03-screen-inventory.md` |
