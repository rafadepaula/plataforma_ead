---
name: bootstrap-maintenance
description: >
  Debug, test, edge-case guide for Bootstrap 5.3 frontend of Plataforma
  EAD: stale `public/build/` gotcha that silently breaks Dusk, per-screen
  verify loop (`vendor/bin/sail npm run build` then screen's Dusk
  filter), recurring failure modes — `data-bs-toggle` inert because JS
  bundle never imported, stacked modal backdrops, dropdowns dead without
  Popper, utility class losing to leftover inline `style=`, missing
  `.table-responsive` causing horizontal overflow, dompdf choking on
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
verificação. `npm run dev` (HMR) rodando em outro terminal **não**
substitui build: Dusk não usa dev server a menos que `APP_URL` aponte
para ele e hot file exista. Na dúvida, rode build.

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
     `overflow: hidden` (`.grayscale` faz `filter`!) — cria containing
     block, quebra `position: fixed`. **Modais devem ser irmãos do
     conteúdo, idealmente no fim do `<body>` do layout**, nunca dentro de
     card com filtro.
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

### 3.5 Tabela estourando na horizontal (falta `.table-responsive`)

- **Sintoma:** scroll horizontal na página inteira no mobile; Dusk com
  viewport estreito falha ao clicar botão da última coluna ("element not
  interactable"/"outside of viewport").
- **Causa:** `<table class="table">` sem wrapper.
- **Solução:** sempre
  ```blade
  <div class="table-responsive">
      <table class="table align-middle">…</table>
  </div>
  ```
  `<x-ui.table>` já emite wrapper — erro só aparece quando alguém escreve
  `<table>` cru numa tela (proibido, ver `bootstrap-conventions` §3.12).
- Sintoma correlato: dropdown de ações da última coluna cortado pelo
  `overflow: auto` do `.table-responsive`. Solução: `data-bs-strategy="fixed"`
  no gatilho, ou mover ação para modal.

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

### 3.7 Radius voltando "sozinho"

- **Sintoma:** `LayoutRenderingTest` falha com `Expected border-radius to enforce 0px`.
- **Causas:** `$enable-rounded` não está `false`; alguém usou `rounded`,
  `rounded-pill` ou `rounded-circle`; componente novo do Bootstrap
  (`.form-control`, `.dropdown-menu`, `.toast`, `.progress`) entrou depois
  do build de tokens e trouxe radius default.
- **Solução:** `$enable-rounded: false` + as cinco variáveis
  `$border-radius*: 0` em `_variables.scss`. Nunca corrigir com CSS de
  override por componente.

### 3.8 Fonte errada (Instrument Sans em vez de Archivo)

- **Sintoma:** texto renderiza em sans genérica/diferente.
- **Causas:** `$font-family-base` não sobrescrito; `@font-face` do Archivo
  ficou em `app.css` (removido) e não migrou para
  `resources/scss/_fonts.scss`; plugin `bunny('Instrument Sans')` do
  `vite.config.js` ainda injeta fonte errada no layout.
- **Solução:** `_fonts.scss` com os três `@font-face` (400/600/800)
  apontando para `/fonts/archivo/*.woff2`, e remover bloco `fonts:` do
  `vite.config.js`.

### 3.9 Cor errada / accent virou azul Bootstrap

- **Sintoma:** botão primário azul `#0d6efd`.
- **Causa:** `_variables.scss` importado **depois** de
  `bootstrap/scss/bootstrap` (variáveis `!default` já resolveram), ou
  import de `functions` faltando antes dos tokens (falha ao usar
  `tint-color()`/`shade-color()`).
- **Solução:** respeitar ordem: `functions`, `variables` (nosso),
  `bootstrap`. Ver `bootstrap-architecture`.

### 3.10 Dusk clicando no elemento errado depois da migração

- **Sintoma:** `click('@salvar')` acerta outro elemento, ou
  `assertSeeIn('@card-titulo', ...)` falha embora texto esteja visível.
- **Causas:** `dusk` migrou para elemento de granularidade diferente (foi
  do `<button>` para o `<div>` que envolve); ou mesmo `dusk` passou a
  existir duas vezes porque componente faz `merge` e tela também escreveu
  atributo.
- **Solução:** rodar diff de `dusk=` do §2 passo 3; garantir cada valor de
  `dusk` único por página.

---

## 4. Checklist de regressão por tela migrada

Marque tudo antes de considerar tela concluída:

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
