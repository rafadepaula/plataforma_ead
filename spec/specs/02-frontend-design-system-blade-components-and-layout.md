# **02. Frontend Design System, Master Layouts e Componentes Reutilizáveis Blade**

---

## **1. Visão Geral e Arquitetura do Frontend**

Esta especificação define a fundação visual e os componentes de interface do usuário (UI) da Plataforma EAD. Inspirado na estrutura limpa e bem particionada do projeto de referência `conectaconselho/painel`, o frontend adota uma abordagem de **Arquitetura em Camadas com Micro-Componentes Blade**, garantindo a máxima reutilização de código, alta manutenibilidade e flexibilidade de identidade visual.

### **1.1. Diretrizes Principais de Desenvolvimento**
1. **Bootstrap 5.3 (somente grid + utilities) + Design System "Modernist" (Claude Design)**: Estrutura de grid/breakpoints/utilitários de Bootstrap 5.3 (`@import` apenas dos módulos `grid` e `utilities`, **sem** `reboot`/componentes Bootstrap), sobreposta pela camada visual e de componentes do design system **Modernist**, produzido via Claude Design MCP e importado em `resources/css/app.css`. Os nomes de classe do Modernist (`.btn`, `.card`, `.table`, `.nav`, `.input`, `.tag`, `.dialog`) colidem intencionalmente com os componentes Bootstrap — por isso Bootstrap entra só como grid/utilities, e `app.css` do Modernist é importado **depois** para vencer a especificidade. Fonte: [Claude Design — projeto "Modernist"](https://claude.ai/design/p/a405d083-9548-4317-8df5-baa8dee6dca6), `projectId=c1f64217-a76d-4e10-b7b0-51641e8b3d67`, arquivos `theme.json`, `styles.css`, `readme.md`, `foundations/*.html`, `components/*.html`.
2. **Micro-Componentização Blade (Princípio de Responsabilidade Única - SRP)**: Cada componente Blade (`<x-ui.*>`) é atômico, focado em uma única responsabilidade de renderização (ex: botões, cards, campos de formulário, modais, badges, tabelas).
3. **JavaScript / jQuery Modular (Clean Code & SOLID)**: Código JS organizado em módulos/classes orientados a objetos em `resources/js/modules/`, aplicando princípios SOLID (Single Responsibility, Open/Closed) para chamadas AJAX, modais e notificações.
4. **Navegação Dinâmica por Role Spatie**: O menu lateral (`sidebar`) renderiza estritamente os itens de menu permitidos para o papel atual do usuário (`role:admin`, `role:gestor`, `role:aluno`).
5. **Guardrail de Cobertura E2E (100% via Dusk)**: A suíte de testes de interface com **Laravel Dusk** deve cobrir 100% dos componentes e fluxos visuais.

---

## **2. Master Layout e Estrutura de Templates**

A aplicação possui uma estrutura de layout mestre em `resources/views/layouts/app.blade.php`, particionada nos seguintes submódulos reutilizáveis:

```
resources/views/
├── layouts/
│   ├── app.blade.php             <-- Master Template Principal
│   └── guest.blade.php           <-- Layout de Páginas Públicas / Login / Convite
└── components/
    ├── layout/
    │   ├── topbar.blade.php      <-- Barra Superior (Usuário, Impersonate, Notificações)
    │   ├── sidebar.blade.php     <-- Menu Lateral Dinâmico por Role Spatie
    │   ├── footer.blade.php      <-- Rodapé Institucional
    │   └── alerts.blade.php      <-- Contêiner de Mensagens Flash e Toasts
    └── ui/                       <-- Micro-Componentes Reutilizáveis de UI
        ├── button.blade.php
        ├── card.blade.php
        ├── modal.blade.php
        ├── badge.blade.php
        ├── input.blade.php
        ├── select.blade.php
        ├── table.blade.php
        ├── alert.blade.php
        └── stat-card.blade.php
```

---

## **3. CSS Design System & Variáveis Globais — "Modernist" (`resources/css/app.css`)**

Tokens copiados **exatamente** de `styles.css` do projeto Modernist (Claude Design). Não hard-codar hex/px em nenhuma view — sempre `var(--color-*)`, `var(--space-*)`, `var(--radius-*)`, `var(--shadow-*)`.

```css
:root {
  --color-bg: #f3f2f2;
  --color-surface: #eae9e9;
  --color-text: #201e1d;
  --color-accent: #ec3013;
  --color-accent-2: #e15b47;
  --color-divider: color-mix(in srgb, #201e1d 40%, transparent);

  /* Rampas tonais 100 (claro) -> 900 (escuro), geradas em OKLCH */
  --color-neutral-100: #f8f4f4; --color-neutral-200: #eae7e7; --color-neutral-300: #d7d3d3;
  --color-neutral-400: #bab6b6; --color-neutral-500: #9b9797; --color-neutral-600: #7d7979;
  --color-neutral-700: #605d5d; --color-neutral-800: #444141; --color-neutral-900: #2d2b2b;

  --color-accent-100: #fff2ef; --color-accent-200: #ffe0d9; --color-accent-300: #ffc4b8;
  --color-accent-400: #ff9783; --color-accent-500: #ff563c; --color-accent-600: #dd2b0f;
  --color-accent-700: #ae1800; --color-accent-800: #7c1405; --color-accent-900: #4d170e;

  /* Paleta mono — accent-2 é um stand-in derivado, trate como mesma role do accent */
  --color-accent-2-100: #fff2ef; --color-accent-2-200: #ffe0da; --color-accent-2-300: #ffc4b9;
  --color-accent-2-400: #ff9784; --color-accent-2-500: #ef6853; --color-accent-2-600: #c94b39;
  --color-accent-2-700: #9e3526; --color-accent-2-800: #71261b; --color-accent-2-900: #471d16;

  --font-heading: "Archivo", system-ui, sans-serif;
  --font-heading-weight: 800;
  --font-body: "Archivo", system-ui, sans-serif;

  --space-1: 4px; --space-2: 8px; --space-3: 12px; --space-4: 16px; --space-6: 24px; --space-8: 32px;

  /* Radius é 0 em todo o sistema — decisão de marca, não bug */
  --radius-sm: 0px; --radius-md: 0px; --radius-lg: 0px;

  --shadow-sm: 0 1px 2px color-mix(in srgb, #2d2b2b 14%, transparent);
  --shadow-md: 0 3px 10px color-mix(in srgb, #2d2b2b 16%, transparent);
  --shadow-lg: 0 12px 32px color-mix(in srgb, #2d2b2b 22%, transparent);
}
```

### **3.1. Fonte (self-hosted, sem CDN do Google Fonts)**

O `styles.css` de origem carrega Archivo via `@import url('https://fonts.googleapis.com/...')`. Em hospedagem compartilhada (SPEC-00 §1.2), evitar dependência de rede externa no carregamento de CSS: baixar os pesos `400/600/800` de Archivo (woff2) para `public/fonts/archivo/` e declarar via `@font-face` em `app.css`, substituindo o `@import` do CDN. Mantém `--font-heading`/`--font-body` idênticos.

### **3.2. Ícones — Lucide inline (sem dependência nova)**

Design system usa [Lucide](https://lucide.dev), SVG inline com `stroke="currentColor"` (herda cor do texto). **Não** adicionar pacote npm `lucide` — criar componente `<x-ui.icon name="bell|user|search|..." />` em `resources/views/components/ui/icon.blade.php` que faz `@include` do markup SVG cru por nome, copiado de `foundations/icons.html`. Evita alterar dependências do projeto sem aprovação (CLAUDE.md).

### **3.3. Imagens — sempre grayscale**

Toda foto de conteúdo (avatar, thumbnail de curso, foto de instrutor) é envolvida no wrapper `.grayscale` (`filter: grayscale(1) contrast(1.08)`), conforme `foundations/image.html`. Logos de Organização (`organizations.logo_path`, SPEC-00 §2.1) **não** entram nesse tratamento — são identidade de marca do cliente, não fotografia de conteúdo.

---

## **4. Mapeamento `<x-ui.*>` -> Classes Modernist**

Cada micro-componente Blade renderiza o markup exato dos arquivos `components/*.html` do design system — não inventar classes paralelas (regra do próprio `readme.md`).

| Componente Blade | Classes/markup Modernist | Fonte | Observação |
| :--- | :--- | :--- | :--- |
| `<x-ui.button variant="primary\|secondary\|ghost" block icon>` | `.btn .btn-primary/.btn-secondary/.btn-ghost`, `.btn-icon` (ícone só), `.btn-block` (largura total) | `components/buttons.html` | Label **sempre flush-left**; botão largo não centraliza texto. Ícone `<svg>` inline, nunca `<i class="bi-...">` do Bootstrap Icons. |
| `<x-ui.badge variant="accent\|outline\|neutral\|accent-2">` | `.tag .tag-accent/.tag-outline/.tag-neutral/.tag-accent-2` — mapeamento semântico confirmado em mockup (Tela 2/3/4, ver §4.1): `tag-accent`=positivo/ativo/concluído, `tag-outline`=em andamento/em revisão, `tag-neutral`=pendente/rascunho, `tag-accent-2`=negativo/vencido/atrasado | `components/buttons.html` | Substitui o antigo conceito de `badge` Bootstrap. Nenhuma cor verde/amarela/azul — 4 variantes cobrem todo status do sistema. |
| `<x-ui.card kicker title body meta>` | `.card > .card-kicker, .card-title, .card-body, .card-meta`; elevação opcional `.elev-sm/md/lg` | `components/cards.html` | Usado em "Meus Cursos" (SPEC-07), listagem de cursos (SPEC-05). Card de curso: `image-slot`/`<img class="grayscale">` no topo (padding 0), depois kicker+tag+title+meta+barra de progresso+botão CTA — ver §4.2. |
| `<x-ui.input>` / `<x-ui.select>` | `.field > label + .input` (input/textarea nativo); `<select class="input">` com `appearance:none` + chevron SVG absoluto (`position:relative` no wrapper, ícone `right:10px;top:50%;transform:translateY(-50%);pointer-events:none`) — padrão confirmado em mockup (Tela 3) | `components/forms.html` | `<select>` HTML nativo herda `.input`, chevron custom por cima da seta nativa oculta. |
| `<x-ui.table>` | `.table` (thead th uppercase pequeno, `tbody tr:hover` tint) | `components/table.html` | Colunas numéricas: alinhar à direita com classe local (`text-end` do Bootstrap utilities, mantido). |
| `<x-ui.modal>` | `.dialog-backdrop > .dialog[role=dialog][aria-modal=true] > .dialog-title/.dialog-body/.dialog-actions` | `components/dialog.html` | Confirmar em `ModalManager.js` (JS module, §1.1.3) que o backdrop fecha no clique fora e `Esc`, e foco vai para o dialog (`aria-modal`). |
| `<x-layout.topbar>` | `.nav > .nav-brand + campo de busca `.input` com ícone + `<button class="btn btn-ghost btn-icon">` (sino) + bloco avatar/nome/role/chevron` | `components/navigation.html` + Tela 2 do mockup | Ver markup exato em §4.2.1. |
| `<x-layout.sidebar>` | `background: var(--color-neutral-900)`; item inativo `color: var(--color-neutral-400)`; item ativo `color: var(--color-neutral-100)` + `border-left: 3px solid var(--color-accent)` + `background: color-mix(in srgb, var(--color-accent) 18%, transparent)` | Tela 2 do mockup | **Resolvido** — ver §4.2.2, confirma a recomendação anterior desta spec. |
| `<x-ui.alert>` | Compor com barra full-width `background: var(--color-accent-100); color: var(--color-accent-800)` + ícone + texto + ação à direita (padrão do banner de impersonate, Tela 2) | Tela 2 do mockup | Ver §4.2.3. |
| `<x-ui.stat-card>` | `.card.elev-sm` com `.card-kicker` (label da métrica) + valor grande (`font: var(--font-heading) 800 30px`) + `.card-meta` com `.tag.tag-accent` (delta) | Tela 2 do mockup | Confirmado — ver §4.2.4. |

### **4.1. Gaps do Design System — resolvidos via mockup (`ds/page.html`)**

Os gaps de design antes em aberto foram integralmente resolvidos pelos mockups de alta fidelidade disponibilizados em `ds/page.html` (abrangendo 9 telas completas nos breakpoints **Mobile 390px**, **Tablet 768px** e **Desktop 1920px**, além do layout de Login/Convite). Nenhum introduz cor fora da paleta mono do Modernist.

- **`<select>` estilizado — resolvido**: `<select class="input" style="appearance:none;-webkit-appearance:none;padding-right:32px">` dentro de um `<div style="position:relative">`, com um `<svg>` chevron posicionado `position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;opacity:0.6` por cima. Some a seta nativa do browser, usa o ícone Lucide `chevron-down` no lugar. Markup confirmado nos mockups.
- **Paleta semântica — resolvida, sem cor nova**: os 4 status usados nos mockups (Concluído/Live/Ativo, Em andamento/Obrigatório/Única Escolha, Pendente/Rascunho/Inativo, Em Risco/Vencido) mapeiam 1:1 para as 4 variantes de `.tag` já existentes — `tag-accent` (positivo/ativo/publicado), `tag-outline` (em progresso/obrigatório), `tag-neutral` (pendente/rascunho/aluno), `tag-accent-2` (negativo/em risco/vencido). Nenhum verde/amarelo/azul introduzido — mantém o sistema mono.
- **Sidebar escura e Menu Mobile — resolvidos**: 
  - **Desktop**: `width: 236px` ou `240px`, `background: var(--color-neutral-900)`, texto inativo `var(--color-neutral-400)`, texto ativo `var(--color-neutral-100)` com `border-left: 3px solid var(--color-accent)` e tint `color-mix(in srgb, var(--color-accent) 18%, transparent)`.
  - **Tablet**: Modo reduzido em `width: 64px`, exibindo apenas os ícones SVG Lucide centralizados.
  - **Mobile**: Drawer deslizante de `width: 280px` com fundo `--color-neutral-900`, overlay com blur `color-mix(in srgb, var(--color-neutral-900) 55%, transparent)` e seções de navegação por role, mini-cursos e lembretes.

---

## **4.2. Padrões de Tela Confirmados (Mockups — 9 telas + Guest Layout)**

### **4.2.1. Menu Lateral Mobile — Drawer Aberto (`<x-layout.sidebar>`)**
- **Mobile (390px)**: Drawer deslizante (`width: 280px; background: var(--color-neutral-900); shadow: var(--shadow-lg)`).
- **Cabeçalho**: Nome da organização ("Conselho EAD") + ícone fechar `X` Lucide.
- **Bloco de Usuário**: Avatar circular 32px com iniciais ("JP"), nome ("João Pereira") e role ("Aluno" / "Admin").
- **Seções (Aluno)**: Navegação principal (Meus Cursos, Fórum, Certificados, Perfil), seção "Continuar estudando" (mini-cursos com barras de progresso 4px) e seção "Lembretes" (ícones Lucide `bell`/`clock` em `--color-accent`).
- **Seções (Admin)**: Navegação por permissões Spatie (Dashboard, Alunos, Cursos e Módulos, Convites, Certificados, Relatórios, Configurações).

### **4.2.2. Landing Page Pública (`route('landing')`)**
- **Mobile (390px)**: Header `.nav` com botão hambúrguer, hero image grayscale `height: 150px`, headline `<h1>` (20px), parágrafo `.text-muted` e botões empilhados `.btn-block` ("Acessar Cursos" em `primary` e "Entrar" em `secondary`).
- **Tablet (768px)**: Header com links "Sobre" e "Central de Ajuda", grid 2 colunas (`1fr 1fr`), tracinho accent 40px x 2px, hero image `height: 220px`.
- **Desktop (1920px)**: Topbar com `padding: 16px 48px`, grid 2 colunas com alinhamento vertical centralizado (`gap: 56px`), tracinho accent 56px x 2px, headline `<h1>` em 44px (max-width 11ch), subtítulo 16px, botões lado a lado e hero image grayscale em `height: 340px`.

### **4.2.3. Meus Cursos — Visão Aluno (`route('student.courses.index')`)**
- **Mobile (390px)**: Lista vertical em coluna única com `padding: 14px`, cards com capa grayscale `height: 90px`, kicker ("NR") e badge de status, progresso 5px e botão CTA `.btn-block` (30px altura).
- **Tablet (768px)**: Sidebar reduzida 64px, grid 2 colunas (`gap: 16px`), capa `height: 110px`.
- **Desktop (1920px)**: Sidebar 240px, topbar com perfil do aluno, cabeçalho `<h1>` ("Meus Cursos") com contador de matrículas, grid de 3 colunas (`repeat(3, 1fr); gap: 20px`), cards `.card.elev-sm` com capa grayscale `height: 150px`, barra de progresso `height: 6px` (`var(--color-accent)`) e botão CTA `.btn-primary.btn-block`.

### **4.2.4. Aula — Player de Vídeo (`route('student.lessons.show')`)**
- **Mobile (390px)**: Topbar com botão voltar (`arrow-left`), player de vídeo com fundo `--color-neutral-900` (`height: 180px`), abas "Sobre", "Materiais" e "Fórum", e lista empilhada de lições.
- **Tablet (768px)**: Player em `height: 280px`, lições dispostas em chips horizontais.
- **Desktop (1920px)**: Layout split de 3 colunas/áreas:
  - Sidebar esquerda (240px).
  - Conteúdo central: Breadcrumbs (`Meus Cursos / NR10`), player de vídeo (`height: 380px`), título `<h2>`, metadados ("Módulo 1 · 10 min"), abas de apoio e texto descritivo (`max-width: 70ch`).
  - Drawer/Sidebar direita (`280px; border-left: 2px solid var(--color-divider)`): Índice de lições do módulo com ícones SVG inline (`check` para concluída, `play` para atual com fundo/borda accent, `lock` para bloqueada).

### **4.2.5. Quiz de Avaliação (`route('student.quizzes.show')`)**
- **Mobile (390px)**: Topbar com cronômetro ("06:12"), contador "Questão 2 de 6", barra de progresso 5px, título `<h4>` e opções radio em cards empilhados.
- **Tablet (768px)**: Container centralizado de `520px`, opções radio `padding: 12px 14px`, botões "Anterior" (`ghost`) e "Próxima" (`primary`).
- **Desktop (1920px)**: Topbar completa com título do curso e cronômetro, container central de `680px`:
  - Header: "Questão 2 de 6" + `<x-ui.badge variant="outline">` ("Única escolha").
  - Barra de progresso 6px.
  - Pergunta `<h3>` em 20px 800.
  - Opções radio expandidas: `.radio` com `padding: 14px 16px`, destacada em `border-color: var(--color-accent); background: var(--color-accent-100)` quando selecionada.
  - Navegação inferior: `<x-ui.button variant="ghost">Anterior</x-ui.button>` à esquerda e `<x-ui.button variant="primary">Próxima questão</x-ui.button>` à direita.

### **4.2.6. Fórum de Discussão (`route('student.forum.index')`)**
- **Mobile (390px)**: Lista vertical de cards com botão "+ Novo Tópico" no topo.
- **Tablet (768px)**: Split-screen com lista de tópicos em 240px à esquerda e thread selecionada à direita.
- **Desktop (1920px)**: Layout split avançado de 2 colunas principais:
  - Coluna da esquerda (`320px; border-right: 2px solid var(--color-divider)`): Lista de tópicos com botão "+ Novo Tópico", badges de autor (`tag-neutral` Aluno / `tag-accent` Professor), título e contagem de respostas.
  - Coluna da direita (flex 1; `padding: 28px 40px`): Pergunta principal em card com fundo `var(--color-accent-100)` e borda `var(--color-accent-300)`, thread de respostas com avatares e badges, e campo de resposta rápida com `<input class="input">` e botão primário.

### **4.2.7. Dashboard Admin (`route('admin.dashboard')`)**
- **Mobile (390px)**: Stat cards em grid 2 colunas (`font-size: 18px`), lista compacta de matrículas recentes.
- **Tablet (768px)**: Stat cards em grid 4 colunas (`font-size: 20px`), lista com curso e badge de status.
- **Desktop (1920px)**: Sidebar 220px, conteúdo `padding: 28px 40px`, headline `<h1>` ("Dashboard"):
  - Grid de 4 Stat Cards (`<x-ui.stat-card>`): `.card.elev-sm` com kicker uppercase, valor de impacto em `font-size: 30px; font-weight: 800; font-family: var(--font-heading)` ("318", "142", "84%", "6") e delta percentual em `.tag.tag-accent` ("+4,2%").
  - Tabela `<x-ui.table>` de matrículas recentes: Colunas Nome, Curso e Status (badged `tag-accent` "Nova", `tag-neutral` "Concluído", `tag-accent-2` "Em risco").

### **4.2.8. Gestão de Alunos (`route('admin.students.index')`)**
- **Mobile (390px)**: Lista de linhas compactas com avatar 24px, nome e badge.
- **Tablet (768px)**: Topbar com botões "Importar CSV" e "+ Novo", tabela de 3 colunas.
- **Desktop (1920px)**: Topbar com busca integrada (`.input` com lupa SVG inline em `padding-left: 32px; width: 240px`), botão secundário com ícone `upload` ("Importar CSV") e botão primário ("+ Novo Aluno"). Tabela `<x-ui.table>` de 5 colunas: Nome, E-mail, Cursos matriculados, Status (`tag-accent` "Ativo" / `tag-neutral` "Inativo") e Ações ("Editar" em `.btn-ghost`).

### **4.2.9. Gestão de Cursos e Módulos (`route('admin.courses.index')`)**
- **Mobile (390px)**: Lista compacta com título e status.
- **Tablet (768px)**: Grid 2 colunas de cards `.elev-sm` com metadados (módulos e carga horária).
- **Desktop (1920px)**: Topbar com botão "+ Novo Curso". Tabela `<x-ui.table>` de 5 colunas com handle de reordenação `⠿` em `color: var(--color-neutral-500)`, Curso, Módulos, Carga horária e Status (`tag-accent` "Publicado" / `tag-neutral` "Rascunho").

### **4.2.10. Login e Convite (`layouts/guest.blade.php`)**
- **Mobile (390px)**: Formulário em coluna única com fundo claro (`var(--color-bg)`).
- **Desktop (1920px)**: Split-screen 42%/58%:
  - Painel esquerdo escuro (42%, `--color-neutral-900`): Marca "Conselho EAD", tracinho accent 56px x 2px, headline `<h1>` (36px 800, max 9ch) "Acesse a plataforma", texto institucional e copyright.
  - Painel direito claro (58%, `--color-bg`): Form centralizado 380px com `<h2>` ("Entrar na sua conta"), campos `.field` > `label` + `.input`, checkbox "Manter conectado", link "Esqueceu a senha?", `<x-ui.button variant="primary" block>`Entrar`</x-ui.button>`, divisor "OU" e botão SSO secundário.

> Nota de implementação: `browser-window.jsx`, `image-slot.js` e `support.js` do projeto de mockup são só ferramental de preview do Claude Design (moldura de navegador falsa, placeholder de imagem drag-and-drop) — **não portar para o Blade real**. No Laravel, vídeo/thumbnail usam `<img>`/`<iframe>` reais (SPEC-05, SPEC-07), não o custom element `<image-slot>`.

---

### **4.3. Documentação de Suporte de Mockups por Tela (`spec/docs/mockups/*.md`)**

Para garantir a completa autonomia de implementação por agentes de código e desenvolvedores, cada tela do design system gerado pelo Claude Design (`ds/page.html`) está detalhadamente documentada em arquivos individuais de especificação visual e comportamental em [`spec/docs/mockups/`](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/INDEX.md):

1. **[Índice Geral de Mockups](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/INDEX.md)**: Mapeamento completo e diretrizes globais do Modernist Design System.
2. **[01. Menu Lateral Mobile (Drawer Aberta)](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/01-menu-mobile.md)**: Drawer deslizante mobile (390px) para papeis Aluno e Admin.
3. **[02. Landing Page Pública](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/02-landing-page.md)**: Estrutura responsiva pública com hero grid e ações de entrada.
4. **[03. Meus Cursos (Visão Aluno)](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/03-meus-cursos.md)**: Catálogo do aluno matriculado, barras de progresso e badges semânticos.
5. **[04. Aula — Player de Vídeo](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/04-aula-player.md)**: Player de vídeo YouTube, abas de apoio e drawer lateral de índice de lições.
6. **[05. Quiz de Avaliação](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/05-quiz-avaliacao.md)**: Interface centralizada de avaliação com cronômetro e opções radio customizadas.
7. **[06. Fórum de Discussão](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/06-forum-discussao.md)**: Layout split com lista de tópicos e thread de discussão por curso.
8. **[07. Dashboard Admin](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/07-dashboard-admin.md)**: Stat cards com deltas de métricas e tabela de matrículas recentes.
9. **[08. Gestão de Alunos](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/08-gestao-alunos.md)**: Tabela de gestão de usuários, busca integrada e ações de importação CSV.
10. **[09. Gestão de Cursos e Módulos](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/09-gestao-cursos.md)**: Tabela de gestão de catálogo com handles de reordenação e alternância de rascunho/publicado.
11. **[10. Login e Convite (Guest Layout)](file:///home/rafael/projects/cursos/plataforma_ead/spec/docs/mockups/10-login-convite.md)**: Layout split 42%/58% para autenticação e aceite de convites.

---

## **5. Regras Do/Don't do Design System (`readme.md`)**

**Fazer:**
- Deixar o grid modular aparente: células de largura igual, divisores horizontais fortes entre seções (`.hr`, `--color-divider`).
- Manter tudo alinhado à esquerda — títulos, corpo de texto e labels de botões largos.
- Usar o accent (`--color-accent`) com moderação — ação primária e pequenos destaques; o sistema é majoritariamente tinta sobre fundo claro.
- Fotografia sempre em preto-e-branco via `.grayscale`.
- Estados de foco/hover/pressed vêm da rampa accent (`--color-accent-600/700` hover/active) e `:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px }` — nunca o azul padrão do navegador.

**Não fazer:**
- Arredondar qualquer canto — `--radius-*` é 0px por decisão de marca, não esquecimento.
- Centralizar label de botão ou título de hero.
- Suavizar divisores em hairline ou trocá-los por espaçamento em branco.
- Colorir/tintar imagens — sempre grayscale.
- Hard-codar hex/px que já existem como token.

---

## **6. Checklist de Implementação & Testes E2E Laravel Dusk**

- [ ] Tokens Modernist em `resources/css/app.css` (§3), fonte Archivo self-hosted (§3.1), sem `@import` de CDN externo
- [ ] Bootstrap 5.3 restrito a grid + utilities (sem reboot/componentes) importado **antes** do `app.css` do Modernist
- [ ] Layout master em `layouts/app.blade.php` e `layouts/guest.blade.php`
- [ ] Componentes de layout: `<x-layout.topbar>` (`.nav`), `<x-layout.sidebar>` (§4.1 — variante escura via `--color-neutral-900`), `<x-layout.footer>`, `<x-layout.alerts>`
- [ ] Micro-componentes UI mapeados no §4: `<x-ui.button>`, `<x-ui.card>`, `<x-ui.modal>` (`.dialog`), `<x-ui.badge>` (`.tag`), `<x-ui.input>`, `<x-ui.select>`, `<x-ui.table>`, `<x-ui.stat-card>`, `<x-ui.icon>` (Lucide inline, §3.2)
- [ ] Módulos JavaScript Clean Code em `resources/js/modules/` (`HttpClient.js`, `ModalManager.js`, `NotificationService.js`)
- [ ] Harness: Criar/atualizar as 3 skills (`frontend-architecture`, `frontend-conventions`, `frontend-maintenance`) — incluir referência ao design system Modernist na skill `frontend-conventions`
- [ ] Testes E2E com Laravel Dusk (`tests/Browser/LayoutRenderingTest.php`, `tests/Browser/BladeComponentsTest.php`) aprovados com 100%, incluindo asserção de `border-radius: 0` e `:focus-visible` accent em pelo menos um componente interativo por tela.
