# UX-001: Menu lateral do Admin mistura administração de sistema com operação de Organização

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO. A migração reescreveu apenas o *render* do menu (o drawer mobile virou `.offcanvas` do Bootstrap), não a sua composição: `NavigationRegistry` continua fixando `section: 'Administração'` para `courses`/`quiz-attempts`/`forum-moderation` e continua listando `'admin'` em `student-courses`. A correção segue inteiramente no service layer — o Blade não muda.

## 1. Executive Summary & Impact
- **ID:** UX-001
- **Severity:** High (bloqueia a compreensão do fluxo — o Admin não distingue o que é global do que é escopo de Organização)
- **Affected Role(s):** admin
- **Tenant Context:** Admin-global vs. Impersonate Org
- **Summary:** O Admin vê, em qualquer tela, um menu com 9 itens que misturam responsabilidades de administração de sistema (Organizações, Auditoria, Configurações) com operação do dia a dia de uma Organização (Cursos e Módulos, Redações Pendentes, Moderação do Fórum) e ainda a área do aluno (Meus Cursos). Sem Impersonate Org ativo, os itens operacionais não têm contexto de Organização e não fazem sentido. A demanda pede reduzir o menu padrão do Admin e reagrupar os itens operacionais numa subseção "Impersonate", visível somente quando o contexto de Organização está ativo.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Usuário com role `admin`, sem `org_id` próprio.
2. Nenhum Impersonate Org ativo (`session('active_org_id')` ausente).
3. Pelo menos uma Organização ativa cadastrada, para exercitar o segundo cenário.

### Reproduction Steps:
1. Fazer login como `admin`.
2. Observar o menu lateral (desktop e drawer mobile) em qualquer tela.
3. Acessar `/organizations` e clicar em "Entrar como" numa Organização.
4. Observar novamente o menu lateral.

### Expected Behavior (Happy Path):
**Sem Impersonate Org ativo** — seção "Administração":
- Dashboard
- Organizações
- Alunos & Usuários
- Auditoria
- Configurações

**Com Impersonate Org ativo** — a seção "Administração" acima permanece, e passa a existir adicionalmente uma seção **"Impersonate"** contendo:
- Cursos e Módulos
- Redações Pendentes
- Moderação do Fórum

As demais regras do SPEC-17 seguem valendo: badges preservados nos itens de Redações Pendentes e Moderação do Fórum, item ativo destacado, seções vazias nunca renderizadas, nenhum link morto.

### Actual Behavior (UX atual):
- Em **ambos** os contextos o Admin vê a seção "Administração" com Dashboard, Organizações, Alunos & Usuários, Cursos e Módulos, Redações Pendentes, Moderação do Fórum, Auditoria e Configurações — e a seção "Aprendizado" com Meus Cursos.
- Nada no menu sinaliza que os itens operacionais dependem de um contexto de Organização.

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** agnóstico — o menu é renderizado em todas as rotas que usam `layouts.app`.
- **Serviço de navegação (fonte única da verdade):**
  - `App\Services\Navigation\NavigationRegistry::items()` — `app/Services/Navigation/NavigationRegistry.php:33-150`
  - `App\Services\Navigation\NavigationRegistry::sectionOrder()` — `app/Services/Navigation/NavigationRegistry.php:155-158`, alimentado pela constante `SECTION_ORDER` em `:28` (`['Administração', 'Aprendizado']`)
  - `App\Services\Navigation\NavigationService::build()` / `resolve()` — `app/Services/Navigation/NavigationService.php:39-108`
  - `App\Services\Navigation\NavigationItem` — `app/Services/Navigation/NavigationItem.php`
  - `App\Services\Navigation\NavigationSection` — `app/Services/Navigation/NavigationSection.php`
- **View Composer:** `App\Http\View\Composers\NavigationComposer::compose()` — `app/Http/View/Composers/NavigationComposer.php:29-37` (injeta `$navigationSections`).
- **Blade View / Component (pós-Bootstrap 5.3):** `resources/views/components/layout/sidebar.blade.php`
  - desktop: `<aside class="sidebar d-none d-lg-flex">` — `:15-42`; item em `:28-38` com `dusk="sidebar-{{ $item['key'] }}-link"` (`:29`).
  - mobile: `<div class="offcanvas offcanvas-start d-lg-none text-bg-dark" id="mobile-sidebar">` — `:50-105`; item em `:90-100` com `dusk="sidebar-{{ $item['key'] }}-link-mobile"` (`:91`). O antigo drawer com diretivas Alpine mortas e backdrop manual **não existe mais**; o backdrop é gerado pelo `bootstrap.Offcanvas`.
  - o gatilho do drawer migrou para a topbar: `resources/views/components/layout/topbar.blade.php:19-32` (`data-bs-toggle="offcanvas"`, `data-bs-target="#mobile-sidebar"`, `dusk="mobile-menu-button"` preservado).
  - ambos os blocos são puramente declarativos (`@foreach($sidebarSections …)`) e **não devem** ganhar `@if`/checagens de role; toda a lógica pertence ao service layer.
- **Contexto de impersonate:** `session('active_org_id')`, escrito/limpo em `App\Http\Controllers\ImpersonateOrgController` — `app/Http/Controllers/ImpersonateOrgController.php:33` e `:60`
- **Spec de origem da arquitetura:** `spec/specs/17-dynamic-navigation-menu-and-access-control.md`

## 4. Root Cause Analysis (apresentação / composição do menu)
Não há defeito funcional: cada link do menu leva a uma tela que existe e responde. O problema é de **arquitetura de informação** — a `section` de cada item é uma string fixa no registry, decidida sem considerar o contexto de Organização ativo:

```php
// app/Services/Navigation/NavigationRegistry.php:68-76 (trecho, verificado em 2026-08-13)
new NavigationItem(
    key: 'courses',
    label: 'Cursos e Módulos',
    route: 'courses.index',
    activePatterns: ['courses.*', 'modules.*', 'lessons.*', 'quizzes.*'],
    icon: $this->bookIcon(),
    roles: self::ADMIN_GESTOR,
    section: 'Administração',       // ← fixo, sem noção de impersonate
),
```

O mesmo `section: 'Administração'` fixo está em `quiz-attempts` (`:84`) e `forum-moderation` (`:95`).

`NavigationService::resolve()` filtra por `roles`, `permissions` e disponibilidade de rota (`app/Services/Navigation/NavigationService.php:83-108`) — não existe nenhum gate contextual de sessão. Por isso os três itens operacionais aparecem para o Admin mesmo em contexto global.

Direção da correção (mantendo o service layer como fonte única, RN38/RN40):
1. Introduzir no `NavigationItem` a noção de seção contextual — por exemplo um `sectionResolver`/`?string $impersonateSection`, ou um gate declarativo `requiresImpersonation`/`sectionWhenImpersonating`.
2. Nos itens `courses`, `quiz-attempts` e `forum-moderation`, declarar que, **para o Admin**, pertencem à seção "Impersonate" e só são visíveis com `session('active_org_id')` presente. Para o **Gestor** nada muda: ele opera sempre na própria Organização e deve continuar vendo esses itens em "Administração".
3. Acrescentar `'Impersonate'` à constante `SECTION_ORDER` (`NavigationRegistry.php:28`), após `'Administração'`.
4. Manter o comportamento existente de seções vazias descartadas (`NavigationService::build()` — `app/Services/Navigation/NavigationService.php:67`), o que faz a seção "Impersonate" desaparecer sozinha quando o contexto é encerrado.
5. **Nenhuma alteração de markup.** `resources/views/components/layout/sidebar.blade.php` já itera `$navigationSections` genericamente nos dois blocos (desktop `:17-41` e offcanvas `:80-102`), e o título de seção usa a classe de projeto `.sidebar-section-title` com `pt-0`/`pt-3x` (chave `3x` = 12px do mapa `$spacers` de `resources/scss/app.scss:102-109`). Não há `style=`, classe Tailwind ou classe fantasma a introduzir aqui — a demanda não toca em CSS.

> Decisão do solicitante (confirmada): "Meus Cursos" **deve ser removido** do menu do Admin. A chave real do item é **`student-courses`** (não `my-courses`), declarada em `NavigationRegistry.php:123-131` com `roles: ['aluno', 'gestor', 'admin']` (`:129`). Remover `'admin'` dessa lista. A seção "Aprendizado" passa a ficar vazia para o Admin e é descartada automaticamente por `NavigationService::build()`.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/Navigation/NavigationServiceTest.php` (arquivo existente do SPEC-17 — acrescentar casos)
  - admin **sem** `active_org_id`: as seções compiladas contêm exatamente as chaves `dashboard, organizations, users, audit-logs, settings`; nenhuma seção "Impersonate" é emitida.
  - admin **com** `active_org_id`: existe uma seção `Impersonate` com exatamente `courses, quiz-attempts, forum-moderation`, e a seção `Administração` mantém as 5 chaves anteriores.
  - a seção `Impersonate` aparece **depois** de `Administração` na ordenação.
  - gestor: nenhuma seção "Impersonate"; `courses`, `quiz-attempts`, `forum-moderation` permanecem em "Administração" (não-regressão).
  - admin: nenhuma seção "Aprendizado" é emitida, com ou sem `active_org_id` (o item `student-courses` não é mais visível ao Admin).
  - gestor e aluno: a seção "Aprendizado" com `student-courses` continua sendo emitida (não-regressão).
  - aluno: nenhuma seção administrativa (não-regressão do RN38).
  - badges de `quiz-attempts` e `forum-moderation` continuam sendo resolvidos dentro da seção "Impersonate".
- **Browser test (Dusk):** `tests/Browser/Navigation/AdminSidebarScopeTest.php`
  - admin logado sem impersonate: `assertMissing('[dusk="sidebar-courses-link"]')`, `assertMissing('[dusk="sidebar-quiz-attempts-link"]')`, `assertMissing('[dusk="sidebar-forum-moderation-link"]')`, `assertSee('Organizações')`.
  - após "Entrar como" (`dusk="impersonate-{id}"`): `assertSee('Impersonate')` e `waitFor('[dusk="sidebar-courses-link"]')`.
  - após "Sair do contexto" (`dusk="exit-impersonation"`): a seção "Impersonate" e seus três links somem.
  - mesmas asserções no drawer mobile: clicar `@mobile-menu-button` (topbar `:19-32`) e aguardar o offcanvas do Bootstrap com `waitFor('#mobile-sidebar.show')` — **não** existe mais `.mobile-sidebar-backdrop` nem estado Alpine para esperar. Os seletores dos itens seguem `@sidebar-{key}-link-mobile` (`sidebar.blade.php:91`).

## 6. Acceptance Criteria for Fix Verification
- [ ] Admin sem Impersonate Org vê apenas Dashboard, Organizações, Alunos & Usuários, Auditoria e Configurações.
- [ ] Admin com Impersonate Org vê, além dos itens acima, uma seção "Impersonate" com Cursos e Módulos, Redações Pendentes e Moderação do Fórum.
- [ ] A seção "Impersonate" desaparece integralmente ao encerrar o contexto.
- [ ] "Meus Cursos" não é mais exibido para o Admin, e a seção "Aprendizado" não é renderizada para ele.
- [ ] O menu do Gestor e do Aluno permanece inalterado.
- [ ] Nenhuma checagem de role ou de sessão foi adicionada ao Blade — toda a lógica permanece em `NavigationRegistry`/`NavigationService`.
- [ ] Nenhum link morto (`#`) é emitido em qualquer contexto (RF36).
- [ ] Desktop e drawer mobile exibem exatamente as mesmas seções.
- [ ] `vendor/bin/sail artisan test --compact --filter=NavigationServiceTest` passa.
- [ ] `vendor/bin/sail artisan dusk --filter=AdminSidebarScopeTest` passa.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** FIXED
- **Reproduction Tests:**
  - `tests/Unit/NavigationServiceTest.php` (casos UX-001 acrescentados ao arquivo existente do SPEC-17 — a spec citava `tests/Feature/Navigation/NavigationServiceTest.php`, que não existe)
  - `tests/Feature/RoleMenuVisibilityTest.php`
  - `tests/Browser/Navigation/AdminSidebarScopeTest.php`
- **Fixed In Files:**
  - `app/Services/Navigation/NavigationRegistry.php`
  - `app/Services/Navigation/NavigationService.php`
  - `app/Services/Navigation/NavigationItem.php`
- **Desvio consciente do §5:** o conjunto esperado para o Admin sem Impersonate é `dashboard, organizations, audit-logs, settings` — **sem** `users`, que o BUG-005 (commit `58dc8b7`, posterior à redação desta demanda) já esconde no contexto global pelo mesmo motivo de escopo. Nenhum markup foi alterado.
