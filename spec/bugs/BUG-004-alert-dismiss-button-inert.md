# BUG-004: Botão de fechar das mensagens de flash (`<x-ui.alert dismissable>`) não funciona — a mensagem nunca desaparece

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
- **Route Name / URL:** agnóstico — reproduzível em `organizations.index` (`/organizations`), mas presente em qualquer rota que use `layouts.app`.
- **Controller / Action:** `App\Http\Controllers\OrganizationController@destroy` (`app/Http/Controllers/OrganizationController.php:79`) — apenas origina o `->with('success', ...)`; o controller **não** é a causa.
- **Blade View / Component:**
  - `resources/views/components/ui/alert.blade.php:19` (`x-data="{ show: true }"`, `x-show="show"`)
  - `resources/views/components/ui/alert.blade.php:34-42` (botão com `@click="show = false"`)
  - `resources/views/components/layout/alerts.blade.php` (consumidor: 5 usos de `dismissable`)
  - `resources/views/layouts/app.blade.php:21` (`<x-layout.alerts />`)
- **Related Models / Services:** `resources/js/app.js`, `resources/js/modules/ModalManager.js`, `package.json`

## 4. Root Cause Technical Analysis
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

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/Ui/AlertComponentTest.php`
  - renderiza `<x-ui.alert dismissable>` e assegura que o markup contém o gancho de dismiss esperado (`data-alert-dismiss`) — protege contra regressão de markup.
  - renderiza sem `dismissable` e assegura que nenhum botão de fechar é emitido.
- **Browser test (Dusk):** `tests/Browser/Ui/AlertDismissTest.php`
  - `dusk="alert-dismiss"`: admin remove uma Organização em `/organizations`, vê o texto "Organização removida com sucesso.", clica no botão de fechar e o alerta some (`waitUntilMissing('.alert')`).
  - o restante da página continua utilizável após o dismiss (a tabela de organizações permanece visível).
  - garantir que múltiplos alertas simultâneos (ex.: `success` + erros de validação) sejam fechados de forma independente.

## 6. Acceptance Criteria for Fix Verification
- [ ] Clicar no botão de fechar oculta/remove o alerta correspondente sem recarregar a página.
- [ ] Fechar um alerta não afeta os demais alertas na mesma tela.
- [ ] O botão de fechar continua acessível (`aria-label="Fechar alerta"`, alcançável por teclado, acionável por `Enter`/`Espaço`).
- [ ] Nenhuma dependência nova foi adicionada ao `package.json` sem aprovação explícita.
- [ ] `vendor/bin/sail artisan test --compact --filter=AlertComponentTest` passa.
- [ ] `vendor/bin/sail artisan dusk --filter=AlertDismissTest` passa.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** OPEN
- **Reproduction Tests:** —
- **Fixed In Files:** —
