# Especificação de Design: Dark Mode na Plataforma EAD

**Data:** 2026-09-07  
**Status:** Aprovado  
**Autor:** Antigravity  

---

## 1. Visão Geral

Este documento especifica a introdução do suporte completo ao **Dark Mode** na **Plataforma EAD**, preservando a identidade visual baseada no design system e na arquitetura de 5 camadas sobre o **Bootstrap 5.3**.

O sistema passará a operar com suporte nativo a dois modos de cor:
1. **Light Mode (Padrão atual):** Superfícies claras (`#faf8ff` / `#ffffff`), texto escuro (`#1b2437`) e primário azul.
2. **Dark Mode (Slate / Deep Navy):** Fundo escuro azul-ardósia (`#0b0f19`), superfícies elevadas (`#111827`), bordas sutis (`#1e293b`), texto de alto contraste (`#f1f5f9`) e acento primário luminoso (`#3b82f6`).

A alternância é feita por um botão na parte superior das telas, persistida em `localStorage`, com suporte a anti-FOUC (sem flashes visuais brancos durante carregamentos ou trocas de página).

---

## 2. Paleta de Cores e Tokens Semânticos

### 2.1 Mapeamento De/Para: Light vs. Dark Mode

| Token Semântico | Light Mode | Dark Mode | Função e Contraste |
|---|---|---|---|
| `--surface-body` | `#f6f8fc` | `#0b0f19` | Fundo principal da aplicação |
| `--surface` | `#ffffff` | `#111827` | Cards, modais e containers elevados |
| `--surface-alt` | `#eef1f7` | `#161f30` | Superfícies secundárias, tabs, faixas alternadas |
| `--surface-sunken` | `#fbfcfe` | `#0d131f` | Headers de tabelas, áreas embutidas, inputs desabilitados |
| `--surface-inverse` | `#1b2437` | `#f8fafc` | Superfície invertida |
| `--text-primary` | `#1b2437` | `#f1f5f9` | Texto principal e headings (WCAG AAA > 12:1) |
| `--text-secondary` | `#5b6880` | `#94a3b8` | Textos de apoio, subtítulos e labels (WCAG AA > 5:1) |
| `--text-disabled` | `#8e99af` | `#64748b` | Estados desabilitados |
| `--border-color` | `#dce3ee` | `#1e293b` | Divisores e bordas gerais |
| `--border-strong` | `#b9c2d4` | `#334155` | Bordas ativas e contornos de foco |
| `--border-subtle` | `#e2e7f0` | `#162032` | Divisores discretos |
| `--primary` | `#4c6fe7` / `#2563eb` | `#3b82f6` | Ação primária (ajustado para contraste em fundo escuro) |
| `--primary-hover` | `#3d5bd0` / `#1d4ed8` | `#60a5fa` | Estado hover/focus da ação primária |
| `--primary-pressed` | `#3550bc` | `#93c5fd` | Estado active/pressed da ação primária |
| `--primary-container` | `#e7ecfd` | `#1e3a8a` | Chips e badges tonais |
| `--on-primary-container` | `#3550bc` | `#dbeafe` | Texto/ícone sobre o container primário |
| `--success` | `#2e9e6b` / `#10b981` | `#10b981` | Conclusão, progresso e status positivo |
| `--success-container` | `#e4f5ec` | `#064e3b` | Container de sucesso |
| `--on-success-container`| `#1e6a4a` | `#a7f3d0` | Texto sobre container de sucesso |
| `--nav-bg` | `#ffffff` | `#0f172a` | Fundo da topbar e sidebar |
| `--nav-fg` | `#5b6880` | `#94a3b8` | Links de navegação inativos |
| `--nav-fg-active` | `#3550bc` | `#60a5fa` | Link de navegação ativo |
| `--nav-active-bg` | `#e7ecfd` | `#1e293b` | Pílula de seleção ativa |

---

## 3. Arquitetura de Implementação

### 3.1 Camada 1: Tokens e Bootstrap 5.3 Bridge
- O Bootstrap 5.3 utiliza o atributo `data-bs-theme="dark"` no elemento `<html>`.
- Em `resources/css/tokens/colors.css` e em `_ds/plataforma-ead-design-system-618bb28f-056b-421b-b1d4-d0fef3cd30e2/tokens/colors.css`, adicionamos a redefinição de variáveis sob `[data-bs-theme="dark"]`.
- Em `resources/scss/_bridge.scss` (ou partial dedicado `resources/scss/_dark.scss`), configuramos as variáveis CSS do Bootstrap ativadas sob `[data-bs-theme="dark"]`:
  - `--bs-body-bg: var(--surface-body);`
  - `--bs-body-color: var(--text-primary);`
  - `--bs-card-bg: var(--surface);`
  - `--bs-card-border-color: var(--border-color);`
  - `--bs-border-color: var(--border-color);`
  - `--bs-tertiary-bg: var(--surface-sunken);`
  - `--bs-secondary-bg: var(--surface-alt);`
  - `--bs-secondary-color: var(--text-secondary);`
  - Ajustes de input (`.form-control`, `.form-select`) para `--bs-body-bg: var(--surface)` e cor de texto correta no escuro.

### 3.2 Camada 4: Componente Blade `<x-ui.theme-toggle />`
- Arquivo: `resources/views/components/ui/theme-toggle.blade.php`
- Emite um `<button type="button" class="btn btn-ghost ds-state-layer d-inline-flex align-items-center justify-content-center p-2 rounded-circle" data-theme-toggle dusk="theme-toggle" aria-label="Alternar tema" title="Alternar tema">`.
- Renderiza internamente ícones Lucide `<x-ui.icon name="moon" class="theme-icon-dark" />` e `<x-ui.icon name="sun" class="theme-icon-light" />`.
- Através de CSS/JS, exibe a lua quando o modo claro está ativo (indicando a ação de escurecer) e o sol quando o modo escuro está ativo (indicando a ação de clarear).

### 3.3 Camada 5: Pontos de Inserção nos Cabeçalhos
1. **Topbar Autenticada (`resources/views/components/layout/topbar.blade.php`):**
   - Inserido no grupo de utilidades à direita, ao lado de `<x-help-button>` e `<x-notifications-bell>`.
2. **Landing Page (`resources/views/landing/show.blade.php`):**
   - Inserido no header superior direito da landing, ao lado do botão de ajuda e do botão de login.
3. **Layout de Visitante / Login (`resources/views/layouts/guest.blade.php`):**
   - Inserido no contêiner absoluto superior direito (`position-absolute top-0 end-0 p-4`), ao lado de `<x-help-button>`.

### 3.4 Prevenção de FOUC (Flash of Unstyled Content)
Nos arquivos de layout (`layouts/app.blade.php`, `layouts/guest.blade.php`, `components/layout/public.blade.php`), inserimos imediatamente antes de qualquer carregamento de estilos no `<head>`:
```html
<script>
    (function () {
        const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
</script>
```

### 3.5 Módulo JavaScript (`resources/js/modules/ThemeManager.js`)
- Criado em `resources/js/modules/ThemeManager.js` e registrado em `resources/js/modules/index.js`.
- Métodos:
  - `init()`: sincroniza os botões existentes na tela com o tema atual de `document.documentElement.getAttribute('data-bs-theme')` e anexa os event listeners de clique.
  - `toggle()`: inverte o tema atual ('light' <-> 'dark'), atualiza o atributo `data-bs-theme` em `<html>`, salva no `localStorage` e atualiza todos os botões na página.
  - Dispara evento `theme-changed` em `window`.

---

## 4. Atualizações na Documentação do Design System (`DESIGN.md`)

- Atualizar o cabeçalho YAML com as definições de cores para os modos claro e escuro.
- Adicionar no texto do `DESIGN.md` a seção formal do **Modo Escuro (Dark Mode)**, detalhando os fundamentos de contraste, hierarquia de superfícies e a integração com o Bootstrap 5.3.

---

## 5. Plano de Verificação

1. **Compilação de Assets (Vite):** `vendor/bin/sail npm run build` sem erros de SCSS ou JS.
2. **Estilo de Código PHP:** `vendor/bin/sail bin pint --dirty --format agent`.
3. **Testes Unitários / Feature:** `vendor/bin/sail artisan test --compact` (garantindo que nenhuma view quebrou).
4. **Nota sobre Dusk:** Conforme instrução do usuário, **os testes Dusk não serão executados**.
