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

### **4.1. Gaps do Design System — resolvidos via mockup (Claude Design, projeto "Modernist system UI mockups", `projectId=a405d083-9548-4317-8df5-baa8dee6dca6`, arquivo `Plataforma EAD.dc.html`)**

Os 3 gaps abaixo, antes em aberto, foram resolvidos por mockups concretos de 6 telas (Login, Dashboard Admin, Vitrine de Componentes, Catálogo de Cursos, Player de Aula, Quiz). Nenhum introduz cor fora da paleta mono do Modernist.

- **`<select>` estilizado — resolvido**: `<select class="input" style="appearance:none;-webkit-appearance:none;padding-right:32px">` dentro de um `<div style="position:relative">`, com um `<svg>` chevron posicionado `position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;opacity:0.6` por cima. Some a seta nativa do browser, usa o ícone Lucide `chevron-down` no lugar. Markup completo na Tela 3 do mockup.
- **Paleta semântica — resolvida, sem cor nova**: os 4 status usados no mockup (Concluído/Live, Em andamento/Em revisão, Pendente/Rascunho, Vencido) mapeiam 1:1 para as 4 variantes de `.tag` já existentes — `tag-accent` (positivo), `tag-outline` (em progresso), `tag-neutral` (pendente/neutro), `tag-accent-2` (negativo/atrasado). Nenhum verde/amarelo/azul introduzido — resolve o gap mantendo o sistema mono. Aplicar esse mesmo mapeamento em matrícula (SPEC-07), resultado de prova (SPEC-08) e certificado (SPEC-09): aprovado/emitido = `tag-accent`, em andamento = `tag-outline`, pendente = `tag-neutral`, reprovado/revogado/vencido = `tag-accent-2`.
- **Sidebar escura — resolvida, confirma a recomendação anterior**: `background: var(--color-neutral-900)`, texto inativo `var(--color-neutral-400)`, texto ativo `var(--color-neutral-100)`. Item ativo NÃO preenche o fundo inteiro — usa `border-left: 3px solid var(--color-accent)` + tint sutil `color-mix(in srgb, var(--color-accent) 18%, transparent)` como background do item. Rodapé da sidebar com versão/role em `var(--color-neutral-600)` sobre borda `var(--color-neutral-800)`.

---

## **4.2. Padrões de Tela Confirmados (mockup — 6 telas)**

### **4.2.1. Topbar (`<x-layout.topbar>`)**

`.nav` com: `.nav-brand` → campo de busca (`.input` com ícone de lupa SVG absoluto à esquerda, `padding-left:32px`) → botão de notificações (`.btn.btn-ghost.btn-icon`, ícone sino) → bloco de usuário (avatar circular 30px `background:var(--color-neutral-800);color:var(--color-neutral-100)` com iniciais, nome 13px/600, role 11px em `--color-neutral-600`, chevron down).

### **4.2.2. Sidebar escura por role (`<x-layout.sidebar>`)**

Largura fixa `236px`, fundo `--color-neutral-900`. Grupo com label uppercase 10px (`--color-neutral-600`). Cada item: ícone Lucide 16px + label, `padding:10px 20px`, cor/fundo/borda conforme estado ativo (§4.1). Rodapé fixo (`margin-top:auto`) com versão e role atual.

### **4.2.3. Banner de Impersonate (`<x-ui.alert variant="info">`, usado em SPEC-04 Impersonate Org)**

Barra full-width abaixo do topbar: `background:var(--color-accent-100);color:var(--color-accent-800)`, ícone de info + texto "Visualizando como **{nome}** ({role}) — modo impersonate ativo." + botão `.btn.btn-secondary` (borda/texto em `--color-accent-700/800`) alinhado à direita "Sair da visualização".

### **4.2.4. Stat cards do Dashboard (SPEC-12)**

Grid `repeat(4,1fr)`, cada card `.card.elev-sm` com `.card-kicker` (label), valor grande `font-family:var(--font-heading);font-weight:800;font-size:30px`, e `.card-meta` com `.tag.tag-accent` mostrando o delta ("+4,2%") + texto "vs. mês anterior".

### **4.2.5. Card de curso (Catálogo, SPEC-05/07)**

`.card.elev-sm` com `padding:0`: `<img class="grayscale">` (capa, `height:150px`) no topo, depois bloco `padding:14px 16px` com `.card-kicker` (categoria) + `.tag` de status alinhado à direita, `.card-title`, `.card-meta` (duração), barra de progresso (`height:6px;background:var(--color-neutral-200)` com fill `background:var(--color-accent)` na largura `{{ progress }}%`), e `.btn.btn-secondary.btn-block` como CTA ("Continuar"/"Iniciar"/"Revisar" conforme status).

### **4.2.6. Player de aula (SPEC-07)**

Vídeo: bloco `background:var(--color-neutral-900)` com botão play circular sobreposto (`border:2px solid var(--color-neutral-100)`, ícone play preenchido). Barra de progresso do curso no topbar (mesma barra 6px do card). Sidebar direita (320px) lista lições: ícone check (concluída)/play (atual, borda+bg `--color-accent`/`--color-accent-100`)/lock (bloqueada, `--color-neutral-500`).

### **4.2.7. Quiz (SPEC-08)**

Card centralizado 680px: contador "Questão N de 10" + `.tag.tag-outline` "Obrigatório", barra de progresso do quiz, pergunta em `<h3>`, opções como `.radio` expandido (padding 14px/16px, borda e fundo destacados quando selecionada: `border-color:var(--color-accent);background:var(--color-accent-100)`), navegação `.btn.btn-ghost` (Anterior) / `.btn.btn-primary` (Próxima) com ícones seta.

### **4.2.8. Login/Convite (`layouts/guest.blade.php`)**

Split 42%/58%: painel esquerdo escuro (`--color-neutral-900`) com marca, indicador `56px` de 2px accent, headline `<h1>` até 9 caracteres de largura, texto institucional, copyright no rodapé; painel direito claro (`--color-bg`) com formulário centralizado 380px (`.field` + `.input`, radio "manter conectado", `.btn.btn-primary.btn-block`, divisor "ou", `.btn.btn-secondary.btn-block` para SSO).

> Nota de implementação: `browser-window.jsx`, `image-slot.js` e `support.js` do projeto de mockup são só ferramental de preview do Claude Design (moldura de navegador falsa, placeholder de imagem drag-and-drop) — **não portar para o Blade real**. No Laravel, vídeo/thumbnail usam `<img>`/`<iframe>` reais (SPEC-05, SPEC-07), não o custom element `<image-slot>`.

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
