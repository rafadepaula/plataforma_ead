---
name: LMS Core System
colors:
  surface: '#faf8ff'
  surface-dim: '#d9d9e5'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f3fe'
  surface-container: '#ededf9'
  surface-container-high: '#e7e7f3'
  surface-container-highest: '#e1e2ed'
  on-surface: '#191b23'
  on-surface-variant: '#434655'
  inverse-surface: '#2e3039'
  inverse-on-surface: '#f0f0fb'
  outline: '#737686'
  outline-variant: '#c3c6d7'
  surface-tint: '#0053db'
  primary: '#004ac6'
  on-primary: '#ffffff'
  primary-container: '#2563eb'
  on-primary-container: '#eeefff'
  inverse-primary: '#b4c5ff'
  secondary: '#515f74'
  on-secondary: '#ffffff'
  secondary-container: '#d5e3fc'
  on-secondary-container: '#57657a'
  tertiary: '#943700'
  on-tertiary: '#ffffff'
  tertiary-container: '#bc4800'
  on-tertiary-container: '#ffede6'
  error: '#EF4444'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dbe1ff'
  primary-fixed-dim: '#b4c5ff'
  on-primary-fixed: '#00174b'
  on-primary-fixed-variant: '#003ea8'
  secondary-fixed: '#d5e3fc'
  secondary-fixed-dim: '#b9c7df'
  on-secondary-fixed: '#0d1c2e'
  on-secondary-fixed-variant: '#3a485b'
  tertiary-fixed: '#ffdbcd'
  tertiary-fixed-dim: '#ffb596'
  on-tertiary-fixed: '#360f00'
  on-tertiary-fixed-variant: '#7d2d00'
  background: '#faf8ff'
  on-background: '#191b23'
  surface-variant: '#e1e2ed'
  primary-hover: '#1D4ED8'
  primary-surface: '#EFF6FF'
  text-main: '#0F172A'
  border-subtle: '#CBD5E1'
  bg-interface: '#F1F5F9'
  success: '#10B981'
  warning: '#F59E0B'
typography:
  headline-2xl:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-xl:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  headline-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 26px
  body-base:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  caption-xs:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  space-1: 4px
  space-2: 8px
  space-4: 16px
  space-8: 32px
  container-max: 1280px
  margin-mobile: 24px
---

# Design System: Plataforma EAD

Este sistema de design foi concebido para ser o alicerce visual e funcional da
plataforma, priorizando a performance pedagógica, a acessibilidade e a
consistência entre diferentes papéis de usuário (Estudantes, Instrutores e
Administradores). Ele rompe com dependências de frameworks legados em favor de
um sistema de tokens agnóstico e otimizado.

Os tokens deste arquivo são a **referência obrigatória** para as specs de
execução em `_ds/specs/` (`01-tokens-e-build.md` em diante). Nenhum
valor de cor, tipografia, espaçamento ou raio deve ser escrito fora da camada
de tokens.

| Propriedade | Valor |
|---|---|
| Modo de cor | LIGHT |
| Variante de cor | FIDELITY (Material dynamic color) |
| Fonte (headline / body / label) | Inter |
| Raio padrão | ROUND_FOUR (escala 0.125–0.75rem) |
| Cor primária (override) | `#2563eb` |
| Cor secundária (override) | `#475569` |
| Dispositivo de referência | Desktop (container máx. 1280px) |

---

## 1. Fundamentos Visuais

### Cores (Tokens de Cor)
O sistema utiliza uma paleta equilibrada para garantir legibilidade e reduzir o
cansaço visual durante longas sessões de estudo.

**Primárias (Ação e Marca)**
- `color-primary-500`: #2563EB (Azul Principal - Confiança e Foco)
- `color-primary-600`: #1D4ED8 (Hover/Press)
- `color-primary-50`: #EFF6FF (Fundos de destaque leve)

**Neutras (Estrutura e Conteúdo)**
- `color-neutral-900`: #0F172A (Texto Principal / Headings)
- `color-neutral-600`: #475569 (Texto Secundário / Ícones)
- `color-neutral-300`: #CBD5E1 (Bordas e Divisores)
- `color-neutral-100`: #F1F5F9 (Backgrounds de Interface)
- `color-white`: #FFFFFF (Superfícies e Cards)

**Sinalização (Semântica)**
- `color-success`: #10B981 (Conclusão de Aula / Progresso)
- `color-error`: #EF4444 (Alertas / Erros de validação)
- `color-warning`: #F59E0B (Avisos / Prazos)

---

## 2. Tipografia

O sistema utiliza uma pilha tipográfica moderna que equilibra estética e
performance (LCP).

- **Font Family**: `Inter`, system-ui, sans-serif.
- **Escala de Tamanho**:
  - `text-xs`: 0.75rem (12px) - Legendas
  - `text-sm`: 0.875rem (14px) - UI Elements / Texto de Apoio
  - `text-base`: 1rem (16px) - Body Text (Padrão)
  - `text-lg`: 1.125rem (18px) - Subheadings
  - `text-xl`: 1.25rem (20px) - Títulos de Seção
  - `text-2xl`: 1.5rem (24px) - Títulos de Página
- **Pesos (Weights)**:
  - `font-normal`: 400
  - `font-medium`: 500
  - `font-semibold`: 600
  - `font-bold`: 700

### Tokens tipográficos do Stitch

| Token | Equivalente na escala | Fonte | Tamanho | Peso | Entrelinha |
|---|---|---|---|---|---|
| `caption-xs` | `text-xs` | Inter | 12px | 400 | 16px |
| `body-sm` | `text-sm` | Inter | 14px | 400 | 20px |
| `label-sm` | `text-sm` | Inter | 14px | 500 | 20px |
| `body-base` | `text-base` | Inter | 16px | 400 | 24px |
| `headline-lg` | `text-lg` | Inter | 18px | 600 | 26px |
| `headline-xl` | `text-xl` | Inter | 20px | 600 | 28px |
| `headline-2xl` | `text-2xl` | Inter | 24px | 700 | 32px |

---

## 3. Grid e Espaçamento

Baseado em um sistema de múltiplos de **8px** para garantir ritmo vertical e
alinhamento matemático.

- **Base Unit**: 8px
- **Escala**:
  - `space-1`: 4px
  - `space-2`: 8px
  - `space-4`: 16px (Padrão para margens internas)
  - `space-8`: 32px (Padrão para seções)
- **Container**: Largura máxima de 1280px para desktop, com padding lateral de
  24px.

---

## 4. Componentes Agnósticos

### Buttons
- **Primary**: Preenchimento total com `color-primary-500`, texto branco,
  bordas arredondadas de 6px.
- **Secondary**: Borda `color-neutral-300`, fundo transparente, texto
  `color-neutral-900`.
- **States**: Transições suaves de 200ms para hover/active.

### Cards
- **Estrutura**: Background `color-white`, borda `color-neutral-100`, shadow
  leve (`0 1px 3px rgba(0,0,0,0.1)`).
- **Uso**: Listagem de cursos, módulos e cards de progresso.

### Form Inputs
- **Foco**: Borda externa de 2px em `color-primary-500` com ring de 2px de
  offset.
- **Acessibilidade**: Labels sempre visíveis e associadas via ARIA.

### Progress Bars
- **Track**: `color-neutral-100`.
- **Fill**: `color-success` (para aulas concluídas) ou `color-primary-500`
  (para curso em andamento).

---

## 5. Comportamentos e UX

### Hierarquia de Papéis
- **Estudante**: Interface limpa, foco no "Course Player", notificações de
  progresso e gamificação leve (conquistas).
- **Instrutor/Admin**: Densidade de informação maior, uso de tabelas de dados
  robustas, filtros avançados e dashboards analíticos baseados em tokens.

### Performance e Acessibilidade
- **LCP (Largest Contentful Paint)**: Prioridade zero. Imagens devem ser
  lazy-loaded e SVGs devem ser inline para ícones críticos.
- **Contrast**: Todos os textos devem passar no teste WCAG AA (mínimo 4.5:1).
- **Interatividade**: Feedback visual imediato em cliques e transições suaves
  de estado (Stimulus.js logic).

---

## 6. Referência de tokens (CSS custom properties)

Nomes de variáveis derivados 1:1 do frontmatter acima (hífen no lugar de
underline). Definir em `:root` na camada de tokens e consumir **apenas** via
`var(--*)` — nenhum hex fora da camada de tokens.

### 6.1 Marca e ação (primária)

| Token | Hex | Uso |
|---|---|---|
| `--primary` | `#004ac6` | Papel Material "primary" (azul profundo da marca) |
| `--primary-hover` | `#1D4ED8` | Hover/press de ação primária |
| `--primary-container` | `#2563eb` | Container primário; é o azul de ação (`color-primary-500`) |
| `--on-primary-container` | `#eeefff` | Texto/ícone sobre o container primário |
| `--on-primary` | `#ffffff` | Texto/ícone sobre `--primary` |
| `--primary-surface` | `#EFF6FF` | Fundo de destaque leve (`color-primary-50`) |
| `--inverse-primary` | `#b4c5ff` | Primária sobre superfícies escuras |
| `--primary-fixed` | `#dbe1ff` | Tonalidade fixa clara |
| `--primary-fixed-dim` | `#b4c5ff` | Tonalidade fixa escura |
| `--on-primary-fixed` | `#00174b` | Texto sobre `--primary-fixed` |
| `--on-primary-fixed-variant` | `#003ea8` | Variante de texto sobre fixed |
| `--surface-tint` | `#0053db` | Tint de elevação sobre superfícies |

**Regra de ação**: botões e elementos interativos usam `#2563EB`
(`color-primary-500`), hover/press `#1D4ED8` (`color-primary-600`) e fundo
leve `#EFF6FF` (`color-primary-50`), conforme §1. Os papéis Material
(`--primary`, `--primary-container`, fixed) definem as tonalidades para chips,
alerts e superfícies tonais.

### 6.2 Superfícies

| Token | Hex | Uso |
|---|---|---|
| `--background` | `#faf8ff` | Fundo do app (`on-background` `#191b23`) |
| `--surface` | `#faf8ff` | Superfície base |
| `--surface-dim` | `#d9d9e5` | Superfície atenuada |
| `--surface-bright` | `#faf8ff` | Superfície clara |
| `--surface-container-lowest` | `#ffffff` | Cards e superfícies elevadas |
| `--surface-container-low` | `#f3f3fe` | Containers baixos |
| `--surface-container` | `#ededf9` | Container padrão |
| `--surface-container-high` | `#e7e7f3` | Container alto |
| `--surface-container-highest` | `#e1e2ed` | Container mais alto |
| `--surface-variant` | `#e1e2ed` | Variante de superfície |

### 6.3 Texto e conteúdo

| Token | Hex | Uso |
|---|---|---|
| `--on-surface` | `#191b23` | Texto sobre superfícies |
| `--on-surface-variant` | `#434655` | Variante de texto |
| `--text-main` | `#0F172A` | Texto principal / headings (`color-neutral-900`) |
| `--bg-interface` | `#F1F5F9` | Backgrounds de interface (`color-neutral-100`) |
| `--secondary` | `#515f74` | Texto/ícone secundário (`color-neutral-600` #475569 no uso prático) |
| `--on-secondary` | `#ffffff` | Sobre `--secondary` |
| `--secondary-container` | `#d5e3fc` | Container secundário (chips tonais) |
| `--on-secondary-container` | `#57657a` | Texto sobre o container secundário |
| `--secondary-fixed` | `#d5e3fc` | Tonalidade fixa |
| `--secondary-fixed-dim` | `#b9c7df` | Tonalidade fixa escura |
| `--on-secondary-fixed` | `#0d1c2e` | Texto sobre fixed |
| `--on-secondary-fixed-variant` | `#3a485b` | Variante de texto sobre fixed |

### 6.4 Bordas, divisores e outline

| Token | Hex | Uso |
|---|---|---|
| `--outline` | `#737686` | Outline de controles |
| `--outline-variant` | `#c3c6d7` | Variante de outline |
| `--border-subtle` | `#CBD5E1` | Bordas e divisores (`color-neutral-300`) |

### 6.5 Estados (semântica)

| Token | Container | On-container | Uso |
|---|---|---|---|
| `--success` `#10B981` | — | — | Conclusão de aula, progresso |
| `--error` `#EF4444` | `--error-container` `#ffdad6` | `--on-error-container` `#93000a` | Alertas, erros de validação |
| `--on-error` | — (`#ffffff`) | — | Texto sobre `--error` |
| `--warning` `#F59E0B` | — | — | Avisos, prazos |

### 6.6 Terciária e inversa

| Token | Hex | Uso |
|---|---|---|
| `--tertiary` | `#943700` | Papel Material terciário (laranja queimado) |
| `--on-tertiary` | `#ffffff` | Sobre `--tertiary` |
| `--tertiary-container` | `#bc4800` | Container terciário |
| `--on-tertiary-container` | `#ffede6` | Texto sobre o container terciário |
| `--tertiary-fixed` | `#ffdbcd` | Tonalidade fixa |
| `--tertiary-fixed-dim` | `#ffb596` | Tonalidade fixa escura |
| `--on-tertiary-fixed` | `#360f00` | Texto sobre fixed |
| `--on-tertiary-fixed-variant` | `#7d2d00` | Variante de texto sobre fixed |
| `--inverse-surface` | `#2e3039` | Superfície invertida (tooltips, snackbars) |
| `--inverse-on-surface` | `#f0f0fb` | Texto sobre superfície invertida |
| `--inverse-primary` | `#b4c5ff` | Ação sobre superfície invertida |

### 6.7 Raios (`rounded`)

| Token | Valor | Uso |
|---|---|---|
| `--radius-sm` | `0.125rem` (2px) | Detalhes mínimos |
| `--radius` | `0.25rem` (4px) | Padrão (ROUND_FOUR) |
| `--radius-md` | `0.375rem` (6px) | Botões |
| `--radius-lg` | `0.5rem` (8px) | Inputs, elementos médios |
| `--radius-xl` | `0.75rem` (12px) | Cards |
| `--radius-full` | `9999px` | Pílulas, avatares |

### 6.8 Espaçamento e layout

| Token | Valor | Uso |
|---|---|---|
| `--space-base` | `8px` | Unidade base (múltiplos de 8px) |
| `--space-1` | `4px` | Ajuste fino |
| `--space-2` | `8px` | Gaps pequenos |
| `--space-4` | `16px` | Padrão para margens internas |
| `--space-8` | `32px` | Padrão para separar seções |
| `--container-max` | `1280px` | Largura máxima do conteúdo em desktop |
| `--margin-mobile` | `24px` | Padding lateral em mobile |

### 6.9 Sombra e movimento

| Propriedade | Valor |
|---|---|
| `--shadow-card` | `0 1px 3px rgba(0,0,0,0.1)` |
| `--transition-base` | `200ms` (hover/active) |
| `--focus-ring` | 2px `color-primary-500` com offset de 2px |

---

## 7. Como usar em `_ds`

1. **Fonte da tipografia**: Inter (Google Fonts), pesos 400 / 500 / 600 / 700,
   com fallback `system-ui, sans-serif`. Substitui Archivo em
   `tokens/fonts.css` do bundle.
2. **Camada de tokens**: as variáveis da seção 6 definem o conteúdo de
   `tokens/colors.css`, `typography.css`, `spacing.css`, `elevation.css` do
   bundle `plataforma-ead-design-system-…/`. Nenhuma spec pode introduzir hex
   fora dessa camada.
3. **Direção anterior extinguida**: a linguagem "Material Bootstrap
   azul-e-menta" (Nunito Sans, `--primary #4c6fe7`, proibição de
   vermelho/amarelo) foi removida junto com `spec/new_ds/`. Onde sobrar
   referência a ela, valem os tokens deste arquivo — inclusive
   `--error #EF4444` e `--warning #F59E0B`, que existem como semântica de
   validação e prazo.
4. **Escala**: a grade muda de 4px para base **8px** (`space-1` 4px é o único
   ajuste fino). Container de 1280px substitui 1240px.
5. **Acessibilidade**: contraste mínimo WCAG AA 4.5:1; labels sempre visíveis e
   associadas via ARIA; foco nunca removido sem substituto (anel de 2px).
6. **Desempenho**: imagens com lazy-load; ícones críticos como SVG inline;
   evitar blocking de fonte no LCP.

### Origem

Exportado via API Stitch do projeto **"Plataforma EAD Design System"**
(`projects/10297532859767677570`,
https://stitch.withgoogle.com/projects/10297532859767677570), atualizado em
2026-09-02. O frontmatter YAML deste arquivo é o export canônico do Stitch e
pode ser reenviado ao projeto via `upload_design_md` sem alterações.
