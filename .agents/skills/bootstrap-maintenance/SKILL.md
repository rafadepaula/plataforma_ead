---
name: bootstrap-maintenance
description: >
  Debug, test, edge-case guide for Bootstrap 5.3 frontend of Plataforma
  EAD: stale `public/build/` gotcha that silently breaks Dusk, per-screen
  verify loop (`vendor/bin/sail npm run build` then screen's Dusk
  filter), recurring failure modes — `data-bs-toggle` inert because JS
  bundle never imported, stacked modal backdrops, dropdowns dead without
  Popper, utility class losing to leftover inline `style=`, table reflow/
  accessible `thead`/action-dropdown regressions, dompdf choking on
  Bootstrap CSS in `certificates/pdf.blade.php`. Use when Dusk test fails
  after UI change, modal/toast/dropdown does nothing in browser, migrated
  screen looks unstyled or overflows, certificate PDF renders blank.
license: MIT
metadata:
  feature: bootstrap
  role: maintenance
  specs:
    - spec/front_migration/06-skills-and-agents.md
    - spec/specs/00-architecture-database-and-guardrails.md
    - spec/front_redesign/01-direcao-visual-e-tokens.md
    - spec/front_redesign/02-camada-de-tema-e-build.md
    - spec/front_redesign/14-contrato-dusk-e-testes.md
---

# Bootstrap Maintenance

## 1. Pegadinha do `public/build/` obsoleto

**Modo de falha mais caro da migração inteira.** Primeiro a checar em
qualquer teste Dusk vermelho.

Dusk faz requisição HTTP real ao app. App serve assets pelo manifest em
`public/build/manifest.json`. Dusk **não** compila nada. Logo:

> Editar `.blade.php`, `.scss` ou `.js` e rodar `dusk` **sem** rodar
> `npm run build` faz navegador carregar CSS/JS **anterior**. Blade novo
> chega, CSS/JS velho chega junto.

Sintomas típicos, mesma causa:

- Teste falha em `waitFor('.modal.show')` — `data-bs-toggle` está no HTML,
  mas bundle carregado é antigo, sem Bootstrap JS.
- Tela "sem estilo" no screenshot de falha
  (`tests/Browser/screenshots/`): HTML já usa `.card`/`.btn`, CSS ainda é
  `app.css` antigo, sem essas classes.
- `assertVisible` passa local e falha no CI (ou inverso), depende de quem
  rodou build por último.
- `.scss` editado mas cor não muda, nem depois de recarregar, porque
  `manifest.json` ainda aponta hash antigo.

**Regra operacional, sem exceção:**

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=NomeDoTeste
```

Sempre encadeado com `&&`, sempre nessa ordem, em **todo** ciclo de
verificação. `npm run dev` (HMR) rodando em outro terminal não só **não**
substitui o build como **quebra a suíte inteira**: o dev server escreve
`public/hot`, e basta esse arquivo existir para o `@vite` servir todo
asset de `http://localhost:5173` — que, dentro do container do Selenium,
resolve para o próprio container. Resultado: todo CSS e JS morre com
`ERR_CONNECTION_REFUSED` e os testes falham por elemento ausente, sem
erro óbvio. Antes de rodar Dusk, `ls public/hot` tem que falhar.

Diagnóstico rápido de manifest podre:

```bash
vendor/bin/sail php -r 'echo file_get_contents("public/build/manifest.json");' | head -20
ls -la public/build/assets/ | head
```

Timestamp dos assets anterior à última edição de `.scss`/`.js`, build não
rodou. Último caso: `rm -rf public/build && vendor/bin/sail npm run build`.

---

## 2. Como verificar tela migrada

Loop obrigatório por tela, nessa ordem:

```bash
# 1. Zero style= inline sobrou no arquivo migrado
grep -n 'style="' resources/views/<caminho>/<tela>.blade.php

# 2. Zero classe fantasma / Tailwind sobrou
grep -nE 'btn-block|btn-icon|\bdialog\b|tag-(accent|outline|neutral)|elev-(sm|md|lg)|\bfield\b|rounded|flex |grid |text-sm|bg-white|px-[0-9]|space-y-' resources/views/<caminho>/<tela>.blade.php

# 3. Seletores dusk preservados byte-a-byte
git show HEAD:resources/views/<caminho>/<tela>.blade.php | grep -o 'dusk="[^"]*"' | sort > /tmp/dusk-before.txt
grep -o 'dusk="[^"]*"' resources/views/<caminho>/<tela>.blade.php | sort > /tmp/dusk-after.txt
diff /tmp/dusk-before.txt /tmp/dusk-after.txt   # tem que sair vazio

# 4. Build + o Dusk específico da tela
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<TesteDaTela>

# 5. Pint, se algum PHP foi tocado
vendor/bin/sail bin pint --dirty --format agent
```

Mapa tela, teste Dusk (usar sempre filtro mais estreito):

| Área | Filtro Dusk |
| :--- | :--- |
| Layout, sidebar, topbar, radius | `LayoutRenderingTest` |
| Componentes `<x-ui.*>` | `BladeComponentsTest` |
| Navegação / menus por role | `NavigationMenuDuskTest` |
| Login, convite, reset | `Auth\...` (ver `tests/Browser/Auth/`) |
| Perfil | `ProfileTest` |
| CRUD de usuários / alunos | `UserManagementTest` |
| CRUD de organizações | `OrganizationCrudTest` |
| Import CSV | `MultiTenantStudentImportTest` |
| Cursos (gestão) | `CourseManagementTest` |
| Reorder de módulos | `ModuleReorderTest` |
| Player de aula / vídeo | `LessonPlayerDuskTest`, `ModuleAndLessonManagementDuskTest` |
| Quiz (aluno) | `StudentQuizTakingDuskTest` |
| Correção discursiva | `EssayGradingScreenTest` |
| Fórum | `ForumDuskTest` |
| Certificados | `CertificateVerificationTest`, `CertificateRevocationTest`, `CourseCompletionRuleTest` |
| Dashboard | `DashboardDuskTest` |
| Notificações (sino) | `NotificationBellTest` |
| Audit logs (modal de diff) | `AuditLogUiTest` |
| Help center (modal) | `HelpCenterDuskTest` |
| Impersonate org | `ImpersonateOrgTest` |
| Matrícula multi-org | `MultiOrgEnrollmentTest`, `MultiOrgStudentClassroomTest` |

Tabela, filtros mínimos:

```bash
# Contrato Blade, alias, tokens, reflow e paginação
vendor/bin/sail artisan test --compact tests/Feature/UiTableComponentTest.php

# Build obrigatório antes do browser; reflow, thead acessível, seletor único,
# foco/teclado do dropdown e ausência de scroll horizontal
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=test_courses_index_management_screen_has_no_horizontal_scroll

# Outras listagens migradas para markup único
vendor/bin/sail artisan dusk --filter=test_organizations_index_management_screen_has_no_horizontal_scroll
vendor/bin/sail artisan dusk --filter=test_admin_users_index_management_screen_has_no_horizontal_scroll

# Dropdown de ações do admin dentro da cadeia real
vendor/bin/sail artisan dusk --filter=test_admin_manages_users_across_orgs_without_impersonating
```

Fim de fase (não a cada tela): `vendor/bin/sail artisan dusk` completo.

---

## 3. Modos de falha recorrentes

### 3.1 `data-bs-toggle` inerte (nada acontece ao clicar)

- **Sintoma:** botão com `data-bs-toggle="modal"` / `="dropdown"` /
  `="collapse"` não faz nada; console sem erro; Dusk estoura em
  `waitFor('.modal.show')`.
- **Causas, ordem de frequência:**
  1. `resources/js/app.js` **não** importa Bootstrap. Confirme primeira
     linha `import * as bootstrap from 'bootstrap';`.
  2. Build velho (§1).
  3. `data-bs-target` aponta id inexistente ou duplicado. Id duplicado é
     comum em lista — inclua chave: `id="confirm-delete-{{ $user->id }}"`.
  4. Elemento injetado no DOM **depois** do load por AJAX: atributos
     `data-bs-*` funcionam por delegação para modal/dropdown/collapse, mas
     elemento recriado dentro de container substituído precisa instância
     explícita: `bootstrap.Modal.getOrCreateInstance(el)`.
  5. Layout da tela é `guest.blade.php` e ele **não** tem
     `@vite(['resources/scss/app.scss','resources/js/app.js'])`. Cheque os
     dois layouts — esse erro atinge só telas públicas.
- **Verificação rápida no browser:** `typeof window.bootstrap` deve
  retornar `"object"`. Em Dusk:
  `$browser->script('return typeof window.bootstrap')[0]`.

### 3.2 Backdrops de modal empilhados / página trava com scroll bloqueado

- **Sintoma:** ao fechar modal tela continua escura e sem scroll; sobram
  `<div class="modal-backdrop">` no DOM.
- **Causas:**
  1. Dupla instanciação: markup com `data-bs-toggle` **e** JS chamando `new
     bootstrap.Modal(el)`. Use `getOrCreateInstance`, escolha **um**
     caminho por modal.
  2. Resíduo do `ModalManager` antigo ainda registrado em `app.js`,
     adiciona backdrop próprio. Remova módulo, não "desligue".
  3. Modal renderizado **dentro** de container com `transform`, `filter` ou
     `overflow: hidden` — cria containing block, quebra `position: fixed`.
     (A antiga `.grayscale` fazia `filter` e era o exemplo clássico; foi
     removida do projeto na Fase 2 do `front_redesign`, substituída por
     `.ds-pastel-wash` — que usa `background: linear-gradient(...)`, não
     `filter`, então não sofre mais deste bug. O guardrail continua valendo
     para qualquer outra classe de camada 3 que aplique `transform`/`filter`
     a um ancestral de modal.) **Modais devem ser irmãos do conteúdo,
     idealmente no fim do `<body>` do layout**, nunca dentro de card com
     filtro.
  4. Modal aberto de dentro de outro modal sem fechar primeiro. Bootstrap
     suporta empilhar, mas Dusk acerta backdrop errado: prefira fechar
     primeiro em `hidden.bs.modal` e abrir segundo.
- **Limpeza de emergência (só depuração, nunca produção):**
  `document.querySelectorAll('.modal-backdrop').forEach(e => e.remove())`.

### 3.3 Dropdown não abre (Popper ausente)

- **Sintoma:** menu de perfil/notificações da topbar não abre; console
  mostra `Bootstrap doesn't allow more than one instance per element` ou
  `Popper__namespace is not defined`.
- **Causa:** import parcial do Bootstrap (`import Modal from 'bootstrap/js/dist/modal'`)
  sem Popper, ou CSS do bundle com JS sem bundle.
- **Solução:** importe pacote inteiro (`import * as bootstrap from 'bootstrap'`),
  resolve `@popperjs/core` via dependência npm. Se import avulso for
  imprescindível, `bootstrap/js/dist/dropdown` **exige** `@popperjs/core`
  como dependência explícita — e adicionar dependência precisa de
  aprovação.
- Dropdown também morre se `.dropdown-menu` não for irmão imediato do
  gatilho dentro de `.dropdown`/`.btn-group`.

### 3.4 Utility perde para `style=` remanescente

- **Sintoma:** elemento tem `class="text-primary mb-3"` e continua preto e
  colado; DevTools mostra regra riscada.
- **Causa:** `style="color: var(--color-text); margin-bottom: 0"` sobrou no
  mesmo elemento (ou em componente pai que ainda faz
  `$attributes->merge(['style' => ...])`). Estilo inline vence qualquer
  classe, `!important` à parte.
- **Solução:** remover inline — **nunca** vencer com `!important`. Inline
  vindo do componente significa componente não migrado: migre-o antes da
  tela.
- **Varredura global:**
  ```bash
  grep -rn 'style="' resources/views --include='*.blade.php' | grep -v 'certificates/pdf.blade.php'
  ```
  Alvo ao fim da migração: **zero linha** nessa saída.

### 3.5 Tabela estourando na horizontal ou sem reflow

- **Sintoma:** scroll horizontal na página inteira no mobile; Dusk com
  viewport estreito falha ao clicar botão da última coluna ("element not
  interactable"/"outside of viewport").
- **Causas:** tabela crua; `responsive=false`; `<td>` sem `data-label`;
  `_table.scss` ausente de `components/_index.scss`; build velho.
- **Solução:** `<x-ui.table>` ou alias `<x-ui.data-table>`, prop responsive
  default, um `data-label` por célula. Nunca duplique desktop/mobile.
- **Checagem:** em 375px, `.ds-table tbody` = `grid`; `<tr>` = `block`;
  célula = `grid`; `::before` contém rótulo; `scrollWidth <= innerWidth`.

### 3.5.1 Cabeçalho some para leitor de tela no mobile

- **Sintoma:** layout em cards funciona, mas árvore acessível não contém
  cabeçalhos.
- **Causa:** `display:none` ou `aria-hidden="true"` no `<thead>`.
- **Solução:** manter `<thead>` sem `aria-hidden`; `_table.scss` usa recorte
  visual (posição absoluta, caixa 1px, overflow oculto). `th` continua
  renderizado.

### 3.5.2 Dropdown “Mais” não abre, não recebe foco ou corta ações

- **Sintoma:** quarta ação inacessível; seta para baixo não foca item; Escape
  não fecha; menu recortado.
- **Causas:** mais de 3 ações expostas sem menu; id repetido; `aria-labelledby`
  inválido; gatilho sem `data-bs-toggle="dropdown"`; menu fora de `.dropdown`;
  build JS velho.
- **Solução:** máximo 3 ações visíveis. Gatilho com id único,
  `aria-expanded="false"`, `aria-label` contextual; menu
  `.dropdown-menu-end` com `aria-labelledby`. Ícone decorativo =
  `aria-hidden="true"`. Confirme ArrowDown e Escape no navegador.
- Modal de confirmação fica fora da tabela. Se menu ainda recortar, use
  estratégia fixa do Bootstrap no gatilho; não duplique ações fora do menu.

### 3.6 dompdf engasga com CSS do Bootstrap (certificado em branco/quebrado)

- **Sintoma:** `certificates/pdf.blade.php` gera PDF em branco, sem layout,
  ou `barryvdh/laravel-dompdf` lança erro de parsing de CSS.
- **Causa:** dompdf implementa aproximadamente CSS 2.1. **Não** entende:
  custom properties (`var(--bs-primary)`), `color-mix()`, `rgba()` moderno
  em sintaxe de espaço, flexbox, CSS grid, `:root`, media queries
  modernas, `@layer`. CSS do Bootstrap 5.3 é praticamente todo construído
  sobre `--bs-*`, então **carregá-lo no PDF quebra o documento**.
- **Regra:** `certificates/pdf.blade.php` **não** usa `@vite` e **não**
  carrega bundle. Mantém `<style>` embutido, CSS 2.1 puro, valores hex
  literais, layout por `table`/`float`. **Única** exceção autorizada às
  regras "zero CSS ad-hoc" e "zero hex hardcoded".
- **Ao migrar:** arquivo explicitamente **fora de escopo**. Agente de
  migração que o tocar comete violação de escopo.
- **Verificação:** `vendor/bin/sail artisan test --filter=CertificateEligibilityTest`
  e Dusk `CertificateVerificationTest`; inspeção visual, gerar PDF e abrir.

### 3.7 [SUBSTITUÍDO — Fase 0 `front_redesign`] Radius indo para 0 "sozinho"

- Esta seção descrevia o mandato Modernist antigo (canto reto, `$enable-rounded: false`).
  **Invertido**: o sistema atual (`spec/front_redesign/01-direcao-visual-e-tokens.md`)
  quer canto **suave** (`$border-radius: 14px`, botões pílula `999px`), vindo
  de `_bridge.scss`.
- **Sintoma agora:** elemento renderiza com canto reto (`0px`) numa tela já
  migrada para o redesign.
- **Causas:** `_bridge.scss` não importado antes de `bootstrap/scss/bootstrap`
  em `app.scss`; componente novo em `resources/scss/components/*.scss`
  força `border-radius: 0` por hábito do sistema antigo; utility `rounded-0`
  usada por engano na Blade.
- **Solução:** conferir ordem dos 4 imports de `app.scss` (tokens → `bridge`
  → `bootstrap` → `components/index`, ver `bootstrap-architecture`); nunca
  hardcodar radius em componente — deixar a variável Sass da ponte resolver.
- Em tela **ainda não migrada** (Fases 1–8 pendentes), o canto reto antigo é
  esperado até a migração daquela tela — não é regressão.

### 3.8 Fonte errada (não é Nunito Sans)

- **Sintoma:** texto renderiza em sans genérica/diferente de Nunito Sans.
- **Causas:** `$font-family-base` não chega da ponte (`_bridge.scss` não
  importado, ou importado depois de `bootstrap/scss/bootstrap`); Google
  Fonts `<link>` de Nunito Sans ausente do layout.
- **Nota histórica:** a fonte antiga era Archivo self-hosted
  (`public/fonts/archivo/*.woff2`); esse diretório e todo `@font-face`
  específico foram **removidos** na Fase 0 do redesign — não recriar.
- **Solução:** conferir `$font-family-base` em `_bridge.scss` e a tag de
  import da fonte no `<head>`.

### 3.9 Cor errada / accent virou azul Bootstrap padrão (`#0d6efd`) ou vermelho/amarelo

- **Sintoma:** botão primário no azul stock do Bootstrap, ou alerta/badge
  vermelho/amarelo puro (nunca deveria — vermelho, laranja e amarelo são
  **proibidos** no sistema, ver doc 01).
- **Causa:** `_bridge.scss` importado **depois** de `bootstrap/scss/bootstrap`
  em `app.scss` (variáveis `!default` já resolveram); ou só `$danger`/`$warning`
  foram remapeados e `$red`/`$orange`/`$yellow` de base ficaram no padrão do
  Bootstrap, vazando pelos mapas `$reds`/`$yellows` internos.
- **Solução:** respeitar a ordem dos 4 imports de `app.scss`: tokens `_ds/`,
  `bridge`, `bootstrap`, `components/index`. Ver `bootstrap-architecture`.
  Confirmar que `_bridge.scss` remapeia `$red`/`$orange`/`$yellow` também,
  não só `$danger`/`$warning`.

### 3.10b [Fase 1 `front_redesign`] Classe com cara de utility mas sem CSS nenhum por trás

- **Sintoma:** elemento renderiza sem estilo algum (não é "canto reto",
  simplesmente **nenhuma** regra bate) mesmo depois de build fresco; a
  classe não é uma das "fantasmas" listadas em `bootstrap-conventions` §3.5
  nem Tailwind.
- **Causa:** código pré-redesign inventou classes com aparência de utility
  do projeto (`min-w-16`, `h-16`, `fs-10`, `w-340`, `max-w-90vw`,
  `max-h-420`, `max-h-360`, `guest-form-max-w`, `guest-help-pos`) que
  **nunca existiram** em nenhum `.scss` — igual às classes fantasma do
  Modernist, só que sem estar na lista fechada porque foram inventadas
  depois. Achadas ao migrar `notifications-bell.blade.php` e
  `guest.blade.php` na Fase 1.
- **Solução:** não reusar uma classe só porque ela já aparece em outra
  Blade — confirmar que existe em `resources/scss/components/*.scss`,
  `_bridge.scss`, ou é utility real do Bootstrap
  (`grep -rn '\.<classe>' resources/scss/`). Sem match, é utility real do
  Bootstrap (ex. `.badge.rounded-pill`, `top-0 start-100 translate-middle`)
  ou classe nova na camada 3 — nunca herdar a classe morta.

### 3.10 Dusk clicando no elemento errado depois da migração

- **Sintoma:** `click('@salvar')` acerta outro elemento, ou
  `assertSeeIn('@card-titulo', ...)` falha embora texto esteja visível.
- **Causas:** `dusk` migrou para elemento de granularidade diferente (foi
  do `<button>` para o `<div>` que envolve); ou mesmo `dusk` passou a
  existir duas vezes porque componente faz `merge` e tela também escreveu
  atributo.
- **Solução:** rodar diff de `dusk=` do §2 passo 3; garantir cada valor de
  `dusk` único por página.

### 3.11 [Fase 8 `front_redesign`] Cluster direito da app bar estoura em 320–375px

- **Sintoma:** `document.body.scrollWidth > window.innerWidth` em qualquer
  tela autenticada abaixo de ~450px, mesmo em telas que não têm tabela nem
  conteúdo largo — porque o culpado é o **shell**, não a tela.
- **Causa:** `<x-layout.topbar>` (o cluster de ajuda/sino/avatar/"Meu
  Perfil"/"Sair" à direita) tinha largura natural fixa e nunca colapsava; em
  telas < ~450px ele sozinho estourava o viewport por 80–115px.
- **Solução aplicada:** abaixo de `sm`, rótulo em texto vira ícone +
  `.visually-hidden` (nunca ícone mudo) em "Meu Perfil"/"Sair", o círculo de
  iniciais (`.ds-avatar`) some (redundante com o ícone do link ao lado), e o
  nome da organização/tenant na marca cede lugar ao `.brand-mark` (quadrado
  de iniciais). Ver `topbar.blade.php` e `bootstrap-architecture` para o
  detalhe. **Qualquer novo item adicionado ao cluster direito da topbar
  precisa do mesmo tratamento** (ícone-only + `.visually-hidden` abaixo de
  `sm`) ou o estouro volta.
- **Como checar:** `tests/Browser/Theme/ResponsiveShellTest.php` (shell
  desktop/mobile e drawer) e `tests/Browser/Theme/StudentMobileScreensTest.php`
  (compara `scrollWidth`/`innerWidth` a 375px nas telas do Aluno) — rode-os
  depois de qualquer mudança na topbar ou no drawer.

### 3.12 [Fase 8 `front_redesign`] `prefers-reduced-motion` — escopo fechado

- **Só** `.modal.fade .modal-dialog` e `.offcanvas.fade` (o drawer mobile)
  perdem transição/transform sob `@media (prefers-reduced-motion: reduce)`
  (`_reduced-motion.scss`). **Não** generalize para outros componentes
  animados (dropdown, toast, `.ds-state-layer`, `collapse`) — o doc 13 pede
  redução só nesses dois, não "desligar toda animação do site". Se um review
  pedir para ampliar o escopo, é sinal de leitura errada do doc, não de
  lacuna real.

---

## 4. Checklist de regressão por tela migrada

Marque tudo antes de considerar tela concluída:

- [ ] `grep 'style="'` no arquivo retorna vazio.
- [ ] Nenhuma classe fantasma (`dialog`, `tag-*`, `elev-*`, `field`,
      `input` solto) e nenhuma classe Tailwind.
- [ ] `diff` dos `dusk="..."` antes/depois vazio (ou divergência justificada por
      escrito no receipt).
- [ ] Nenhum markup Bootstrap cru que deveria ser `<x-ui.*>`.
- [ ] **[Fase 0+ `front_redesign`]** Canto suave visualmente em botões
      (pílula), cards (`20px`), inputs (`14px`), modais (`28px`), badges —
      **não** `border-radius: 0` (mandato antigo, substituído).
- [ ] Fonte Nunito Sans; sem resíduo de Archivo.
- [ ] Primário `--blue-600 #4c6fe7`; nenhum vermelho/laranja/amarelo puro em
      elemento novo (proibidos no sistema — ver doc 01 do redesign).
- [ ] Faixas de mídia (curso/pessoa) usam `.ds-pastel-wash` — **não**
      `.grayscale`, que foi removida do projeto inteiro na Fase 2 do
      `front_redesign`; logo da organização com `.org-logo`, real desde a
      Fase 4 (`resources/scss/components/_organizations.scss`), não mais
      isenta da varredura de classe fantasma.
- [ ] Formulários: cada campo com `<label for>`; erros via `.is-invalid` +
      `.invalid-feedback`; `dusk="error-{campo}"` presente.
- [ ] Modais: `aria-labelledby`, `.btn-close` com `aria-label="Fechar"`, foco
  volta ao gatilho ao fechar.
- [ ] Tabela usa `<x-ui.table>` ou alias `<x-ui.data-table>`; anatomia
      `.ds-table-wrap > .ds-table-scroll > .ds-table`; `data-label` em cada
      `<td>`; zero marcação desktop/mobile duplicada.
- [ ] Mobile: `<thead>` recortado visualmente, presente na árvore acessível,
      sem `display:none`/`aria-hidden`; rótulo `::before` visível.
- [ ] Máximo 3 ações visíveis. Quarta+ no dropdown “Mais”, com id único,
      `aria-label`, `aria-labelledby`, ArrowDown e Escape funcionais.
- [ ] **[Fase 8]** Sem scroll horizontal em 320/375/768/1024/1440px de
      largura (`document.body.scrollWidth === window.innerWidth`).
- [ ] **[Fase 8]** Todo controle interativo ≥ 48px de alvo de toque
      (`--touch-min`); exceção única: `<x-ui.button size="sm">` (40px)
      **dentro** de `<tr>` de tabela.
- [ ] **[Fase 8]** Cor nunca é o único sinal: status tem rótulo em texto além
      do chip; ação destrutiva tem ícone **e** palavra.
- [ ] `vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<Teste>` verde.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo (se tocou PHP).
- [ ] `<x-help-button>` da tela continua presente e funcional (RF12/RN05).

## 5. Comandos de auditoria global (fim de fase)

```bash
# style= inline restantes (alvo: 0, exceto certificates/pdf.blade.php)
grep -rn 'style="' resources/views --include='*.blade.php' | grep -vc 'certificates/pdf'

# classes fantasma restantes (btn-ghost NÃO entra aqui — é classe real desde a Fase 2, ver _state-layer.scss)
grep -rnE 'btn-block|btn-icon|dialog-backdrop|tag-(accent|outline|neutral)|elev-(sm|md|lg)|\bgrayscale\b' resources/views

# resíduo Tailwind
grep -rnE 'class="[^"]*(\bflex\b|\bgrid\b|space-y-|text-(xs|sm|base|lg|xl)\b|bg-white|rounded-)' resources/views

# módulos JS artesanais que deveriam ter sumido
grep -rn 'ModalManager' resources/js resources/views

# padrões tabulares obsoletos ou células sem revisão manual de data-label
grep -rnE 'd-none d-md-block|d-md-none' resources/views/{courses,organizations,users,admin/users,audit-logs}
grep -rn '<table' resources/views --include='*.blade.php'

# contrato canônico da tabela
vendor/bin/sail artisan test --compact tests/Feature/UiTableComponentTest.php

# contagem de dusk= (deve permanecer == 400, baseline versionado atual)
grep -ro 'dusk="' resources/views | wc -l

# suíte completa
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk
vendor/bin/sail artisan test --compact
```

### 5.1 [Fase 0 `front_redesign`] Testes de regressão automática do tema

`tests/Feature/Theme/` (criados na Fase 0) automatizam parte da auditoria
acima — rode-os antes do gate manual:

- `CompiledCssTokenRegressionTest` — lê `public/build/assets/*.css`
  compilado (via `manifest.json`, não invoca Sass) e falha se aparecer
  matiz vermelho/laranja/amarelo saturado, ou `border-radius: 0`
  injustificado fora dos resets conhecidos do próprio Bootstrap. **Exige
  build fresco** — mesma pegadinha da seção 1.
- `InlineStyleRegressionTest` — varre todo `*.blade.php` e falha em
  qualquer `style="..."` fora de `certificates/pdf.blade.php`.
- `DuskSelectorContractTest` — compara a contagem e o conjunto
  `arquivo::seletor` de `dusk="..."` atual contra
  `tests/fixtures/dusk-selectors-snapshot.json` (baseline com 400
  entradas). Atualizar o snapshot **só** com justificativa explícita —
  nunca para "fazer o teste passar".

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan test tests/Feature/Theme
```

---

## Mapa Tela/Teste Dusk é Atalho, Não a Organização Real

Filtros da tabela acima são atalhos práticos. Organização real da suíte é
por **cadeia de ciclo de vida (jornada do usuário)**, não por tela/módulo:
um método cobre criar, editar, transicionar, excluir, consequência, e pode
cruzar módulos. Consequências ao migrar tela:

- Filtro da tabela não existe mais (cadeias consolidadas), ache teste pelo
  seletor preservado:
  ```bash
  grep -rn 'dusk="<seletor-da-tela>"' tests/Browser/
  ```
- Tela pode ser exercitada por **várias etapas dentro de uma cadeia**, não
  por teste dedicado — ausência de arquivo por tela não é lacuna.
- Precisando de nova cobertura de navegador, estenda cadeia da jornada com
  etapa numerada (asserção de UI + banco) em vez de criar método atômico
  por tela.
- Nenhuma trait de banco em `tests/Browser/*`: `DatabaseTruncation` vem de
  `Tests\DuskTestCase`.

Regra completa: `testing-conventions`.

## Paginação e catálogo de cursos

Sintoma: resumo aparece duas vezes ou landmark `nav` fica aninhado. Causa:
`<x-ui.pagination>` voltou a usar `pagination::bootstrap-5`. Corrija mantendo
views internas links-only.

Sintoma: página estoura abaixo de `sm`. Causa: lista numerada visível no mobile.
Branch mobile deve mostrar previous/next apenas; branch numerado usa
`d-none d-sm-flex`. Cada `.page-link` mede 40x40.

Sintoma: catálogo estoura abaixo de `md`. Confirme `_courses.scss` importado,
larguras 150/110/130/470px só no desktop e reset para 100% no media query.
Catálogo mantém quatro ações visíveis; teste responsivo não deve procurar
dropdown `course-actions-toggle` aposentado.

Gates focados:

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Feature/UiTableComponentTest.php
vendor/bin/sail artisan dusk --filter=CourseManagementTest
vendor/bin/sail artisan dusk --filter=test_courses_index_management_screen_has_no_horizontal_scroll
```

## Raio de 28px "surgindo" num card (`rounded-4`)

Sintoma: um card fica visivelmente mais arredondado que os vizinhos. Causa:
`rounded-4` na marcação. `_bridge.scss` redefine `$border-radius-xl: 28px`
(raio de modal), então `rounded-4` **não** entrega os 20px de card.
Correção: apagar `rounded-4` — `.card` já traz `$card-border-radius: 20px`.
Não adicione outra classe de raio por cima. Ver `bootstrap-conventions`
§5.1, e §5.2 para a família `.ds-*` da tela de aula (SPEC-28), incluindo a
proibição do atributo `hidden` nos controles de conclusão.
