# 07 — Plano de Migração Faseado

> **Playbook de execução** da migração do frontend para Bootstrap 5.3. Orquestra todos os
> work items identificados nos documentos 01–06, define oportunidades de paralelização,
> sequencia dependências críticas e estabelece critérios de saída para cada fase.
>
> **Status do projeto em 2026-08-12**: 78 views Blade, 634 `style=` inline, 316 `dusk=`,
> 14 módulos JS, 0 JS do Bootstrap carregado, Alpine inerte (4 arquivos), Tailwind morto.

---

## Sumário Executivo

A migração está organizada em **8 fases sequenciais** (0–7), cada uma com critérios de
entrada e saída explícitos, paralelização documentada e rollback planejado. O trabalho total
estima em **60–80 horas-persona** distribuídas em 2–3 sprints, com pico de paralelismo de
8–12 agentes simultâneos nas fases de telas (C–F).

| Fase | Foco | Arquivos | Paralelismo | Risco |
|:---|:---|:---:|:---:|:---:|
| 0 | Foundation & Build Pipeline | 8 arquivos | Serial | Alto — toca `package.json`, `vite.config.js`, `app.js` |
| 1 | Layouts & Componentes Globais | 18 arquivos | Baixo (1 PR) | Alto — layouts são shared |
| 2 | Parciais & Formulários | 12 arquivos | Médio (2–4 PRs) | Médio |
| 3 | Telas Index (read-only) | ~20 arquivos | **Alto (8–12 PRs)** | Baixo |
| 4 | Telas CRUD & Form | ~20 arquivos | **Alto (8–12 PRs)** | Médio |
| 5 | Telas JS-Heavy | ~10 arquivos | Médio (4–6 PRs) | **Alto** — player, quiz, forum |
| 6 | Telas Públicas & Guest | ~10 arquivos | Médio (4–6 PRs) | Baixo |
| 7 | Novos Componentes & Polish | ~20 componentes | Médio (3–5 PRs) | Baixo |

**Total**: ~40 arquivos de tela + ~40 componentes novos/reescritos + ~14 módulos JS.

---

## 0. Pré-requisitos

### 0.1 Verificações ambientais

Antes de iniciar qualquer fase, confirmar:

```bash
# 1. Sail está rodando
vendor/bin/sail ps
# Esperado: todos os containers "Up"

# 2. Dependências base instaladas
vendor/bin/sail npm ls
# Esperado: bootstrap@^5.3.3 instalado

# 3. Backup do public/build/ (para rollback rápido)
cp -r public/build public/build.backup.$(date +%Y%m%d)

# 4. Baseline Dusk verde
vendor/bin/sail npm run build
vendor/bin/sail artisan dusk --compact
# Esperado: 100% passed (gravar baseline para comparação)
```

### 0.2 Estratégia de branch

Criar branch de feature **antes da Fase 0**:

```bash
git checkout -b bootstrap-migration
git push -u origin bootstrap-migration
```

**Regra**: toda migração acontece neste branch. Merge para `main` só ao final da
Fase 7, após suíte Dusk completa verde.

### 0.3 Skills & Agents — bootstrap antes da Fase 1

As 3 skills e 5 subagentes especializados DEVEM existir antes do início da Fase 1.
Eles são criados em PR separado (preferencialmente antes da branch de migração) conforme
especificado no **documento 06-skills-and-agents.md**.

**Ordem de criação** (sequência obrigatória):

1. **`.agents/skills/bootstrap-architecture/SKILL.md`** — define a arquitetura de 5 camadas
2. **`.agents/skills/bootstrap-conventions/SKILL.md`** — define padrões de código
3. **`.agents/skills/bootstrap-maintenance/SKILL.md`** — define debug e verificação
4. **`.agents/agents/bootstrap-migrator.md`** — cavalo de tração da migração
5. **`.agents/agents/bootstrap-component-author.md`** — cria novos componentes
6. **`.agents/agents/bootstrap-js-refactorer.md`** — refactor de módulos JS
7. **`.agents/agents/bootstrap-design-reviewer.md`** — review visual
8. **`.agents/agents/bootstrap-visual-verifier.md`** — verificação cross-browser

Espelhar cada arquivo para `.claude/skills/*/SKILL.md` e `.claude/agents/*.md`.

> **Bloqueio**: a Fase 1 não inicia sem essas 3 skills ativas. O `bootstrap-migrator`
> depende delas em seu system prompt.

---

## 1. Fase 0 — Fundação & Pipeline de Build

**Objetivo**: estabelecer a infraestrutura SCSS, importar Bootstrap JS completo,
configurar variáveis do Modernist, preparar o registry de módulos JS e eliminar
dependências mortas.

**Dependências**: nenhuma (ponto zero da migração).

### 1.1 Tarefas (serial, único PR)

| # | Tarefa | Arquivo(s) | Mudança | Verificação |
|:--|:---|:---|:---|:---|
| 0.1 | **Aprovar e instalar `sass-embedded`** | `package.json` | `+ sass-embedded@^1.80.0` | `npm ls sass-embedded` |
| 0.2 | **Remover Tailwind** | `package.json`, `vite.config.js` | `- tailwindcss, - @tailwindcss/vite`, `- tailwindcss()` | `grep -i tailwind package.json vite.config.js` → vazio |
| 0.3 | **Remover bunny fonts** | `vite.config.js` | `- fonts: [bunny('Instrument Sans')]` | `grep bunny vite.config.js` → vazio |
| 0.4 | **Substituir Arquivo de 0 byte** | `public/fonts/archivo/*.woff2` | Baixar arquivos reais do Archivo v19 | `du -b public/fonts/archivo/*` → > 0 |
| 0.5 | **Criar pipeline SCSS** | `resources/scss/app.scss`<br>`resources/scss/_tokens.scss`<br>`resources/scss/_fonts.scss`<br>`resources/scss/_components.scss` | Novos; conteúdo completo em 04 §3.2 | `vendor/bin/sail npm run build` |
| 0.6 | **Atualizar Vite** | `vite.config.js` | `input: ['resources/scss/app.scss', 'resources/js/app.js']` | Build sucede |
| 0.7 | **Atualizar layouts** | `layouts/app.blade.php:10`<br>`layouts/guest.blade.php:10` | `@vite(['resources/scss/app.scss', 'resources/js/app.js'])` | Dusk smoke test |
| 0.8 | **Paginator Bootstrap** | `app/Providers/AppServiceProvider.php` | `Paginator::useBootstrapFive();` | 9 telas ganham estilo de páginação |
| 0.9 | **Registry de módulos JS** | `resources/js/modules/index.js` (novo)<br>`resources/js/app.js` (reescrever) | Import Bootstrap, registry, `window.bootstrap` | Build + Dusk smoke |
| 0.10 | **Mover quiz files** | `quiz-builder.js` → `modules/QuizBuilder.js`<br>`quiz-timer.js` → `modules/QuizTimer.js` | `git mv` + ajuste de import | Build |
| 0.11 | **Build e baseline** | — | `vendor/bin/sail npm run build` | Suíte Dusk verde |

### 1.2 Conteúdo completo dos arquivos criados

#### `resources/scss/app.scss`

```scss
// =============================================================================
//  Plataforma EAD — entrypoint SCSS
//  Ordem obrigatória do Bootstrap 5.3 (docs/5.3/customize/sass):
//  functions -> overrides de variáveis -> variables -> variables-dark
//  -> overrides de maps -> maps -> mixins -> root -> componentes
//  -> utilities/api -> código próprio
// =============================================================================

@use "sass:map";

// -----------------------------------------------------------------------------
// 1. Functions primeiro (habilita tint-color, shade-color, color-contrast...)
// -----------------------------------------------------------------------------
@import "bootstrap/scss/functions";

// -----------------------------------------------------------------------------
// 2. Tokens Modernist + overrides de variáveis (ANTES de `variables`)
// -----------------------------------------------------------------------------
@import "tokens";

// --- Cores de marca ---
$primary:   $modernist-accent;      // #ec3013
$secondary: $modernist-neutral-700;
$success:   $modernist-accent;      // mandato Modernist: sucesso usa o accent
$danger:    $modernist-accent-2;    // #e15b47
$warning:   $modernist-neutral-400;
$info:      $modernist-neutral-600;
$light:     $modernist-surface;
$dark:      $modernist-neutral-900;

// --- Superfícies e texto ---
$body-bg:              $modernist-bg;      // #f3f2f2
$body-color:           $modernist-text;    // #201e1d
$body-secondary-color: $modernist-neutral-600;
$body-tertiary-bg:     $modernist-surface; // #eae9e9
$border-color:         rgba($modernist-text, .4); // equivale ao --color-divider

// --- MANDATO MODERNIST: raio de borda é ZERO em todo o sistema ---------------
$enable-rounded:        false;  // desliga a emissão de border-radius no core
$border-radius:         0;
$border-radius-sm:      0;
$border-radius-lg:      0;
$border-radius-xl:      0;
$border-radius-xxl:     0;
$border-radius-pill:    0;

// --- Sombras -----------------------------------------------------------------
$enable-shadows:  false;  // não adiciona gradiente/sombra "3D" aos componentes
$box-shadow-sm:   0 1px 2px  rgba($modernist-neutral-900, .14);
$box-shadow:      0 3px 10px rgba($modernist-neutral-900, .16);
$box-shadow-lg:   0 12px 32px rgba($modernist-neutral-900, .22);

// --- Outros flags ------------------------------------------------------------
$enable-gradients:          false;
$enable-transitions:        true;
$enable-negative-margins:   true;
$enable-smooth-scroll:      true;
$enable-cssgrid:            false;

// --- Tipografia --------------------------------------------------------------
$font-family-sans-serif: $modernist-font-family;
$font-size-base:         0.875rem;   // 14px — base atual dos estilos inline
$headings-font-family:   $modernist-font-family;
$headings-font-weight:   $modernist-heading-weight; // 800
$headings-color:         $modernist-text;

// --- Espaçamento (mapeia --space-1..8 para a escala do Bootstrap) ------------
$spacer: 1rem;

// --- Foco (regra :focus-visible atual usa accent com offset 2px) -------------
$focus-ring-width:   2px;
$focus-ring-opacity: 1;
$focus-ring-color:   $modernist-accent;

// -----------------------------------------------------------------------------
// 3. Variáveis do Bootstrap
// -----------------------------------------------------------------------------
@import "bootstrap/scss/variables";
@import "bootstrap/scss/variables-dark";

// -----------------------------------------------------------------------------
// 4. Overrides de MAPS (depois de `variables`, antes de `maps`)
// -----------------------------------------------------------------------------
$theme-colors: map.merge($theme-colors, (
  "accent":     $modernist-accent,
  "accent-2":   $modernist-accent-2,
  "surface":    $modernist-surface,
  "neutral":    $modernist-neutral-700,
));

$spacers: map.merge($spacers, (
  "1x": 4px,   // --space-1
  "2x": 8px,   // --space-2
  "3x": 12px,  // --space-3
  "4x": 16px,  // --space-4
  "6x": 24px,  // --space-6
  "8x": 32px,  // --space-8
));

// -----------------------------------------------------------------------------
// 5. Núcleo obrigatório
// -----------------------------------------------------------------------------
@import "bootstrap/scss/maps";
@import "bootstrap/scss/mixins";
@import "bootstrap/scss/utilities";
@import "bootstrap/scss/root";

// -----------------------------------------------------------------------------
// 6. Componentes (lista explícita — remover o que comprovadamente não for usado)
// -----------------------------------------------------------------------------
@import "bootstrap/scss/reboot";
@import "bootstrap/scss/type";
@import "bootstrap/scss/images";
@import "bootstrap/scss/containers";
@import "bootstrap/scss/grid";
@import "bootstrap/scss/tables";
@import "bootstrap/scss/forms";
@import "bootstrap/scss/buttons";
@import "bootstrap/scss/transitions";
@import "bootstrap/scss/dropdown";
@import "bootstrap/scss/button-group";
@import "bootstrap/scss/nav";
@import "bootstrap/scss/navbar";
@import "bootstrap/scss/card";
@import "bootstrap/scss/accordion";
@import "bootstrap/scss/breadcrumb";
@import "bootstrap/scss/pagination";
@import "bootstrap/scss/badge";
@import "bootstrap/scss/alert";
@import "bootstrap/scss/progress";
@import "bootstrap/scss/list-group";
@import "bootstrap/scss/close";
@import "bootstrap/scss/toasts";
@import "bootstrap/scss/modal";
@import "bootstrap/scss/tooltip";
@import "bootstrap/scss/popover";
@import "bootstrap/scss/spinners";
@import "bootstrap/scss/offcanvas";
@import "bootstrap/scss/placeholders";
@import "bootstrap/scss/helpers";

// -----------------------------------------------------------------------------
// 7. Utilities API por último (gera as classes a partir de $utilities)
// -----------------------------------------------------------------------------
@import "bootstrap/scss/utilities/api";

// -----------------------------------------------------------------------------
// 8. Camadas próprias do projeto
// -----------------------------------------------------------------------------
@import "fonts";       // @font-face do Archivo
@import "components";  // .sidebar-item, .stat-card, etc.

// -----------------------------------------------------------------------------
// 9. Shim de compatibilidade — aliases `--color-*` legados
//    Mantém as ~634 declarações inline existentes funcionando DURANTE a
//    migração incremental. REMOVER quando o último `var(--color-*)` sair
//    das Blades (rastrear como dívida: `grep -rn "var(--color-" resources/views | wc -l`).
// -----------------------------------------------------------------------------
:root {
  --color-bg:      var(--bs-body-bg);
  --color-surface: var(--bs-tertiary-bg);
  --color-text:    var(--bs-body-color);
  --color-accent:  var(--bs-primary);
  --color-accent-2: var(--bs-danger);
  --color-divider: var(--bs-border-color);

  --color-neutral-100: #{$modernist-neutral-100};
  --color-neutral-200: #{$modernist-neutral-200};
  --color-neutral-300: #{$modernist-neutral-300};
  --color-neutral-400: #{$modernist-neutral-400};
  --color-neutral-500: #{$modernist-neutral-500};
  --color-neutral-600: #{$modernist-neutral-600};
  --color-neutral-700: #{$modernist-neutral-700};
  --color-neutral-800: #{$modernist-neutral-800};
  --color-neutral-900: #{$modernist-neutral-900};

  --color-accent-100: #{$modernist-accent-100};
  --color-accent-200: #{$modernist-accent-200};
  --color-accent-300: #{$modernist-accent-300};
  --color-accent-400: #{$modernist-accent-400};
  --color-accent-500: #{$modernist-accent-500};
  --color-accent-600: #{$modernist-accent-600};
  --color-accent-700: #{$modernist-accent-700};
  --color-accent-800: #{$modernist-accent-800};
  --color-accent-900: #{$modernist-accent-900};

  --color-accent-2-100: #{$modernist-accent-2-100};
  --color-accent-2-200: #{$modernist-accent-2-200};
  --color-accent-2-300: #{$modernist-accent-2-300};
  --color-accent-2-400: #{$modernist-accent-2-400};
  --color-accent-2-500: #{$modernist-accent-2-500};
  --color-accent-2-600: #{$modernist-accent-2-600};
  --color-accent-2-700: #{$modernist-accent-2-700};
  --color-accent-2-800: #{$modernist-accent-2-800};
  --color-accent-2-900: #{$modernist-accent-2-900};

  --font-heading:        var(--bs-body-font-family);
  --font-heading-weight: #{$modernist-heading-weight};
  --font-body:           var(--bs-body-font-family);

  --space-1: 4px;  --space-2: 8px;  --space-3: 12px;
  --space-4: 16px; --space-6: 24px; --space-8: 32px;

  --radius-sm: 0px; --radius-md: 0px; --radius-lg: 0px;

  --shadow-sm: #{$box-shadow-sm};
  --shadow-md: #{$box-shadow};
  --shadow-lg: #{$box-shadow-lg};
}

// Tratamento de imagem em escala de cinza + isenção para logo de organização
// (regras preservadas do app.css atual).
.grayscale { filter: grayscale(1) contrast(1.08); }
.org-logo,
.grayscale.org-logo,
img.org-logo { filter: none !important; }
```

#### `resources/scss/_tokens.scss`

```scss
// =============================================================================
//  Modernist Design System — FONTE ÚNICA DE VERDADE
//  Estes valores alimentam as variáveis SCSS do Bootstrap (ver app.scss).
// =============================================================================
// --- Paleta base -------------------------------------------------------------
$modernist-bg:        #f3f2f2;
$modernist-surface:   #eae9e9;
$modernist-text:      #201e1d;
$modernist-accent:    #ec3013;
$modernist-accent-2:  #e15b47;

// --- Rampa neutra (100–900) --------------------------------------------------
$modernist-neutral-100: #f8f4f4;
$modernist-neutral-200: #eae7e7;
$modernist-neutral-300: #d7d3d3;
$modernist-neutral-400: #bab6b6;
$modernist-neutral-500: #9b9797;
$modernist-neutral-600: #7d7979;
$modernist-neutral-700: #605d5d;
$modernist-neutral-800: #444141;
$modernist-neutral-900: #2d2b2b;

// --- Rampa accent (100–900) --------------------------------------------------
$modernist-accent-100: #fff2ef;
$modernist-accent-200: #ffe0d9;
$modernist-accent-300: #ffc4b8;
$modernist-accent-400: #ff9783;
$modernist-accent-500: #ff563c;
$modernist-accent-600: #dd2b0f;
$modernist-accent-700: #ae1800;
$modernist-accent-800: #7c1405;
$modernist-accent-900: #4d170e;

// --- Rampa accent-2 (100–900) ------------------------------------------------
$modernist-accent-2-100: #fff2ef;
$modernist-accent-2-200: #ffe0da;
$modernist-accent-2-300: #ffc4b9;
$modernist-accent-2-400: #ff9784;
$modernist-accent-2-500: #ef6853;
$modernist-accent-2-600: #c94b39;
$modernist-accent-2-700: #9e3526;
$modernist-accent-2-800: #71261b;
$modernist-accent-2-900: #471d16;

// --- Tipografia --------------------------------------------------------------
$modernist-font-family:    "Archivo", system-ui, -apple-system, "Segoe UI", sans-serif;
$modernist-heading-weight: 800;
```

#### `resources/scss/_fonts.scss`

```scss
// Archivo v19, subset latin. ATENÇÃO: substituir os arquivos de 0 byte em
// public/fonts/archivo/ pelos arquivos reais antes de usar este arquivo.
@font-face {
  font-family: "Archivo";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url("/fonts/archivo/archivo-v19-latin-400.woff2") format("woff2");
}
@font-face {
  font-family: "Archivo";
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url("/fonts/archivo/archivo-v19-latin-600.woff2") format("woff2");
}
@font-face {
  font-family: "Archivo";
  font-style: normal;
  font-weight: 800;
  font-display: swap;
  src: url("/fonts/archivo/archivo-v19-latin-800.woff2") format("woff2");
}
```

#### `resources/scss/_components.scss`

```scss
// Camada própria — só entra aqui o que NÃO existe no Bootstrap.
// Regra: se um utilitário Bootstrap resolve, use o utilitário na Blade.

.app-shell        { display: flex; flex-direction: column; min-height: 100vh; }
.app-body         { display: flex; flex: 1; position: relative; }
.app-main         { flex: 1; min-width: 0; padding: var(--space-6); background: var(--bs-body-bg); }

.sidebar          { width: 240px; background: $modernist-neutral-900; color: $modernist-neutral-400; flex-shrink: 0; }
.sidebar-item     { display: flex; align-items: center; gap: var(--space-3);
                   padding: 11px 20px; font-size: 13px; font-weight: 600;
                   text-decoration: none; color: $modernist-neutral-400;
                   border-left: 3px solid transparent;
                   &.active { color: $modernist-neutral-100;
                              border-left-color: $primary;
                              background: rgba($modernist-accent, .18); } }
.sidebar-section-title { font-size: 10px; letter-spacing: .1em; text-transform: uppercase;
                          color: $modernist-neutral-600; padding: 20px 20px 8px; font-weight: 700; }

.stat-card        { /* usado por dashboard-conventions <x-ui.stat-card> */ }
.forum-reply      { /* alvo do template clonado por ForumPolling.appendReply() */ }
```

#### `resources/js/app.js` (pós-Fase 0)

```js
// -----------------------------------------------------------------------------
// Entrypoint. Estável por design: adicionar/remover um módulo NÃO toca este
// arquivo — edite `modules/index.js`. Isso elimina a serialização de PRs em
// torno de um arquivo compartilhado.
// -----------------------------------------------------------------------------

// Bootstrap COMPLETO (com Popper). Necessário para que os listeners de
// data-api (`data-bs-toggle`, `data-bs-dismiss`) sejam registrados: cada
// componente só responde a data-attributes se seu módulo tiver sido avaliado.
// Verificado em node_modules/bootstrap/js/dist/modal.js:284 e alert.js:79.
import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';

import registry from './modules/index.js';

// `window.bootstrap` é o contrato que a suíte Dusk usa para dirigir modais e
// toasts programaticamente (`bootstrap.Modal.getOrCreateInstance(...)`).
window.bootstrap = bootstrap;

// Tooltip e Popover são opt-in por decisão de performance do Bootstrap:
// `data-bs-toggle="tooltip"` NÃO auto-inicializa. Init explícito aqui.
const initOptIns = () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
    document.querySelectorAll('[data-bs-toggle="popover"]')
        .forEach((el) => bootstrap.Popover.getOrCreateInstance(el));
};

const boot = () => {
    initOptIns();

    Object.entries(registry).forEach(([name, instance]) => {
        window[name] = instance;
        if (typeof instance.init === 'function') {
            try {
                instance.init();
            } catch (error) {
                console.error(`[app] falha ao inicializar ${name}`, error);
            }
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
```

#### `resources/js/modules/index.js` (pós-Fase 0)

```js
// -----------------------------------------------------------------------------
// Registry único de módulos. Cada chave vira `window.<chave>` e recebe
// `.init()` após o DOMContentLoaded. Este é o ÚNICO arquivo que muda quando um
// módulo entra ou sai — mantenha-o em ordem alfabética para minimizar conflito.
// -----------------------------------------------------------------------------
import AuditLogDiffModal   from './AuditLogDiffModal';
import CsvImporter         from './CsvImporter';
import ForumPolling        from './ForumPolling';
import ForumReportModal    from './ForumReportModal';
import HttpClient          from './HttpClient';
import LessonPlayer        from './LessonPlayer';
import ModuleReorder       from './ModuleReorder';
import NotificationBell    from './NotificationBell';
import NotificationService from './NotificationService';
import QuizBuilder         from './QuizBuilder';
import QuizTimer           from './QuizTimer';
import SmartInvitationForm from './SmartInvitationForm';

// ModalManager e ForumEditHistory foram REMOVIDOS: substituídos por
// bootstrap.Modal + data-bs-toggle/data-bs-dismiss.

const httpClient   = HttpClient;          // singleton
const notifications = NotificationService; // singleton (agora sobre bootstrap.Toast)

export default {
    HttpClient:          httpClient,
    NotificationService: notifications,
    AuditLogDiffModal:   new AuditLogDiffModal(),
    CsvImporter:         new CsvImporter(httpClient),
    ForumPolling:        new ForumPolling(httpClient),
    ForumReportModal:    new ForumReportModal(httpClient, notifications),
    LessonPlayer:        new LessonPlayer(httpClient, notifications),
    ModuleReorder:       new ModuleReorder(httpClient, notifications),
    NotificationBell:    new NotificationBell(httpClient),
    QuizBuilder:         new QuizBuilder(notifications),
    QuizTimer:           new QuizTimer(),
    SmartInvitationForm: new SmartInvitationForm(httpClient, notifications),
};
```

#### `vite.config.js` (pós-Fase 0)

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

### 1.3 Entry Criteria

- [ ] Branch `bootstrap-migration` criada a partir de `main` atualizada
- [ ] Skills `bootstrap-*` triade criadas e ativas
- [ ] Subagentes especializados criados
- [ ] Baseline Dusk verde gravado

### 1.4 Exit Criteria

- [ ] `sass-embedded` instalado, Tailwind e bunny removidos
- [ ] `resources/css/app.css` **deletado**
- [ ] `resources/scss/*` criados com conteúdo acima
- [ ] `app.js` e `modules/index.js` reescritos
- [ ] `Paginator::useBootstrapFive()` em `AppServiceProvider`
- [ ] Build sucede sem erros
- [ ] Suíte Dusk smoke pass (`ExampleSmokeTest`, `LayoutRenderingTest`)
- [ ] 3 arquivos Archivo `*.woff2` têm > 0 bytes

### 1.5 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="LayoutRenderingTest|ExampleSmokeTest"
```

### 1.6 Rollback Strategy

```bash
# Reverter todas as mudanças da Fase 0
git checkout main -- package.json package-lock.json vite.config.js
git checkout main -- resources/js/app.js resources/js/modules/
rm -rf resources/scss resources/css/app.css
git checkout main -- resources/css/app.css
git checkout main -- app/Providers/AppServiceProvider.php
vendor/bin/sail npm install && vendor/bin/sail npm run build
```

Ou restaurar do backup: `cp -r public/build.backup.YYYYMMDD public/build`.

---

## 2. Fase 1 — Layouts & Componentes Globais (Wave A)

**Objetivo**: migrar layouts master (`app`, `guest`) e todos os 16 componentes
existentes (`x-ui.*` + `x-layout.*` + componentes avulsos).

**Dependências**: Fase 0 completa.

### 2.1 Unidades de trabalho paralelas

| Unidade | Arquivos | Conflitos | Observação |
|:---|:---|:---|:---|
| **L1** | `layouts/app.blade.php`<br>`layouts/guest.blade.php` | Nenhum (serial entre si) | Shell da aplicação, alto risco |
| **L2** | `components/layout/topbar.blade.php`<br>`components/layout/sidebar.blade.php`<br>`components/layout/footer.blade.php`<br>`components/layout/alerts.blade.php` | Consomem L1 | Sidebar: offcanvas mobile |
| **L3** | `components/ui/alert.blade.php`<br>`components/ui/modal.blade.php` | Nenhum | BUG-004 e BUG-003 resolvem aqui |
| **L4** | `components/ui/button.blade.php`<br>`components/ui/input.blade.php`<br>`components/ui/select.blade.php`<br>`components/ui/badge.blade.php`<br>`components/ui/card.blade.php`<br>`components/ui/icon.blade.php` | Nenhum | Componentes UI mais usados |
| **L5** | `components/ui/table.blade.php`<br>`components/ui/stat-card.blade.php` | Nenhum | Dashboard depende de stat-card |
| **L6** | `components/help-button.blade.php`<br>`components/notifications-bell.blade.php` | Consomem L3 + L2 + L4 | JS pesado (polling, modal) |

**Total**: 18 arquivos em 6 unidades. **Recomendação**: executar L1–L6 em sequência
(pois L1 é blocker para todos), mas L4–L6 podem ser paralelas entre si.

### 2.2 Padrão de migração por componente

Cada componente segue o padrão do `bootstrap-conventions` §1:

1. Manter `@props` existente
2. Adicionar bloco `@php` com `match()` de variantes
3. Emitir markup Bootstrap correspondente
4. `$attributes->merge(['class' => $classes])` **obrigatório**
5. Preservar `dusk=` via `merge()` ou prop explícita
6. Zero `style=`

**Exemplo** (`x-ui.button`):

```blade
@props([
    'variant' => 'primary',
    'size' => 'md',
    'block' => false,
    'icon' => null,
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-outline-secondary',
        'ghost' => 'btn-link text-body text-decoration-none',
        'danger' => 'btn-danger',
        default => 'btn-primary',
    };

    $classes = collect([
        'btn',
        $variantClass,
        $size === 'sm' ? 'btn-sm' : ($size === 'lg' ? 'btn-lg' : null),
        $block ? 'w-100' : null,
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-ui.icon :name="$icon" size="16" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-ui.icon :name="$icon" size="16" /> @endif
        {{ $slot }}
    </button>
@endif
```

### 2.3 Migrações críticas de JS

Fase 1 também inclui refactor de 3 módulos JS que tocam `app.js` (bloqueante).

#### Tarefa J1: `NotificationService` → `bootstrap.Toast`

- **Arquivo**: `resources/js/modules/NotificationService.js`
- **Mudança**: reimplementar sobre `bootstrap.Toast`, preservando assinatura pública
- **Consumidores**: 6 módulos injetam esta instância
- **Build**: `npm run build` + Dusk `NotificationBellTest`

#### Tarefa J2: `NotificationBell` → `bootstrap.Dropdown`

- **Arquivo**: `resources/js/modules/NotificationBell.js`
- **Mudança**: remover `toggleDropdown/closeDropdown`, usar `shown.bs.dropdown`
- **Build**: `npm run build` + Dusk `NotificationBellTest`

#### Tarefa J3: Deletar `ModalManager` e `ForumEditHistory`

- **Arquivos**: `resources/js/modules/ModalManager.js`, `ForumEditHistory.js`
- **Mudança**: **deletar** (substituídos por `bootstrap.Modal`)
- **Remover do registry** em `modules/index.js`
- **Build**: `npm run build` + Dusk smoke

### 2.4 Entry Criteria

- [ ] Fase 0 completa e verde
- [ ] `app.js` está "congelado" (nenhuma mudança mais nesta fase)
- [ ] Skills `bootstrap-*` ativas

### 2.5 Exit Criteria

- [ ] Todos os 18 arquivos migrados (zero `style=` inline)
- [ ] BUG-003 e BUG-004 resolvidos (confirmado por Dusk)
- [ ] `ModalManager.js` e `ForumEditHistory.js` **deletados**
- [ ] Sidebar mobile funcional (`bootstrap.Offcanvas`)
- [ ] Menu mobile não abre → **abre** ( correção do drawer morto)
- [ ] Suíte Dusk de componentes e layout verde

### 2.6 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="BladeComponentsTest|LayoutRenderingTest"
```

### 2.7 Rollback Strategy

```bash
# Reverter apenas os components e layouts (Fase 1)
git checkout main -- resources/views/layouts/ resources/views/components/
git checkout main -- resources/js/modules/ModalManager.js resources/js/modules/ForumEditHistory.js
git checkout main -- resources/js/modules/index.js resources/js/app.js
vendor/bin/sail npm run build
```

---

## 3. Fase 2 — Parciais Compartilhados & Formulários (Wave B)

**Objetivo**: migrar todos os parciais `_*.blade.php` (formulários e componentes
reutilizáveis de tela).

**Dependências**: Fase 1 completa (componentes `<x-ui.*>` base disponíveis).

### 3.1 Unidades de trabalho paralelas

| Unidade | Arquivos | Componentes usados | Observação |
|:---|:---|:---|:---|
| **P1** | `courses/_form.blade.php`<br>`courses/modules/_form.blade.php` | x-ui.field-stack, x-ui.input, x-ui.textarea, x-ui.checkbox | 2 cursos |
| **P2** | `organizations/_form.blade.php` | + x-ui.select, x-ui.file-input | 1 org |
| **P3** | `modules/lessons/_form.blade.php` | + select de tipo, file-input ×2, preview YouTube | **JS inline** + ModuleReorder |
| **P4** | `quizzes/partials/_question-form.blade.php` | + select de tipo, x-ui.option-row, template | **QuizBuilder.js** acoplado |
| **P5** | `courses/modules/_list.blade.php`<br>`quizzes/partials/_question-list.blade.php` | x-ui.sortable-list, x-ui.sortable-item | **ModuleReorder.js** |
| **P6** | `forum/partials/_topic.blade.php`<br>`forum/partials/_reply.blade.php` | x-ui.post-card (novo) | **ForumPolling.js** espelha _reply |
| **P7** | `forum/partials/_edit-history-modal.blade.php` | x-ui.modal | **ForumEditHistory** — deletar na Fase 1 |
| **P8** | `audit-logs/partials/_diff-modal.blade.php` | x-ui.modal | **AuditLogDiffModal.js** |
| **P9** | `classroom/partials/_video.blade.php`<br>`classroom/partials/_pdf.blade.php`<br>`classroom/partials/_text-image.blade.php`<br>`classroom/partials/_quiz-placeholder.blade.php` | .ratio, .card, x-ui.badge, x-ui.button | **LessonPlayer.js** |

**Total**: 14 arquivos em 9 unidades. **Paralelização**: P1–P4 e P5–P9 podem ser
simultâneas (4–4 agentes), exceto P6 que precisa de `<x-ui.post-card>` criado antes
(ver Fase 7).

### 3.2 Entry Criteria

- [ ] Fase 1 completa
- [ ] `<x-ui.option-row>` disponível (ou criar em P4)
- [ ] `<x-ui.post-card>` disponível (ou criar em P6)

### 3.3 Exit Criteria

- [ ] Todos os 14 parciais migrados
- [ ] Zero `style=` em parciais (exceto valores dinâmicos de progresso)
- [ ] `QuizTimer.js` + `QuizBuilder.js` atualizados (remove `style.display`)
- [ ] `ForumPolling.js` atualizado (remove `style.cssText` em `appendReply`)
- [ ] Dusk dos parciais verde

### 3.4 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="CourseManagementTest|ModuleReorderTest|EssayGradingScreenTest"
```

### 3.5 Rollback Strategy

```bash
git checkout main -- resources/views/courses/ resources/views/organizations/ resources/views/modules/ resources/views/quizzes/ resources/views/forum/ resources/views/audit-logs/ resources/views/classroom/
vendor/bin/sail npm run build
```

---

## 4. Fase 3 — Telas Index Read-Only (Wave C)

**Objetivo**: migrar ~15–20 telas de listagem (CRUD index), baixo risco, alta
paralelização.

**Dependências**: Fase 2 completa (parciais de formulário e listagem prontos).

### 4.1 Telas em ordem de risco (do menor ao maior)

| Tela | Risco | Dusk Filter | Novos componentes |
|:---|:---:|:---|:---|
| `dashboard/index.blade.php` | Baixo | `DashboardDuskTest` | x-ui.stat-card |
| `organizations/index.blade.php` | Baixo | `OrganizationCrudTest` | x-ui.data-table |
| `users/index.blade.php` | Baixo | `UserManagementTest` | x-ui.filter-bar (opcional) |
| `courses/index.blade.php` | Baixo | `CourseManagementTest` | x-ui.data-table |
| `courses/modules/index.blade.php` | Médio (reorder) | `ModuleReorderTest` | x-ui.sortable-list |
| `modules/lessons/index.blade.php` | Médio (reorder) | `CourseManagementTest` | x-ui.sortable-list |
| `courses/enrollments/index.blade.php` | Baixo | `UserManagementTest` | x-ui.data-table |
| `courses/invitation-links/index.blade.php` | Baixo | (sem Dusk) | x-ui.data-table |
| `courses/completion-rules/index.blade.php` | Baixo | `CourseCompletionRuleTest` | x-ui.data-table |
| `certificates/index.blade.php` | Médio (modal N) | `CertificateRevocationTest` | x-ui.data-table, x-ui.confirm-modal |
| `forum/moderation/index.blade.php` | Baixo | `ForumDuskTest` | x-ui.data-table |
| `audit-logs/index.blade.php` | Médio (modal diff) | `AuditLogUiTest` | x-ui.filter-bar |
| `landing/show.blade.php` | Baixo | `BladeComponentsTest` | x-layout.public |
| `public/certificates/show.blade.php` | Baixo | `CertificateVerificationTest` | x-layout.public |

**Total**: ~15 telas. **Paralelização**: 8–10 `bootstrap-migrator` simultâneos, cada um
com 1–2 telas.

### 4.2 Padrão de tela index

Tela index típica após migração:

```blade
<x-layout.page-header kicker="Administração" title="Organizações">
    <x-slot:actions>
        <x-ui.button href="{{ route('organizations.create') }}" dusk="new-organization">
            Nova Organização
        </x-ui.button>
    </x-slot:actions>
</x-layout.page-header>

<x-ui.alert variant="warning" dismissable>
    Você está impersonando a organização {{ session('active_org_name') }}.
    <form method="POST" action="{{ route('impersonation.exit') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-link text-decoration-underline p-0">Sair do contexto</button>
    </form>
</x-ui.alert>

<x-ui.data-table striped hover responsive :headers="['Nome','Slug','CNPJ','Status','Ações']">
    @foreach($organizations as $org)
        <tr dusk="organization-row-{{ $org->id }}">
            <td>{{ $org->name }}</td>
            <td class="font-monospace small">{{ $org->slug }}</td>
            <td>{{ $org->cnpj?->formatted }}</td>
            <td>{{ $org->status->label }}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <form method="POST" action="{{ route('impersonation.enter', $org) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary" dusk="impersonate-{{ $org->id }}">
                            Acessar
                        </button>
                    </form>
                    <x-ui.button href="{{ route('organizations.edit', $org) }}" size="sm" dusk="edit-organization-{{ $org->id }}">
                        Editar
                    </x-ui.button>
                    <x-ui.delete-button :action="route('organizations.destroy', $org)" label="Remover" dusk="delete-organization-{{ $org->id }}" />
                </div>
            </td>
        </tr>
    @endforeach
</x-ui.data-table>

<x-ui.empty-state colspan="5" message="Nenhuma organização cadastrada." />
<x-ui.pagination :paginator="$organizations" />
```

### 4.3 Entry Criteria

- [ ] Fase 2 completa
- [ ] Componentes novos necessários criados (`x-ui.data-table`, `x-ui.pagination`, `x-ui.empty-state`, `x-ui.filter-bar`)

### 4.4 Exit Criteria

- [ ] Todas as 15 telas migradas
- [ ] Zero `style=` em telas index
- [ ] Suíte Dusk destas telas verde
- [ ] Paginação com estilo Bootstrap em 9 telas

### 4.5 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="DashboardDuskTest|OrganizationCrudTest|UserManagementTest|CourseManagementTest|ModuleReorderTest|CourseCompletionRuleTest|CertificateRevocationTest|ForumDuskTest|AuditLogUiTest|CertificateVerificationTest"
```

### 4.6 Rollback Strategy

```bash
# Reverter telas index por módulo
git checkout main -- resources/views/dashboard/ resources/views/organizations/ resources/views/users/ resources/views/courses/ resources/views/modules/ resources/views/certificates/ resources/views/forum/ resources/views/audit-logs/ resources/views/landing/ resources/views/public/
vendor/bin/sail npm run build
```

---

## 5. Fase 4 — Telas CRUD & Form (Wave D)

**Objetivo**: migrar ~15–20 telas de criação/edição (create/edit), maior
complexidade de formulário.

**Dependências**: Fase 3 completa.

### 5.1 Telas CRUD em ordem

| Tela | Risco | Dusk Filter | Observação |
|:---|:---:|:---|:---|
| `organizations/create.blade.php` | Baixo | `OrganizationCrudTest` | Usa `_form` |
| `organizations/edit.blade.php` | Baixo | `OrganizationCrudTest` | Usa `_form` |
| `users/create.blade.php` | Baixo | `UserManagementTest` | 5 inputs + select |
| `users/edit.blade.php` | Baixo | `UserManagementTest` | + select de status |
| `courses/create.blade.php` | Baixo | `CourseManagementTest` | Usa `_form` |
| `courses/edit.blade.php` | Baixo | `CourseManagementTest` | Usa `_form` |
| `courses/modules/create.blade.php` | Baixo | `CourseManagementTest` | Usa `_form` |
| `courses/modules/edit.blade.php` | Baixo | `CourseManagementTest` | Usa `_form` |
| `modules/lessons/create.blade.php` | **Alto** | `LessonMultimediaTest` | **JS inline** no _form |
| `modules/lessons/edit.blade.php` | **Alto** | `LessonMultimediaTest` | **JS inline** no _form |
| `quizzes/create.blade.php` | Médio | `EssayGradingScreenTest` | Campos de quiz |
| `quizzes/edit.blade.php` | **Alto** | `EssayGradingScreenTest` | **N modais**, QuizBuilder |
| `forum/create.blade.php` | Baixo | `ForumDuskTest` | Form simples |
| `forum/edit.blade.php` | Baixo | `ForumDuskTest` | Form simples |
| `convite/show.blade.php` | Médio | `MultiOrgEnrollmentTest` | SmartInvitationForm |

**Total**: ~15 telas. **Paralelização**: 6–8 agentes, mas as 2 de lesson e quizzes/edit
devem ser serializadas ou feitas por `opus` com mais cuidado.

### 5.2 Padrão de tela CRUD

```blade
<x-ui.card :title="$title" :kicker="$kicker">
    <form dusk="{{ $resource }}-form" method="POST" @if($edit) @method('PUT') @endif action="{{ $action }}">
        @csrf
        
        <x-ui.field-stack>
            <x-ui.input name="title" label="Título" required />
            <x-ui.textarea name="description" label="Descrição" rows="4" />
            <x-ui.input type="number" name="workload_hours" label="Carga Horária (h)" />
            <x-ui.checkbox name="is_published" label="Publicado" :checked="$model->is_published ?? false" />
        </x-ui.field-stack>

        <x-ui.form-actions>
            <x-ui.button type="submit" variant="primary">Salvar</x-ui.button>
            <x-ui.button href="{{ route($indexRoute) }}" variant="secondary">Cancelar</x-ui.button>
        </x-ui.form-actions>
    </form>
</x-ui.card>
```

### 5.3 Entry Criteria

- [ ] Fase 3 completa
- [ ] Componentes `<x-ui.field-stack>`, `<x-ui.form-actions>` disponíveis
- [ ] `<x-ui.checkbox>` criado

### 5.4 Exit Criteria

- [ ] Todas as 15 telas migradas
- [ ] Zero `style=` em telas CRUD
- [ ] Suíte Dusk destas telas verde
- [ ] JS inline de `modules/lessons/_form` extraído para módulo ou confirmado funcional

### 5.5 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="OrganizationCrudTest|UserManagementTest|CourseManagementTest|LessonMultimediaTest|EssayGradingScreenTest|ForumDuskTest|MultiOrgEnrollmentTest"
```

### 5.6 Rollback Strategy

```bash
git checkout main -- resources/views/organizations/ resources/views/users/ resources/views/courses/ resources/views/modules/ resources/views/quizzes/ resources/views/forum/ resources/views/convite/
vendor/bin/sail npm run build
```

---

## 6. Fase 5 — Telas JS-Heavy Interativas (Wave E)

**Objetivo**: migrar as telas de maior risco — player de aula, quiz aluno,
fórum com polling, certificados com modais.

**Dependências**: Fase 4 completa.

### 6.1 Telas JS-Heavy em ordem de risco

| Tela | Risco | Dusk Filter | Módulo JS | Mudança JS |
|:---|:---:|:---|:---|:---|
| `classroom/show.blade.php` | Médio | `MultiOrgStudentClassroomTest` | — | Apenas markup |
| `classroom/lesson.blade.php` | Médio | `MultiOrgStudentClassroomTest`<br>`VideoThresholdCompletionTest` | **LessonPlayer** | `reportProgress` preservado |
| `classroom/partials/_video.blade.php` | Médio | `VideoThresholdCompletionTest` | **LessonPlayer** | `.ratio` + badge `d-none` |
| `classroom/partials/_pdf.blade.php` | Médio | `LessonMultimediaTest` | **LessonPlayer** | badge `d-none` |
| `classroom/partials/_text-image.blade.php` | Médio | `LessonMultimediaTest` | **LessonPlayer** | badge `d-none` |
| `student/courses/index.blade.php` | Baixo | `MultiOrgStudentClassroomTest` | — | Grid + progress bar |
| `student/quizzes/show.blade.php` | **Alto** | `StudentQuizAttemptTest` | **QuizTimer** | `.badge`, `.progress` |
| `forum/index.blade.php` | Médio | `ForumDuskTest` | — | Modal + lista |
| `forum/show.blade.php` | **Alto** | `ForumDuskTest` | **ForumPolling**<br>**ForumReportModal** | `appendReply` clona template |
| `users/import.blade.php` | Médio | `MultiTenantStudentImportTest` | **CsvImporter** | `.progress` |

**Total**: ~10 telas. **Paralelização**: 4–6 agentes, mas **cada tela precisa do módulo
JS correspondente atualizado no mesmo PR**. Usar `bootstrap-js-refactorer` em paralelo
com `bootstrap-migrator`.

### 6.2 Mudanças JS por módulo

#### `LessonPlayer.js`

**Mudanças**:
- `reflectCompletion()`: `badge.style.display = 'inline-flex'` → `badge.classList.remove('d-none')`
- Preservar `reportProgress(lessonId, watched, duration)` — é seam Dusk (`VideoThresholdCompletionTest`)

#### `QuizTimer.js`

**Mudanças**:
- `tick()`: `style.color = 'var(--color-accent-2)'` → `badge.classList.add('text-bg-danger')`
- Opcional: barra `.progress` do tempo restante

#### `ForumPolling.js`

**Mudanças críticas**:
- `appendReply()`: **remover** `el.style.cssText = '...'`
- Clonar `<template id="forum-reply-template">` ou aplicar classes `.card.mb-2` + `.card-body`
- Preservar `dusk="reply-{id}"` e `dusk="reply-content-{id}"`

#### `CsvImporter.js`

**Mudanças**:
- `setProgress()`: `bar.style.width = pct + '%'` → `bar.style.width = pct + '%'` (pode ficar inline)
- `showResults()`: criar `.alert.alert-success`/`.alert-danger` via JS
- **OU** usar `classList.toggle('d-none')` e deixar o HTML ter as classes

### 6.3 Entry Criteria

- [ ] Fase 4 completa
- [ ] Módulos JS correspondentes identificados
- [ ] `<x-ui.progress>` disponível (para CsvImporter, QuizTimer, student/courses)

### 6.4 Exit Criteria

- [ ] Todas as 10 telas migradas
- [ ] Módulos JS atualizados (zero `style.cssText` para layout)
- [ ] `LessonPlayer.reportProgress` preservado (Dusk passa)
- [ ] `ForumPolling.appendReply` clona template ou usa classes Bootstrap
- [ ] Suíte Dusk destas telas verde

### 6.5 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="MultiOrgStudentClassroomTest|VideoThresholdCompletionTest|LessonMultimediaTest|StudentQuizAttemptTest|ForumDuskTest|MultiTenantStudentImportTest"
```

### 6.6 Rollback Strategy

```bash
git checkout main -- resources/views/classroom/ resources/views/student/ resources/views/forum/ resources/views/users/import.blade.php resources/js/modules/LessonPlayer.js resources/js/modules/QuizTimer.js resources/js/modules/ForumPolling.js resources/js/modules/CsvImporter.js
vendor/bin/sail npm run build
```

---

## 7. Fase 6 — Telas Públicas & Guest (Wave F)

**Objetivo**: migrar telas públicas (landing, certificado público) e telas de
autenticação (`auth/`, `convite/`).

**Dependências**: Fase 5 completa.

### 7.1 Telas públicas/guest em ordem

| Tela | Risco | Dusk Filter | Observação |
|:---|:---:|:---|:---|
| `auth/login.blade.php` | Baixo | `LoginTest` | Form simples |
| `auth/forgot-password.blade.php` | Baixo | `LoginTest` | Form 1 campo |
| `auth/reset-password.blade.php` | Baixo | `LoginTest` | Form 3 campos |
| `landing/show.blade.php` | Baixo | `BladeComponentsTest`<br>`LayoutRenderingTest` | HTML standalone |
| `public/certificates/show.blade.php` | Baixo | `CertificateVerificationTest` | HTML standalone |
| `convite/show.blade.php` | Médio | `MultiOrgEnrollmentTest` | SmartInvitationForm |

**Total**: ~6 telas. **Paralelização**: 4–6 agentes, 3 telas auth em paralelo.

### 7.2 Padrão de tela guest

```blade
<body class="min-vh-100">
  <div class="d-flex min-vh-100 w-100">
    <div class="d-none d-lg-flex flex-column bg-dark text-white p-5" style="width:42%;flex:none">
      <!-- painel institucional -->
    </div>
    <div class="flex-fill d-flex flex-column justify-content-center align-items-center p-4 position-relative">
      <div class="position-absolute top-0 end-0 m-3"><x-help-button :key="Route::currentRouteName()" /></div>
      <div class="w-100" style="max-width:380px">
        <x-layout.alerts />
        {{ $slot ?? '' }}
        @yield('content')
      </div>
    </div>
  </div>
</body>
```

### 7.3 Entry Criteria

- [ ] Fase 5 completa
- [ ] `<x-layout.auth-heading>` criado (ou usar pattern inline)

### 7.4 Exit Criteria

- [ ] Todas as 6 telas migradas
- [ ] Landing e public/certificates usam `<x-layout.public>`
- [ ] Auth usa `layouts.guest`
- [ ] Suíte Dusk destas telas verde

### 7.5 Comando de Verificação

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact --filter="LoginTest|MultiOrgEnrollmentTest|CertificateVerificationTest"
```

### 7.6 Rollback Strategy

```bash
git checkout main -- resources/views/auth/ resources/views/landing/ resources/views/public/ resources/views/convite/
vendor/bin/sail npm run build
```

---

## 8. Fase 7 — Novos Componentes & Polish Final

**Objetivo**: criar os 18 componentes novos identificados em 02, verificar toda
suíte Dusk, limpar dívida técnica e finalizar build.

**Dependências**: Fase 6 completa.

### 8.1 Componentes novos a criar (ordem de prioridade)

| # | Componente | Uso em | Prioridade |
|:--|:---|:---|:---:|
| 1 | `x-layout.page-header` | 19 telas | Alta |
| 2 | `x-layout.section-header` | 6 telas | Alta |
| 3 | `x-layout.public` | 2 telas | Alta |
| 4 | `x-ui.data-table` | 12 telas | Alta |
| 5 | `x-ui.empty-state` | 12 telas | Alta |
| 6 | `x-ui.pagination` | 9 telas | Alta |
| 7 | `x-ui.filter-bar` | 2–3 telas | Média |
| 8 | `x-ui.form-actions` | 14 telas | Média |
| 9 | `x-ui.field-stack` | 12 telas | Média |
| 10 | `x-ui.checkbox` | 5 telas | Média |
| 11 | `x-ui.file-input` | 4 telas | Média |
| 12 | `x-ui.textarea` | 4 telas | Média |
| 13 | `x-ui.progress` | 4 telas | Média |
| 14 | `x-ui.sortable-list`<br>`x-ui.sortable-item` | 3 telas | Média |
| 15 | `x-ui.confirm-modal` | 2 telas | Média |
| 16 | `x-ui.delete-button` | 11 telas | Média |
| 17 | `x-ui.kv-table` | 1 tela | Baixa |
| 18 | `x-ui.quiz-choice` | 1 tela | Baixa |
| 19 | `x-ui.option-row` | 1 tela (QuizBuilder) | Baixa |
| 20 | `x-ui.post-card` | 2 telas (fórum) | Média |
| 21 | `x-ui.breadcrumb` | 5 telas | Baixa |
| 22 | `x-ui.radio-group` | 1 tela | Baixa |
| 23 | `x-ui.toast` | NotificationService | Alta |

**Total**: ~20 componentes. **Paralelização**: 3–5 `bootstrap-component-author`
simultâneos.

### 8.2 Tarefas de polish

| Tarefa | Descrição | Verificação |
|:---|:---|:---|
| P1 | **Remover shim `--color-*`** quando `grep -rn "var(--color-" resources/views \| wc -l` chegar a 0 | Contagem de ocorrências |
| P2 | **Remover `resources/css/app.css`** (se ainda existir) | Arquivo não existe |
| P3 | **Remover classes fantasma** de código JS (`btn-ghost`, etc.) | `grep` retorna vazio |
| P4 | **Auditar acessibilidade** — testes manuais de teclado, leitor de tela | Navigation por teclado funciona |
| P5 | **Cross-browser test** — Chrome, Firefox, Safari, Edge | Visual consistente |
| P6 | **Performance check** — bundle size, Lighthouse | Lighthouse score > 90 |

### 8.3 Entry Criteria

- [ ] Fase 6 completa
- [ ] Lista de componentes novos priorizada

### 8.4 Exit Criteria

- [ ] Todos os ~20 componentes criados
- [ ] Suíte Dusk **integralmente verde** (`vendor/bin/sail artisan dusk`)
- [ ] Zero `style=` inline (exceto `certificates/pdf.blade.php` e valores dinâmicos)
- [ ] Zero classes fantasma no código
- [ ] Zero uso de `var(--color-*)` nas views
- [ ] Shim `--color-*` removido ou documentado como dívida
- [ ] Build de produção otimizado

### 8.5 Comando de Verificação

```bash
# Verificação final completa
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --compact

# Auditoria de débitos técnicos
grep -rn 'style="' resources/views --include='*.blade.php' | grep -vc 'certificates/pdf'
grep -rnE 'btn-ghost|btn-block|btn-icon|dialog-backdrop|tag-(accent|outline|neutral)|elev-(sm|md|lg)|\bfield\b|rounded' resources/views --include='*.blade.php'
grep -rn 'var(--color-' resources/views --include='*.blade.php' | wc -l

# Contagem de dusk= (deve ser >= 316)
grep -ro 'dusk="' resources/views | wc -l
```

### 8.6 Rollback Strategy

Esta fase é a última; rollback significa voltar ao início da Fase 0 ou ao
último ponto conhecido verde. Documentar rollout em CHANGELOG.

---

## 9. Skills & Agents — Bootstrap (criação pré-Fase 1)

Conforme documento 06-skills-and-agents.md, a tríade de skills e os 5 subagentes
DEVEM ser criados antes do início da Fase 1.

### 9.1 Skills (arquivos `SKILL.md`)

| Skill | Caminho | Propósito |
|:---|:---|:---|
| `bootstrap-architecture` | `.agents/skills/bootstrap-architecture/SKILL.md` | Arquitetura de 5 camadas, decisão record |
| `bootstrap-conventions` | `.agents/skills/bootstrap-conventions/SKILL.md` | Padrões de código, tabela DE↔PARA |
| `bootstrap-maintenance` | `.agents/skills/bootstrap-maintenance/SKILL.md` | Debug, verificação, modos de falha |

**Criar também espelho em `.claude/skills/*/SKILL.md`** para consumo do
Claude Code.

### 9.2 Subagentes (arquivos `.md`)

| Agente | Caminho | Propósito | Modelo |
|:---|:---|:---|:---|
| `bootstrap-migrator` | `.agents/agents/bootstrap-migrator.md` | Migrar 1 tela ou componente | `sonnet` |
| `bootstrap-component-author` | `.agents/agents/bootstrap-component-author.md` | Criar novo `<x-ui.*>` | `opus` |
| `bootstrap-js-refactorer` | `.agents/agents/bootstrap-js-refactorer.md` | Refatorar módulo JS | `opus` |
| `bootstrap-design-reviewer` | `.agents/agents/bootstrap-design-reviewer.md` | Review visual de PR | `sonnet` |
| `bootstrap-visual-verifier` | `.agents/agents/bootstrap-visual-verifier.md` | Verificação cross-browser | `sonnet` |

**Criar também espelho em `.claude/agents/*.md`**.

### 9.3 Ordem de criação

1. Criar skills (3 PRs ou 1 PR com 3 arquivos)
2. Criar subagentes (5 PRs ou 1 PR com 5 arquivos)
3. Testar ativação: `/skill bootstrap-conventions` deve carregar a skill
4. **Somente então** iniciar Fase 1

---

## 10. Registro de Riscos

### 10.1 Riscos identificados (dos documentos 01–06)

| # | Risco | Impacto | Mitigação |
|:---|:---|:---:|:---|
| **R1** | **Inline styles não removidos** — especificidade 1000 vence classes | Alto | Verificação em cada fase: `grep -rn 'style="'` |
| **R2** | **Dusk selector conflitos** — rename/move quebra teste | Alto | Diff antes/depois; documentar mudanças |
| **R3** | **JS module race conditions** — `app.js` compartilhado | Médio | Registry `modules/index.js` estabiliza |
| **R4** | **Stale build quebra Dusk silenciosamente** | Alto | Toda tarefa termina com `npm run build` |
| **R5** | **dompdf PDF rendering** — Bootstrap não funciona | Alto | `certificates/pdf.blade.php` fora de escopo |
| **R6** | **Acessibilidade regressões** — foco, ARIA | Médio | Testes manuais de leitor de tela |
| **R7** | **Cross-browser breaks** — CSS Grid, flexbox | Médio | Testar Chrome/Firefox/Safari/Edge |
| **R8** | **`ForumPolling.appendReply` gera markup divergente** | Médio | Clonar `<template>` ou usar classes idênticas |
| **R9** | **`QuizBuilder`/`QuizTimer` manipulam `style.display`** | Médio | Trocar por `classList.toggle('d-none')` |
| **R10** | **Paginação sem estilo em 9 telas** | Baixo | `Paginator::useBootstrapFive()` na Fase 0 |
| **R11** | **Menu mobile morto (Alpine inerte)** | Alto | `bootstrap.Offcanvas` na Fase 1 |
| **R12** | **BUG-004 e BUG-003 resolvem "de graça"** | Baixo | Confirmar com Dusk após Fase 1 |
| **R13** | **Archivos Archivo 0 byte** — tipografia nunca ativou | Alto | Baixar arquivos reais na Fase 0 |
| **R14** | **`CsvImporter` usa `dusk=` como seletor funcional** | Médio | Preservar verbatim |
| **R15** | **Tailwind classes colidem com Bootstrap** | Baixo | Remover Tailwind na Fase 0 |

### 10.2 Estratégias de mitigação gerais

1. **Baseline verde** — rodar Dusk completo antes de iniciar e gravar resultado
2. **Build obrigatório** — toda tarefa termina com `npm run build`
3. **Diff de dusk=** — antes/depois em cada arquivo migrado
4. **Skills ativas** — `bootstrap-conventions` vicia o agente com padrões corretos
5. **Revisão visual** — `bootstrap-design-reviewer` em cada PR de tela
6. **Testes manuais** — ao menos uma rodada completa por navegador
7. **Branch de feature** — nunca migrar em `main`
8. **Paralelismo controlado** — só arquivos disjuntos em paralelo

### 10.3 Plano de comunicação de bloqueio

Se algum agente encontrar bloqueio fora de escopo:

1. Registrar em `BLOCKED:` no receipt
2. Abrir issue em `spec/front_migration/` (se não existir)
3. Notificar orquestrador
4. Continuar outras unidades não bloqueadas

---

## Cronograma Sugerido

| Semana | Fases | Entregável |
|:---|:---|:---|
| 1 | Fase 0 + Skills/Agents + início Fase 1 | Pipeline pronto, layouts migrados |
| 2 | Fase 1 completa + início Fase 2 | Componentes globais OK |
| 3 | Fase 2 completa + Fase 3 | Parciais + index screens OK |
| 4 | Fase 4 + início Fase 5 | CRUD OK |
| 5 | Fase 5 completa + Fase 6 | JS-heavy + públicas OK |
| 6 | Fase 7 + polish final | Migração completa, Dusk 100% verde |

**Total**: 6 sprints de 1 semana (ou 3 sprints de 2 semanas com paralelismo maior).

---

## Anexo: Glossário

- **`dusk=`** — seletor exclusivo de teste Dusk, imutável por contrato
- **`data-bs-*`** — atributo do Bootstrap 5.3 para ativar plugins via data-api
- **`style=`** — estilo inline que deve ser **removido** (exceto valores dinâmicos)
- **Classe fantasma** — classe que não existe em CSS nenhum (`.btn-ghost`, `.dialog`, etc.)
- **Modernist** — design system do projeto (radius 0, Archivo, accent `#ec3013`)
- **Wave A–F** — agrupamento de telas por complexidade (documento 03)
- **Shim** — camada de compatibilidade que reemite `--color-*` para código legado
- **Baseline** — estado do projeto antes da migração, gravado para comparação
- **Receipt** — formato de saída do `bootstrap-migrator` (arquivos tocados, diff, build)

---

**Fim do plano de migração.** Execute as fases em ordem, respeite os critérios
de entrada/saída, e mantenha a suíte Dusk verde a cada fase.
