# UX-001: Menu lateral do Admin mistura administração de sistema com operação de Organização

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
  - `App\Services\Navigation\NavigationRegistry::sectionOrder()` — `app/Services/Navigation/NavigationRegistry.php:28` (`['Administração', 'Aprendizado']`)
  - `App\Services\Navigation\NavigationService::build()` / `resolve()` — `app/Services/Navigation/NavigationService.php:39-108`
  - `App\Services\Navigation\NavigationItem` — `app/Services/Navigation/NavigationItem.php`
  - `App\Services\Navigation\NavigationSection` — `app/Services/Navigation/NavigationSection.php`
- **View Composer:** `App\Http\View\Composers\NavigationComposer`
- **Blade View / Component:** `resources/views/components/layout/sidebar.blade.php` (desktop `:18-45`, drawer mobile `:92-115`) — é puramente declarativo e **não deve** ganhar `@if`/checagens de role; toda a lógica pertence ao service layer.
- **Contexto de impersonate:** `session('active_org_id')`, escrito/limpo em `App\Http\Controllers\ImpersonateOrgController` — `app/Http/Controllers/ImpersonateOrgController.php:33` e `:60`
- **Spec de origem da arquitetura:** `spec/specs/17-dynamic-navigation-menu-and-access-control.md`

## 4. Root Cause Analysis (apresentação / composição do menu)
Não há defeito funcional: cada link do menu leva a uma tela que existe e responde. O problema é de **arquitetura de informação** — a `section` de cada item é uma string fixa no registry, decidida sem considerar o contexto de Organização ativo:

```php
// app/Services/Navigation/NavigationRegistry.php:68-98 (trecho)
new NavigationItem(
    key: 'courses',
    label: 'Cursos e Módulos',
    route: 'courses.index',
    roles: self::ADMIN_GESTOR,
    section: 'Administração',       // ← fixo, sem noção de impersonate
),
```

`NavigationService::resolve()` filtra por `roles`, `permissions` e disponibilidade de rota (`app/Services/Navigation/NavigationService.php:83-108`) — não existe nenhum gate contextual de sessão. Por isso os três itens operacionais aparecem para o Admin mesmo em contexto global.

Direção da correção (mantendo o service layer como fonte única, RN38/RN40):
1. Introduzir no `NavigationItem` a noção de seção contextual — por exemplo um `sectionResolver`/`?string $impersonateSection`, ou um gate declarativo `requiresImpersonation`/`sectionWhenImpersonating`.
2. Nos itens `courses`, `quiz-attempts` e `forum-moderation`, declarar que, **para o Admin**, pertencem à seção "Impersonate" e só são visíveis com `session('active_org_id')` presente. Para o **Gestor** nada muda: ele opera sempre na própria Organização e deve continuar vendo esses itens em "Administração".
3. Acrescentar `'Impersonate'` a `sectionOrder()`, após `'Administração'`.
4. Manter o comportamento existente de seções vazias descartadas (`NavigationService::build()` — `app/Services/Navigation/NavigationService.php:67`), o que faz a seção "Impersonate" desaparecer sozinha quando o contexto é encerrado.
5. `resources/views/components/layout/sidebar.blade.php` não precisa de alteração alguma — ele já itera `$navigationSections` genericamente.

> Decisão do solicitante (confirmada): "Meus Cursos" **deve ser removido** do menu do Admin. Hoje ele está na seção "Aprendizado" (`NavigationRegistry.php:123-131`, `roles: ['aluno','gestor','admin']`). Remover `'admin'` dessa lista de roles. A seção "Aprendizado" passa a ficar vazia para o Admin e é descartada automaticamente por `NavigationService::build()`.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/Navigation/NavigationServiceTest.php` (arquivo existente do SPEC-17 — acrescentar casos)
  - admin **sem** `active_org_id`: as seções compiladas contêm exatamente as chaves `dashboard, organizations, users, audit-logs, settings`; nenhuma seção "Impersonate" é emitida.
  - admin **com** `active_org_id`: existe uma seção `Impersonate` com exatamente `courses, quiz-attempts, forum-moderation`, e a seção `Administração` mantém as 5 chaves anteriores.
  - a seção `Impersonate` aparece **depois** de `Administração` na ordenação.
  - gestor: nenhuma seção "Impersonate"; `courses`, `quiz-attempts`, `forum-moderation` permanecem em "Administração" (não-regressão).
  - admin: nenhuma seção "Aprendizado" é emitida, com ou sem `active_org_id` (o item `my-courses` não é mais visível ao Admin).
  - gestor e aluno: a seção "Aprendizado" com `my-courses` continua sendo emitida (não-regressão).
  - aluno: nenhuma seção administrativa (não-regressão do RN38).
  - badges de `quiz-attempts` e `forum-moderation` continuam sendo resolvidos dentro da seção "Impersonate".
- **Browser test (Dusk):** `tests/Browser/Navigation/AdminSidebarScopeTest.php`
  - admin logado sem impersonate: `assertMissing('[dusk="sidebar-courses-link"]')`, `assertMissing('[dusk="sidebar-quiz-attempts-link"]')`, `assertMissing('[dusk="sidebar-forum-moderation-link"]')`, `assertSee('Organizações')`.
  - após "Entrar como" (`dusk="impersonate-{id}"`): `assertSee('Impersonate')` e `waitFor('[dusk="sidebar-courses-link"]')`.
  - após "Sair do contexto" (`dusk="exit-impersonation"`): a seção "Impersonate" e seus três links somem.
  - mesmas asserções no drawer mobile via `dusk="mobile-menu-button"` e os sufixos `-link-mobile`.

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
- **Status:** OPEN
- **Reproduction Tests:** —
- **Fixed In Files:** —
