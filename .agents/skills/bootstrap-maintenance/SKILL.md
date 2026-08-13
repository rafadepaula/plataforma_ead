---
name: bootstrap-maintenance
description: >
  Debugging, testing, and edge-case guide for the Bootstrap 5.3 frontend of
  the Plataforma EAD: the stale `public/build/` gotcha that silently breaks
  Dusk, the per-screen verification loop (`vendor/bin/sail npm run build`
  then the screen's Dusk filter), and the recurring failure modes —
  `data-bs-toggle` inert because the JS bundle was never imported, stacked
  modal backdrops, dropdowns dead without Popper, a utility class losing to
  a leftover inline `style=`, missing `.table-responsive` causing horizontal
  overflow, and dompdf choking on Bootstrap CSS in
  `certificates/pdf.blade.php`. Use when a Dusk test fails after a UI
  change, a modal/toast/dropdown does nothing in the browser, a migrated
  screen looks unstyled or overflows, or the certificate PDF renders blank.
license: MIT
metadata:
  feature: bootstrap
  role: maintenance
  specs:
    - spec/front_migration/06-skills-and-agents.md
    - spec/specs/00-architecture-database-and-guardrails.md
---

# Bootstrap Maintenance

## 1. A pegadinha do `public/build/` obsoleto

**Este é o modo de falha mais caro da migração inteira** e o primeiro a
verificar em qualquer teste Dusk vermelho.

O Dusk faz requisições HTTP reais ao app, que serve os assets pelo manifest em
`public/build/manifest.json`. O Dusk **não** compila nada. Portanto:

> Editar `.blade.php`, `.scss` ou `.js` e rodar `dusk` **sem** rodar
> `npm run build` faz o navegador carregar o CSS/JS **anterior**. O Blade novo
> chega, o CSS/JS velho chega junto.

Sintomas típicos, todos com a mesma causa:

- O teste falha em `waitFor('.modal.show')` — o `data-bs-toggle` está no HTML,
  mas o bundle carregado é o antigo, sem o Bootstrap JS.
- A tela aparece "sem estilo" no screenshot de falha
  (`tests/Browser/screenshots/`): o HTML já usa `.card`/`.btn`, o CSS ainda é o
  `app.css` antigo, que não tem essas classes.
- Um `assertVisible` passa localmente e falha no CI (ou o inverso), dependendo de
  quem rodou o build por último.
- O `.scss` foi editado mas a cor não muda — e "não muda" mesmo depois de
  recarregar, porque o `manifest.json` ainda aponta o hash antigo.

**Regra operacional, sem exceção:**

```bash
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=NomeDoTeste
```

Sempre encadeado com `&&`, sempre nessa ordem, em **todo** ciclo de verificação.
Se estiver com `npm run dev` (HMR) rodando em outro terminal, ele **não** substitui
o build: o Dusk não usa o dev server a menos que `APP_URL` aponte para ele e o
hot file exista. Na dúvida, rode o build.

Diagnóstico rápido de manifest podre:

```bash
vendor/bin/sail php -r 'echo file_get_contents("public/build/manifest.json");' | head -20
ls -la public/build/assets/ | head
```

Se o timestamp dos assets for anterior à sua última edição de `.scss`/`.js`,
o build não rodou. Em último caso: `rm -rf public/build && vendor/bin/sail npm run build`.

---

## 2. Como verificar uma tela migrada

Loop obrigatório por tela, na ordem:

```bash
# 1. Zero style= inline sobrou no arquivo migrado
grep -n 'style="' resources/views/<caminho>/<tela>.blade.php

# 2. Zero classe fantasma / Tailwind sobrou
grep -nE 'btn-ghost|btn-block|btn-icon|\bdialog\b|tag-(accent|outline|neutral)|elev-(sm|md|lg)|\bfield\b|rounded|flex |grid |text-sm|bg-white|px-[0-9]|space-y-' resources/views/<caminho>/<tela>.blade.php

# 3. Seletores dusk preservados byte-a-byte
git show HEAD:resources/views/<caminho>/<tela>.blade.php | grep -o 'dusk="[^"]*"' | sort > /tmp/dusk-before.txt
grep -o 'dusk="[^"]*"' resources/views/<caminho>/<tela>.blade.php | sort > /tmp/dusk-after.txt
diff /tmp/dusk-before.txt /tmp/dusk-after.txt   # tem que sair vazio

# 4. Build + o Dusk específico da tela
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<TesteDaTela>

# 5. Pint, se algum PHP foi tocado
vendor/bin/sail bin pint --dirty --format agent
```

Mapa tela → teste Dusk (usar sempre o filtro mais estreito):

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
| Player de aula / vídeo | `LessonMultimediaTest`, `VideoThresholdCompletionTest` |
| Quiz (aluno) | `StudentQuizAttemptTest` |
| Correção discursiva | `EssayGradingScreenTest` |
| Fórum | `ForumDuskTest` |
| Certificados | `CertificateVerificationTest`, `CertificateRevocationTest`, `CourseCompletionRuleTest` |
| Dashboard | `DashboardDuskTest` |
| Notificações (sino) | `NotificationBellTest` |
| Audit logs (modal de diff) | `AuditLogUiTest` |
| Help center (modal) | `HelpCenterDuskTest` |
| Impersonate org | `ImpersonateOrgTest` |
| Matrícula multi-org | `MultiOrgEnrollmentTest`, `MultiOrgStudentClassroomTest` |

Ao final de uma fase (não a cada tela): `vendor/bin/sail artisan dusk` completo.

---

## 3. Modos de falha recorrentes

### 3.1 `data-bs-toggle` inerte (nada acontece ao clicar)

- **Sintoma:** botão com `data-bs-toggle="modal"` / `="dropdown"` /
  `="collapse"` não faz nada; console sem erro; Dusk estoura em
  `waitFor('.modal.show')`.
- **Causas, em ordem de frequência:**
  1. `resources/js/app.js` **não** importa o Bootstrap. Confirme que a primeira
     linha é `import * as bootstrap from 'bootstrap';`.
  2. O build está velho (§1).
  3. O `data-bs-target` aponta para um id inexistente ou duplicado. Ids duplicados
     são comuns em listas — inclua a chave: `id="confirm-delete-{{ $user->id }}"`.
  4. O elemento foi injetado no DOM **depois** do load por AJAX: os atributos
     `data-bs-*` funcionam por delegação para modal/dropdown/collapse, mas para
     elementos recriados dentro de um container substituído, instancie
     explicitamente com `bootstrap.Modal.getOrCreateInstance(el)`.
  5. O layout usado pela tela é `guest.blade.php` e ele **não** tem
     `@vite(['resources/scss/app.scss','resources/js/app.js'])`. Verifique os dois
     layouts — este erro atinge só as telas públicas.
- **Verificação rápida no browser:** `typeof window.bootstrap` deve retornar
  `"object"`. Em Dusk: `$browser->script('return typeof window.bootstrap')[0]`.

### 3.2 Backdrops de modal empilhados / página trava com scroll bloqueado

- **Sintoma:** ao fechar o modal a tela continua escura e sem scroll; sobram
  `<div class="modal-backdrop">` no DOM.
- **Causas:**
  1. Dupla instanciação: markup com `data-bs-toggle` **e** JS chamando `new
     bootstrap.Modal(el)`. Use `getOrCreateInstance` e escolha **um** dos dois
     caminhos por modal.
  2. Resíduo do `ModalManager` antigo ainda registrado em `app.js` adicionando o
     seu próprio backdrop. Remova o módulo, não o "desligue".
  3. Modal renderizado **dentro** de um container com `transform`, `filter` ou
     `overflow: hidden` (o `.grayscale` faz `filter`!) — isso cria um containing
     block e quebra o `position: fixed`. **Modais devem ser irmãos do conteúdo,
     idealmente no fim do `<body>` do layout**, nunca dentro de um card com filtro.
  4. Modal aberto de dentro de outro modal sem fechar o primeiro. O Bootstrap
     suporta empilhar, mas o Dusk vai acertar o backdrop errado: prefira fechar o
     primeiro em `hidden.bs.modal` e abrir o segundo.
- **Limpeza de emergência (só para depurar, nunca em produção):**
  `document.querySelectorAll('.modal-backdrop').forEach(e => e.remove())`.

### 3.3 Dropdown não abre (Popper ausente)

- **Sintoma:** menu de perfil/notificações da topbar não abre; console mostra
  `Bootstrap doesn't allow more than one instance per element` ou
  `Popper__namespace is not defined`.
- **Causa:** import parcial do Bootstrap (`import Modal from 'bootstrap/js/dist/modal'`)
  sem Popper, ou uso do CSS do bundle com JS sem bundle.
- **Solução:** importe o pacote inteiro (`import * as bootstrap from 'bootstrap'`),
  que resolve `@popperjs/core` via dependência do npm. Se for imprescindível
  importar módulos avulsos, `bootstrap/js/dist/dropdown` **exige**
  `@popperjs/core` como dependência explícita — e adicionar dependência precisa de
  aprovação.
- Dropdown também morre se o `.dropdown-menu` não for irmão imediato do gatilho
  dentro de um `.dropdown`/`.btn-group`.

### 3.4 Utility perde para um `style=` remanescente

- **Sintoma:** o elemento tem `class="text-primary mb-3"` e continua preto e
  colado; o DevTools mostra a regra riscada.
- **Causa:** um `style="color: var(--color-text); margin-bottom: 0"` sobrou no
  mesmo elemento (ou num componente pai que ainda faz
  `$attributes->merge(['style' => ...])`). Estilo inline vence qualquer classe,
  `!important` à parte.
- **Solução:** remover o inline — **nunca** vencer com `!important`. Se o inline
  vem do componente, o componente ainda não foi migrado: migre-o antes da tela.
- **Varredura global:**
  ```bash
  grep -rn 'style="' resources/views --include='*.blade.php' | grep -v 'certificates/pdf.blade.php'
  ```
  O alvo ao fim da migração é **zero linha** nessa saída.

### 3.5 Tabela estourando na horizontal (falta `.table-responsive`)

- **Sintoma:** scroll horizontal na página inteira no mobile; Dusk com viewport
  estreito falha ao clicar num botão da última coluna ("element not
  interactable"/"outside of viewport").
- **Causa:** `<table class="table">` sem wrapper.
- **Solução:** sempre
  ```blade
  <div class="table-responsive">
      <table class="table align-middle">…</table>
  </div>
  ```
  O `<x-ui.table>` já emite o wrapper — o erro só aparece quando alguém escreve
  `<table>` cru numa tela (proibido, ver `bootstrap-conventions` §3.12).
- Sintoma correlato: dropdown de ações da última coluna cortado pelo
  `overflow: auto` do `.table-responsive`. Solução: `data-bs-strategy="fixed"` no
  gatilho, ou mover a ação para um modal.

### 3.6 dompdf engasga com o CSS do Bootstrap (certificado em branco/quebrado)

- **Sintoma:** `certificates/pdf.blade.php` gera PDF em branco, sem layout, ou
  `barryvdh/laravel-dompdf` lança erro de parsing de CSS.
- **Causa:** dompdf implementa aproximadamente CSS 2.1. Ele **não** entende:
  custom properties (`var(--bs-primary)`), `color-mix()`, `rgba()` moderno em
  sintaxe de espaço, flexbox, CSS grid, `:root`, media queries modernas,
  `@layer`. O CSS do Bootstrap 5.3 é praticamente todo construído sobre
  `--bs-*`, então **carregá-lo no PDF quebra o documento**.
- **Regra:** `certificates/pdf.blade.php` **não** usa `@vite` e **não** carrega o
  bundle. Ele mantém um `<style>` embutido, com CSS 2.1 puro, valores hex
  literais e layout por `table`/`float`. Esta é a **única** exceção autorizada às
  regras de "zero CSS ad-hoc" e "zero hex hardcoded".
- **Ao migrar:** este arquivo é explicitamente **fora de escopo**. Se um agente de
  migração o tocar, é violação de escopo.
- **Verificação:** `vendor/bin/sail artisan test --filter=CertificateEligibilityTest`
  e o Dusk `CertificateVerificationTest`; para inspeção visual, gerar o PDF e abrir.

### 3.7 Radius voltando "sozinho"

- **Sintoma:** `LayoutRenderingTest` falha com `Expected border-radius to enforce 0px`.
- **Causas:** `$enable-rounded` não está `false`; alguém usou `rounded`,
  `rounded-pill` ou `rounded-circle`; um componente novo do Bootstrap
  (`.form-control`, `.dropdown-menu`, `.toast`, `.progress`) foi introduzido depois
  do build de tokens e trouxe seu radius default.
- **Solução:** `$enable-rounded: false` + as cinco variáveis `$border-radius*: 0`
  em `_variables.scss`. Nunca corrigir com CSS de override por componente.

### 3.8 Fonte errada (Instrument Sans em vez de Archivo)

- **Sintoma:** texto renderiza numa sans genérica/diferente.
- **Causas:** `$font-family-base` não sobrescrito; o `@font-face` do Archivo ficou
  em `app.css` (removido) e não migrou para `resources/scss/_fonts.scss`; o
  plugin `bunny('Instrument Sans')` do `vite.config.js` ainda injeta a fonte
  errada no layout.
- **Solução:** `_fonts.scss` com os três `@font-face` (400/600/800) apontando para
  `/fonts/archivo/*.woff2`, e remoção do bloco `fonts:` do `vite.config.js`.

### 3.9 Cor errada / accent virou azul Bootstrap

- **Sintoma:** botão primário azul `#0d6efd`.
- **Causa:** `_variables.scss` importado **depois** de `bootstrap/scss/bootstrap`
  (as variáveis `!default` já resolveram), ou o import de `functions` faltando
  antes dos tokens (falha ao usar `tint-color()`/`shade-color()`).
- **Solução:** respeitar a ordem: `functions` → `variables` (nosso) →
  `bootstrap`. Ver `bootstrap-architecture`.

### 3.10 Dusk clicando no elemento errado depois da migração

- **Sintoma:** `click('@salvar')` acerta outro elemento, ou
  `assertSeeIn('@card-titulo', ...)` falha embora o texto esteja visível.
- **Causas:** o `dusk` migrou para um elemento de granularidade diferente (foi do
  `<button>` para o `<div>` que o envolve); ou o mesmo `dusk` passou a existir
  duas vezes porque o componente faz `merge` e a tela também escreveu o atributo.
- **Solução:** rodar o diff de `dusk=` do §2 passo 3; garantir que cada valor de
  `dusk` seja único por página.

---

## 4. Checklist de regressão por tela migrada

Marque tudo antes de considerar uma tela concluída:

- [ ] `grep 'style="'` no arquivo retorna vazio.
- [ ] Nenhuma classe fantasma (`btn-ghost`, `dialog`, `tag-*`, `elev-*`, `field`,
      `input` solto) e nenhuma classe Tailwind.
- [ ] `diff` dos `dusk="..."` antes/depois vazio (ou divergência justificada por
      escrito no receipt).
- [ ] Nenhum markup Bootstrap cru que deveria ser `<x-ui.*>`.
- [ ] `border-radius: 0` visualmente em botões, cards, inputs, modais, badges.
- [ ] Fonte Archivo; headings peso 800.
- [ ] Accent `#ec3013` nos elementos primários; fundo `#f3f2f2`; sidebar
      `#2d2b2b`.
- [ ] Imagens de pessoas/curso dentro de `.grayscale`; logo da organização com
      `.org-logo` (isenta).
- [ ] Formulários: cada campo com `<label for>`; erros via `.is-invalid` +
      `.invalid-feedback`; `dusk="error-{campo}"` presente.
- [ ] Modais: `aria-labelledby`, `.btn-close` com `aria-label="Fechar"`, foco
  volta ao gatilho ao fechar.
- [ ] Tabelas dentro de `.table-responsive`.
- [ ] Sem scroll horizontal em 375px de largura.
- [ ] `vendor/bin/sail npm run build && vendor/bin/sail artisan dusk --filter=<Teste>` verde.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo (se tocou PHP).
- [ ] `<x-help-button>` da tela continua presente e funcional (RF12/RN05).

## 5. Comandos de auditoria global (fim de fase)

```bash
# style= inline restantes (alvo: 0, exceto certificates/pdf.blade.php)
grep -rn 'style="' resources/views --include='*.blade.php' | grep -vc 'certificates/pdf'

# classes fantasma restantes
grep -rnE 'btn-ghost|btn-block|btn-icon|dialog-backdrop|tag-(accent|outline|neutral)|elev-(sm|md|lg)' resources/views

# resíduo Tailwind
grep -rnE 'class="[^"]*(\bflex\b|\bgrid\b|space-y-|text-(xs|sm|base|lg|xl)\b|bg-white|rounded-)' resources/views

# módulos JS artesanais que deveriam ter sumido
grep -rn 'ModalManager' resources/js resources/views

# contagem de dusk= (deve permanecer >= 316)
grep -ro 'dusk="' resources/views | wc -l

# suíte completa
vendor/bin/sail npm run build && vendor/bin/sail artisan dusk
vendor/bin/sail artisan test --compact
```
