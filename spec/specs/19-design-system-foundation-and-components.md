# **19. Fundação de Tokens CSS, Build Pipeline e Micro-Componentes Blade (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos**

* **Objetivo:** Estabelecer a fundação do novo Design System ("Material Bootstrap") substituindo integralmente o tema legado (Modernist: cinza, vermelho `#ec3013`, raio 0). Centralizar tokens CSS, pipeline SCSS/Vite, tipografia Nunito Sans e 30 micro-componentes Blade padronizados (`<x-ui.*>` e `<x-layout.*>`).
* **Regra Fundamental de Cores:** **Vermelho, laranja e amarelo são terminantemente proibidos na interface.** Destrutivo e erros usam tom `--critical` (`#3b4a78`, azul-slate) + ícone explícito + texto calmo.
* **Roles Cobertas:** Global (`role:admin`, `role:gestor`, `role:aluno`, visitantes públicos).
* **Referência de Design:** `DESIGN.md`, `_ds/Novo Design System - Telas.dc.html`, `_ds/Tabelas.dc.html`.

---

## **2. Tokens CSS & Sistema de Estilos**

### 2.1 Cores e Superfícies (`tokens/colors.css`)
- **Primário:** `--primary: #4c6fe7` (`--blue-600`), hover `#3d5bd0`, pressed `#3550bc`. Container: `--primary-container: #e7ecfd`, texto: `--on-primary-container: #3550bc`.
- **Secundário (Sucesso/Menta):** `--secondary: #2e9e6b` (`--mint-600`), hover `#25835a`. Container: `--secondary-container: #e4f5ec`, texto: `--on-secondary-container: #1e6a4a`.
- **Terciário (Info/Sky):** `--tertiary: #2c7bd1` (`--sky-600`). Container: `--info-container: #e2f0fd`, texto: `--on-info-container: #1f5289`.
- **Crítico / Destrutivo:** `--critical: #3b4a78` (azul-slate escuro), hover `#33406a`. Container: `--critical-container: #e8ecf6`, texto: `--on-critical-container: #2c3760`.
- **Atenção Neutra:** `--attention: #5b6880`. Container: `--attention-container: #eef1f7`, texto: `--on-attention-container: #2b3752`.
- **Superfícies:** `--surface-body: #f6f8fc`, `--surface: #ffffff`, `--surface-alt: #eef1f7`, `--surface-sunken: #fbfcfe`.
- **Texto:** `--text-primary: #1b2437`, `--text-secondary: #5b6880`, `--text-disabled: #8e99af`.
- **Bordas e Divisores:** `--divider: #e2e7f0`, `--border-color: #dce3ee`.
- **Pastel Wash (Gradiente de Mídia):** `linear-gradient(135deg, var(--blue-100), var(--mint-100))`.

### 2.2 Tipografia (`tokens/typography.css`, `tokens/fonts.css`)
- **Família:** Nunito Sans (Google Fonts), pesos 400, 600, 700, 800.
- **Base:** 16px, entrelinha 1.6. Nada abaixo de 13px.
- **Escala:** display (44px/700), h1 (36px/700), h2 (30px/700), h3 (24px/700), h4 (20px/700), h5 (18px/700), h6 (16px/700), lead (19px/400), body (16px/400), body-sm (15px/400), label (14px/700), caption (13px/400), overline (13px/700, único uppercase com tracking .08em), métrica (40px/800).
- **Regra de Texto:** Sentence-case em tudo (inclusive botões, badges e chips). Números em pt-BR com vírgula (`4,2%`, `4,5h`), datas `dd/mm/aaaa`.

### 2.3 Espaçamento, Raios e Elevação (`tokens/spacing.css`, `tokens/elevation.css`)
- **Grade 4px:** 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80px.
- **Paddings:** Card 28px, Célula de tabela 18×20px, `main` 40px (20px mobile).
- **Raios Suaves:** 6px (xs), 10px (sm), 14px (md), 20px (lg), 28px (xl), 36px (2xl), 999px (pill). Botões/chips: pill; cards: 20px; modais: 28px; inputs: 14px; hero: 36px.
- **Elevação Tonal (Sombra Azulada `rgba(27,36,55,...)`):**
  - `--elev-1`: 0 1px 3px rgba(27,36,55,0.06), 0 1px 2px rgba(27,36,55,0.04) (cards e tabelas).
  - `--elev-2`: 0 4px 12px rgba(27,36,55,0.08) (destaques).
  - `--elev-3`: 0 8px 24px rgba(27,36,55,0.12) (FAB e hover de cards).
  - `--elev-4`: 0 12px 32px rgba(27,36,55,0.14) (dropdowns e drawer mobile).
  - `--elev-5`: 0 20px 48px rgba(27,36,55,0.18) (modais).
  - `--elev-primary`: 0 6px 18px rgba(76,111,231,0.28) (CTA primário e FAB).

---

## 3. Inventário dos 30 Micro-Componentes Blade (`<x-ui.*>` e `<x-layout.*>`)

| Componente | Tag Blade | Props Principais | Comportamento e Padrões |
|---|---|---|---|
| **Button** | `<x-ui.button>` | `variant` (primary, tonal, secondary, ghost, success, danger), `size` (sm 40px, base 48px, lg 60px), `href`, `icon`, `type`, `block` | Pílula, anel de foco 3px + 2px offset, elevação sutil no hover. |
| **Badge / Chip** | `<x-ui.badge>` / `<x-ui.chip>` | `variant` (primary, success, info, neutral, critical, outline), `removable`, `active`, `count` | Sentence-case, pílula, pares container/on-container. |
| **Icon** | `<x-ui.icon>` | `name` (Lucide subconjunto), `size` (16, 18, 20, 22, 24, 28, 32), `class` | SVG inline viewBox 24x24, stroke 2px, cantos redondos. Fallback para `info`. |
| **Card** | `<x-ui.card>` | `variant` (elevated, outlined, interactive, sunken), `padding`, `radius`, `interactive` | Raio 20px, hover elev-3 se interativo. |
| **StatCard** | `<x-ui.stat-card>` | `label`, `value`, `delta`, `deltaType` (success, neutral), `caption`, `icon`, `iconVariant` | Ícone 52px em container pastel, métrica 40px/800, delta em chip. |
| **Table / DataTable** | `<x-ui.table>` / `<x-ui.data-table>` | `striped`, `hover`, `compact`, `toolbarSlot`, `emptySlot` | Wrapper com raio 20px e elev-1, hover 6% azul, sem bordas verticais. |
| **Pagination** | `<x-ui.pagination>` | `paginator`, `label` | Pílulas circulares de 40px, página ativa azul primário, independente de Tailwind. |
| **EmptyState** | `<x-ui.empty-state>` | `icon`, `title`, `description`, `actionSlot` | Ícone 32px em círculo pastel, borda tracejada 1.5px, próximo passo obrigatório. |
| **Progress** | `<x-ui.progress>` | `value` (0-100), `size` (sm 8px, base 10px), `variant` (primary, success/mint) | Pílula, transição suave 320ms, suporte a `role="progressbar"`. |
| **Alert** | `<x-ui.alert>` | `variant` (primary, success, info, attention, danger/critical), `dismissible`, `icon` | Fundo tonal container, texto on-container, ícone Lucide integrado. |
| **Modal** | `<x-ui.modal>` | `id`, `size` (sm, md, lg, xl), `title`, `footerSlot`, `scrollable` | Raio 28px, elev-5, scrim 42%, fecha com `Esc`, foco preso. |
| **ConfirmModal** | `<x-ui.confirm-modal>` | `id`, `title`, `message`, `confirmText`, `cancelText`, `action`, `method`, `variant` (critical/danger) | Diálogo de confirmação com botão destrutivo em slate, sem remoção acidental. |
| **DeleteButton** | `<x-ui.delete-button>` | `action`, `title`, `message`, `label`, `itemTitle` | Botão com ícone de lixeira que aciona ConfirmModal associado. |
| **Input** | `<x-ui.input>` | `name`, `type`, `label`, `value`, `placeholder`, `hint`, `required`, `disabled`, `error` | Outlined, floating label, altura 52px, raio 14px, anel de foco 4px. |
| **Textarea** | `<x-ui.textarea>` | `name`, `label`, `rows`, `value`, `hint`, `required`, `disabled`, `error` | Outlined, floating label, resize vertical, raio 14px. |
| **Select** | `<x-ui.select>` | `name`, `label`, `options`, `selected`, `hint`, `required`, `disabled`, `error` | Outlined, floating label, altura 52px, chevron customizado. |
| **Checkbox** | `<x-ui.checkbox>` | `name`, `label`, `checked`, `value`, `disabled`, `hint` | Caixa 22px, alvo de toque de 48px, raio 6px. |
| **Switch** | `<x-ui.switch>` | `name`, `label`, `checked`, `value`, `disabled`, `hint` | Trilha menta quando ativo, alternância animada de 200ms. |
| **Tabs** | `<x-ui.tabs>` | `tabs` (array), `active`, `name` | Pílulas segmentadas em trilha cinza-azulada (`--surface-alt`), `role="tablist"`. |
| **Avatar** | `<x-ui.avatar>` | `name`, `size` (sm 36px, md 44px, lg 64px), `variant` | Círculo com iniciais (2 letras) sobre fundo pastel. |
| **Fab** | `<x-ui.fab>` | `icon`, `label`, `href`, `modalTarget` | Pílula 56px, fixed no canto inferior direito, glow `--elev-primary`. |
| **FieldStack** | `<x-ui.field-stack>` | `gap` (sm, md, lg) | Wrapper flex de formulários com espaçamento consistente. |
| **FormActions** | `<x-ui.form-actions>` | `align` (left, right, between) | Faixa de botões com divisor superior `--divider` e gap 12px. |
| **FilterBar** | `<x-ui.filter-bar>` | `action`, `searchName`, `searchPlaceholder`, `filtersSlot` | Barra de busca e selects de filtro com botão limpar. |
| **Topbar** | `<x-layout.topbar>` | `user`, `org`, `notificationsCount` | Barra de 76px, busca pílula, badge org, ajuda, sino e menu conta. |
| **Sidebar** | `<x-layout.sidebar>` | `navigation`, `activeRoute` | Drawer claro 280px, itens 52px, pílula ativa azul, badges menta. |
| **PageHeader** | `<x-layout.page-header>` | `title`, `kicker`, `subtitle`, `breadcrumbs`, `actionsSlot` | Overline kicker azul, h1 36px, subtítulo lead 19px. |
| **Footer** | `<x-layout.footer>` | `orgName`, `year` | Rodapé com copyright e links institucionais. |
| **GuestPanel** | `<x-layout.guest-panel>` | `tenantName`, `headline`, `lead` | Split screen institucional em `--blue-100` (46% largura). |
| **HelpButton** | `<x-help-button>` | `key` | Botão circular de ajuda contextual com modal integrado e fallback. |

---

## 4. Diretrizes de Implementação & Contrato de Qualidade

1. **Zero Estilos Inline:** Nenhuma view Blade conterá `style="..."`, com a única exceção da largura percentual de barras de progresso dinâmicas (`style="width: XX%"`).
2. **Bootstrap 5.3 Nativo:** Toda interatividade de diálogos, toasts e dropdowns utiliza a API padrão do Bootstrap 5.3 estilizada pelos tokens CSS.
3. **Acessibilidade:**
   - Foco visível (`--focus-ring`) em 100% dos controles.
   - Contraste WCAG AA em todas as combinações de cores.
   - Atributos ARIA obrigatórios (`role="alert"`, `role="progressbar"`, `aria-expanded`, `aria-selected`, `aria-current="page"`).

---

## 5. Checklist de Implementação & Testes

- [ ] Arquivos de tokens CSS criados em `resources/css/tokens/` (`colors.css`, `fonts.css`, `spacing.css`, `elevation.css`, `motion.css`).
- [ ] Importação de Nunito Sans via Google Fonts no layout base.
- [ ] Compilação Vite / SCSS configurada e testada (`npm run build`).
- [ ] 30 Componentes Blade criados/refatorados em `resources/views/components/ui/` e `components/layout/`.
- [ ] Testes Unitários/Feature de renderização de componentes (`BladeComponentsTest.php`).
- [ ] Teste Dusk de verificação de tokens, foco e acessibilidade (`ThemeAccessibilityTest.php`).
