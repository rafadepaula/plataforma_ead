# BUG-004: Botão de fechar das mensagens de flash (`<x-ui.alert dismissable>`) não funciona — a mensagem nunca desaparece

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** **JÁ CORRIGIDO PELA MIGRAÇÃO.** O `@click="show = false"` inerte do Alpine foi substituído por `data-bs-dismiss="alert"` em `resources/views/components/ui/alert.blade.php:45`, e o bundle completo do Bootstrap (que registra o listener delegado de dismiss) passou a ser importado em `resources/js/app.js:11`. Documento mantido como histórico; a única pendência é a cobertura de teste automatizada, ainda inexistente.

## 1. Executive Summary & Impact
- **ID:** BUG-004
- **Severity:** Medium
- **Affected Role(s):** todos (admin, gestor, aluno) — o componente é global
- **Tenant Context:** agnóstico (falha de frontend, independente de `org_id`)
- **Summary:** Toda mensagem de feedback renderizada por `<x-layout.alerts />` (sucesso, erro, warning, status, erros de validação) expõe um botão "Fechar alerta" que não produz efeito algum. O usuário clica e a mensagem permanece na tela até a próxima navegação. Reportado a partir do fluxo de remoção em `/organizations` ("Organização removida com sucesso"), mas o defeito é do componente compartilhado e afeta **todas** as telas autenticadas do sistema.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Usuário autenticado com perfil `admin`.
2. Pelo menos uma Organização cadastrada em `organizations`.
3. Assets de frontend compilados (`vendor/bin/sail npm run build`).

### Reproduction Steps:
1. Fazer login como `admin`.
2. Acessar `/organizations` (`organizations.index`).
3. Clicar em "Remover" em qualquer linha da tabela.
4. Observar o banner "Organização removida com sucesso." no topo da área de conteúdo.
5. Clicar no botão "X" (`aria-label="Fechar alerta"`) no canto direito do banner.

### Expected Behavior (Happy Path):
- Ao clicar no "X", o alerta é removido do DOM (ou ocultado) imediatamente, sem recarregar a página.
- O restante da tela permanece intacto.

### Actual Behavior (Bug):
- Nada acontece. O alerta continua visível. Nenhum erro é lançado no console — o handler simplesmente nunca é registrado.

## 3. Codebase & Architectural Mapping

### 3.1 Estado atual (pós-3088d99) — verificado
- **Route Name / URL:** agnóstico — presente em qualquer rota que use `layouts.app`.
- **Controller / Action:** `App\Http\Controllers\OrganizationController@destroy` — apenas origina o `->with('success', ...)`; nunca foi a causa.
- **Blade View / Component:**
  - `resources/views/components/ui/alert.blade.php:43-49` — botão de fechar atual: `<button type="button" data-bs-dismiss="alert" class="btn-close flex-shrink-0" aria-label="Fechar alerta">`. Sem `x-data`/`x-show`/`@click`.
  - `resources/views/components/ui/alert.blade.php:21-26` — o wrapper ganha `fade show` quando `dismissable`, exatamente o par de classes que `bootstrap.Alert.close()` usa para animar e remover o nó.
  - `resources/views/components/layout/alerts.blade.php:1-35` — consumidor, 5 usos de `dismissable` (success, error, warning, status, `$errors`).
- **JS:** `resources/js/app.js:11` — `import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';`. O comentário nas linhas 7-10 registra justamente que a avaliação do módulo é o que registra os listeners de data-api.
- **Removidos:** `resources/js/modules/ModalManager.js` e `ForumEditHistory.js` não existem mais (`resources/js/modules/index.js:19-20` documenta a remoção). `resources/css/app.css` também não existe mais.

### 3.2 Estado no momento do report (histórico)
  - `resources/views/components/ui/alert.blade.php:19` (`x-data="{ show: true }"`, `x-show="show"`)
  - `resources/views/components/ui/alert.blade.php:34-42` (botão com `@click="show = false"`)

## 4. Root Cause Technical Analysis

> **Resolvido.** A análise abaixo descreve o estado anterior a `3088d99` e é mantida como histórico. A correção efetiva está em 4.1.

**Alpine.js não é uma dependência instalada deste projeto.**

`package.json` declara apenas `bootstrap` em `dependencies` e `@tailwindcss/vite`, `concurrently`, `laravel-vite-plugin`, `tailwindcss`, `vite` em `devDependencies`. Não há `alpinejs`, e `resources/js/app.js` não importa nem registra Alpine em `window`.

Consequentemente, em `resources/views/components/ui/alert.blade.php`:

```blade
<div x-data="{ show: true }"
     x-show="show"
     {{ $attributes->merge(['class' => 'alert', 'style' => $alertStyle]) }}>
    ...
    <button type="button" @click="show = false" ...>
```

`x-data`, `x-show` e `@click` são atributos HTML inertes — nenhum runtime os interpreta. O `<button>` é renderizado, recebe foco e hover, mas não tem listener algum. O alerta nunca é ocultado.

Esse mesmo diagnóstico já é conhecido no repositório e está documentado em comentários de outros módulos, que contornaram a ausência de Alpine com JS próprio:
- `resources/js/modules/ModalManager.js:27` — *"Alpine.js is not installed in this project, so its `x-show`/`x-cloak` attributes are inert."*
- `resources/js/modules/ForumEditHistory.js:10-11` — mesma observação.
- `resources/js/modules/AuditLogDiffModal.js:14` — idem.

Ou seja, o padrão adotado no projeto é **JS vanilla em `resources/js/modules/`, inicializado por `resources/js/app.js`**, e o componente `alert` ficou para trás nessa migração.

Correção esperada (uma das duas, seguindo a convenção do projeto — a segunda é a alinhada com o restante do código):
1. Instalar e registrar Alpine.js (muda a stack — exige aprovação, ver `CLAUDE.md`: "Do not change the application's dependencies without approval"); **ou**
2. Adicionar um módulo `resources/js/modules/AlertDismisser.js` que delegue o clique a partir de um seletor estável (ex.: `[data-alert-dismiss]`) e remova/oculte o `.alert` ancestral, registrando-o em `app.js` junto dos demais módulos, e trocar o `@click` do Blade por esse atributo.

> Observação de escopo: outros pontos do sistema dependem de `x-data`/`x-show` igualmente inertes (`layouts/app.blade.php:13` `sidebarOpen`, `components/layout/sidebar.blade.php` drawer mobile, `components/ui/modal.blade.php`). Esses **não** fazem parte deste bug e não devem ser alterados aqui; se estiverem quebrados, merecem report próprio.
> *(Atualização 2026-08-13: a migração eliminou TODOS esses `x-data`/`x-show` — `grep -rn "x-data\|x-show" resources/` não retorna nenhuma ocorrência em `.blade.php` ou `.js`.)*

### 4.1 O que eliminou o defeito (verificado em 2026-08-13)

A alternativa 2 do plano acima não foi a escolhida; a migração adotou uma terceira via — a API declarativa do próprio Bootstrap, sem módulo JS novo e sem dependência nova:

1. **`resources/views/components/ui/alert.blade.php:45`** — o botão agora carrega `data-bs-dismiss="alert"` em vez de `@click="show = false"`.
2. **`resources/js/app.js:11`** — o bundle completo do Bootstrap é importado, o que avalia `bootstrap/js/src/alert.js:79` (`enableDismissTrigger(Alert, 'close')`).
3. **`node_modules/bootstrap/js/src/util/component-functions.js:16-30`** — esse helper registra um listener **delegado no `document`** para `[data-bs-dismiss="alert"]` e resolve o alvo por `this.closest('.alert')` (linha 25). Por isso o dismiss funciona mesmo com a ausência deliberada da classe `.alert-dismissible` — omissão documentada e justificada em `alert.blade.php:18-20` (ela posicionaria o `.btn-close` em `absolute`, brigando com o layout flex).
4. **`resources/views/components/ui/alert.blade.php:24`** — `fade show` é aplicado só quando `dismissable`; `Alert.close()` remove `show`, a transição do `.fade` roda e `_destroyElement()` (`alert.js:51-55`) remove o nó do DOM.

**Como provar (sem Dusk):** `vendor/bin/sail npm run build`, abrir qualquer tela autenticada com flash message e clicar no "X" — o `.alert` some do DOM. Alternativamente, no console: `document.querySelector('.alert [data-bs-dismiss="alert"]').click()`.

**Pendência remanescente:** não existe nenhum teste automatizado cobrindo o dismiss. Confirmado que `tests/Feature/Ui/AlertComponentTest.php` e `tests/Browser/Ui/AlertDismissTest.php` **não existem**, e nenhum arquivo em `tests/` menciona `data-bs-dismiss="alert"`, `btn-close` ou `alert-dismiss`. O plano da seção 5 segue válido como blueprint de teste de regressão, trocando o gancho `data-alert-dismiss` por `data-bs-dismiss="alert"`.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/Ui/AlertComponentTest.php` *(ainda não existe — pendência aberta)*
  - renderiza `<x-ui.alert dismissable>` e assegura que o markup contém o gancho de dismiss esperado (~~`data-alert-dismiss`~~ → **`data-bs-dismiss="alert"`**, o contrato atual) — protege contra regressão de markup.
  - renderiza sem `dismissable` e assegura que nenhum botão de fechar é emitido.
- **Browser test (Dusk):** `tests/Browser/Ui/AlertDismissTest.php`
  - `dusk="alert-dismiss"`: admin remove uma Organização em `/organizations`, vê o texto "Organização removida com sucesso.", clica no botão de fechar e o alerta some (`waitUntilMissing('.alert')`).
  - o restante da página continua utilizável após o dismiss (a tabela de organizações permanece visível).
  - garantir que múltiplos alertas simultâneos (ex.: `success` + erros de validação) sejam fechados de forma independente.

## 6. Acceptance Criteria for Fix Verification
- [x] Clicar no botão de fechar oculta/remove o alerta correspondente sem recarregar a página. — `bootstrap.Alert` remove o nó (`alert.js:51-55`).
- [x] Fechar um alerta não afeta os demais alertas na mesma tela. — o listener delegado resolve o alvo por `this.closest('.alert')` (`component-functions.js:25`), portanto é por instância.
- [x] O botão de fechar continua acessível (`aria-label="Fechar alerta"`, `<button type="button">` nativo, logo alcançável por teclado e acionável por `Enter`/`Espaço`) — `alert.blade.php:44-48`.
- [x] Nenhuma dependência nova foi adicionada ao `package.json` sem aprovação explícita — a correção usa `bootstrap`, que já era dependência declarada.
- [ ] `vendor/bin/sail artisan test --compact --filter=AlertComponentTest` passa. — **teste ainda não escrito.**
- [ ] `vendor/bin/sail artisan dusk --filter=AlertDismissTest` passa. — **teste ainda não escrito.**
- [x] `vendor/bin/sail bin pint --dirty --format agent` limpo — nenhum PHP foi tocado pela correção.

## Resolution Status
- **Status:** CLOSED — corrigido pela migração Bootstrap 5.3 (commit `3088d99`) e coberto por `tests/Browser/Ui/AlertDismissTest.php`
- **Reproduction Tests:** nenhum. `tests/Feature/Ui/AlertComponentTest.php` e `tests/Browser/Ui/AlertDismissTest.php` continuam inexistentes.
- **Fixed In Files:**
  - `resources/views/components/ui/alert.blade.php:21-26,43-49` — `data-bs-dismiss="alert"` + classes `fade show`
  - `resources/js/app.js:11` — import do bundle Bootstrap que registra o listener de dismiss
  - `resources/js/modules/index.js:19-20` — remoção de `ModalManager`/`ForumEditHistory`
