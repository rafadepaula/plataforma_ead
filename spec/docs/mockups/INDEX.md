# **Índice de Especificações de Mockups de Tela — Plataforma EAD**

---

## **1. Visão Geral**

Esta pasta (`spec/docs/mockups/`) contém a documentação exaustiva e detalhada de cada mockup visual da Plataforma EAD, construído com base no HTML gerado pelo Claude Design (`ds/page.html`) e no Design System **Modernist** (`spec/specs/02-frontend-design-system-blade-components-and-layout.md`).

Cada documento descreve a anatomia visual, o comportamento responsivo nos 3 breakpoints principais (**Mobile 390px**, **Tablet 768px** e **Desktop 1920px**), o mapeamento de micro-componentes Blade (`<x-ui.*>` e `<x-layout.*>`), tokens de CSS utilizados, contratos de dados esperados pela View (PHP/Blade) e asserções obrigatórias para testes E2E com **Laravel Dusk**.

---

## **2. Lista de Documentos de Mockups por Tela**

| ID | Tela / Funcionalidade | Rota / Contexto | Papel (Role) | Arquivo de Especificação |
| :--- | :--- | :--- | :--- | :--- |
| **01** | Menu Lateral Mobile (Estado Aberto) | Drawer Global (Mobile) | Aluno & Admin | [`01-menu-mobile.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/01-menu-mobile.md) |
| **02** | Landing Page Pública | `/` (`landing`) | Visitante (Guest) | [`02-landing-page.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/02-landing-page.md) |
| **03** | Meus Cursos | `/meus-cursos` | Aluno (`role:aluno`) | [`03-meus-cursos.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/03-meus-cursos.md) |
| **04** | Aula / Player de Vídeo | `/cursos/{slug}/aulas/{aula_id}` | Aluno (`role:aluno`) | [`04-aula-player.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/04-aula-player.md) |
| **05** | Quiz de Avaliação | `/cursos/{slug}/quizzes/{quiz_id}` | Aluno (`role:aluno`) | [`05-quiz-avaliacao.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/05-quiz-avaliacao.md) |
| **06** | Fórum de Discussão | `/cursos/{slug}/forum` | Aluno & Gestor | [`06-forum-discussao.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/06-forum-discussao.md) |
| **07** | Dashboard Admin | `/admin/dashboard` | Admin (`role:admin`) | [`07-dashboard-admin.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/07-dashboard-admin.md) |
| **08** | Gestão de Alunos | `/admin/alunos` | Admin & Gestor | [`08-gestao-alunos.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/08-gestao-alunos.md) |
| **09** | Gestão de Cursos e Módulos | `/admin/cursos` | Admin & Gestor | [`09-gestao-cursos.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/09-gestao-cursos.md) |
| **10** | Login e Convite | `/login` e `/convites/{token}` | Guest (Visitante) | [`10-login-convite.md`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/10-login-convite.md) |

---

## **3. Regras Globais de Construção (Modernist Design System)**

1. **Raio de Borda (Border Radius) é Estritamente 0px**: `--radius-sm: 0px`, `--radius-md: 0px`, `--radius-lg: 0px`. Qualquer canto arredondado em botões, cards, modais ou inputs viola o design system.
2. **Fotografia Sempre Grayscale**: Todas as imagens de conteúdo (avatares de usuários sem iniciais, thumbnails de cursos, capas) usam a classe `.grayscale` (`filter: grayscale(1) contrast(1.08)`). Exceção: logos de Organizações (`organizations.logo_path`).
3. **Alinhamento Flush-Left**: Textos, títulos, legendas e labels de botões expansíveis (`.btn-block`) são sempre alinhados à esquerda. Nenhuma centralização ad-hoc.
4. **Paleta Semântica Restrita (4 Variantes de Tag)**:
   - `tag-accent` (`var(--color-accent-100)` / `var(--color-accent-700)`): Ativo, Concluído, Publicado, Live, Nova Matrícula.
   - `tag-outline` (borda `var(--color-divider)`, fundo transparente): Em Andamento, Em Revisão, Obrigatório, Única Escolha.
   - `tag-neutral` (`var(--color-neutral-200)` / `var(--color-neutral-800)`): Pendente, Rascunho, Inativo, Aluno.
   - `tag-accent-2` (`var(--color-accent-2-100)` / `var(--color-accent-2-800)`): Em Risco, Vencido, Atrasado, Reprovado.
5. **Iconografia Inline**: SVG Lucide inline via componente `<x-ui.icon name="..." />`, sem dependências de fontes de ícones externas.
