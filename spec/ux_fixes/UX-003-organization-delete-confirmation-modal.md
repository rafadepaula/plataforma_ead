# UX-003: Remoção de Organização é executada imediatamente, sem modal de confirmação

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO, com o trabalho redefinido. Os componentes `<x-ui.confirm-modal>` e `<x-ui.delete-button>` **já existem** e já são usados em 4 outras telas, mas `organizations/index.blade.php` deliberadamente **não** os usa — o submit segue direto, por decisão registrada em comentário no próprio Blade (`:46-49`), para não quebrar `OrganizationCrudTest`. A correção deixou de ser "construir o modal" e passou a ser "adotar o componente existente **e** atualizar o contrato do teste Dusk".

## 1. Executive Summary & Impact
- **ID:** UX-003
- **Severity:** High (ação destrutiva sem confirmação, a um clique de distância)
- **Affected Role(s):** admin
- **Tenant Context:** Admin-global
- **Summary:** Em `/organizations`, o botão "Remover" submete o formulário `DELETE` imediatamente. Não há confirmação, não há como voltar atrás, e a Organização — junto de todos os seus usuários, cursos e certificados — sai do ar por um clique acidental. A demanda pede um modal de confirmação: confirmar executa a remoção e mostra o feedback de sucesso; cancelar simplesmente fecha o modal, sem qualquer efeito ou mensagem.

> Correlato: o botão de fechar da mensagem de sucesso está quebrado. Isso é um defeito separado, do componente global de alerta, e está reportado em `spec/bugs/BUG-004-alert-dismiss-button-inert.md`.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Usuário com role `admin`.
2. Pelo menos uma Organização cadastrada.

### Reproduction Steps:
1. Fazer login como `admin`.
2. Acessar `/organizations` (`organizations.index`).
3. Clicar em "Remover" em qualquer linha da tabela.

### Expected Behavior (Happy Path):
- O clique em "Remover" abre um modal de confirmação identificando a Organização pelo nome e alertando sobre o impacto.
- **Confirmar:** a remoção é executada, a linha deixa de constar na tabela e a mensagem "Organização removida com sucesso." é exibida.
- **Cancelar:** o modal fecha, a Organização permanece, nenhuma mensagem de feedback é exibida e a tabela fica intacta.

### Actual Behavior (UX atual):
- O clique remove a Organização na hora (soft delete), com um `POST`/`DELETE` direto e um redirect para a mesma listagem.
- Único sinal posterior é o banner de sucesso, já com o ato consumado.

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** `organizations.index` (`GET /organizations`) e `organizations.destroy` (`DELETE /organizations/{organization}`) — `routes/web.php:45` (`Route::resource('organizations', ...)->except(['show'])`, sob `middleware(['auth','role:admin'])` em `routes/web.php:44`).
- **Controller / Action:** `App\Http\Controllers\OrganizationController@destroy` — `app/Http/Controllers/OrganizationController.php:79-90` (soft delete deliberado: `users.org_id` é `ON DELETE RESTRICT`).
- **Policy / Auth Gate:** `App\Policies\OrganizationPolicy@delete` (via `Gate::authorize('delete', $organization)`).
- **Blade View / Component (pós-Bootstrap 5.3):**
  - `resources/views/organizations/index.blade.php:46-54` — o comentário da decisão + o `<form>` de remoção que hoje submete direto (o botão em `:53` já é `<x-ui.button type="submit" variant="ghost" size="sm" class="text-danger link-danger" dusk="delete-organization-{id}">`).
  - `resources/views/components/ui/delete-button.blade.php` — **existe**: emite o par botão + modal irmãos, com `data-bs-toggle="modal"` / `data-bs-target="#{$modalId}"` (`:36-40`) e delega ao `<x-ui.confirm-modal>` (`:42-48`). O `$attributes->merge()` cai no **botão** (`:40`), então o `dusk="delete-organization-{id}"` da tela continua chegando no lugar certo.
  - `resources/views/components/ui/confirm-modal.blade.php` — **existe**: `.modal fade` puro, sem `.show` e sem `style=` (nasce fechado; ver o comentário em `:1-11`), com o `<form>` `POST` + `@method('DELETE')` embutido (`:58-67`) e os seletores `dusk="confirm-modal-{id}"` (`:29`), `-close` (`:42`), `-cancel` (`:56`), `-confirm` (`:66`).
  - `resources/views/components/layout/alerts.blade.php` — onde a mensagem de sucesso aparece.
  - Precedentes de adoção já em produção: `resources/views/users/index.blade.php`, `certificates/index.blade.php`, `courses/invitation-links/index.blade.php`, `courses/enrollments/index.blade.php`.
- **JS: nenhum.** `resources/js/modules/ModalManager.js` **foi deletado** na migração; abertura/fechamento são 100% declarativos via `bootstrap.Modal` (`data-bs-toggle` / `data-bs-dismiss`). Não escrever uma linha de JS para esta correção.
- **Teste que trava a adoção:** `tests/Browser/OrganizationCrudTest.php:56-72` — `test_admin_can_soft_delete_an_organization_via_the_ui()`.
- **Model:** `App\Models\Organization`

## 4. Root Cause Analysis (apresentação / markup)
Não há falha de backend: `OrganizationController@destroy` autoriza, faz soft delete e redireciona com flash — corretamente. O que falta é uma etapa de confirmação na camada de apresentação. O estado atual, verificado em 2026-08-13:

```blade
{{-- resources/views/organizations/index.blade.php:46-54 --}}
{{-- Submit direto, sem `<x-ui.delete-button>`: `OrganizationCrudTest`
     clica `@delete-organization-{id}` e espera o redirect imediato.
     Adotar o modal de confirmação aqui é mudança de contrato de
     teste, agendada para a Fase 7 junto com as demais exclusões. --}}
<form method="POST" action="{{ route('organizations.destroy', $organization) }}" dusk="delete-form-{{ $organization->id }}">
    @csrf
    @method('DELETE')
    <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger link-danger" dusk="delete-organization-{{ $organization->id }}">Remover</x-ui.button>
</form>
```

Ou seja: a causa raiz mudou de natureza. **Não falta mais infraestrutura** — falta *adotá-la*, e a barreira declarada é o contrato do teste Dusk. O `<button type="submit">` dispara o `DELETE` no primeiro clique; não há `confirm()`, não há modal, não há segundo passo.

A infraestrutura pronta, a ser reaproveitada sem criar nenhum componente novo:
- `<x-ui.delete-button>` (`resources/views/components/ui/delete-button.blade.php`) emite botão + `<x-ui.confirm-modal>` como **irmãos**, com o gatilho declarativo `data-bs-toggle="modal"` / `data-bs-target="#{$modalId}"` (`:36-40`). Props: `action` (obrigatória), `label`, `id`, `title`, `message`, `size`, `confirmLabel`.
- `<x-ui.confirm-modal>` (`resources/views/components/ui/confirm-modal.blade.php`) já embute o `<form method="POST">` + `@csrf` + `@method('DELETE')` (`:58-67`) e nasce fechado — `.modal fade` sem `.show` e **sem `style=`** (`:29-33`), o que fecha de uma vez o risco descrito em `spec/bugs/BUG-003-modal-state-always-open.md`.
- Sem `id` explícito, o `$modalId` é derivado de `sha1($action)` (`delete-button.blade.php:31`), o que gera um id estável mas ilegível. Para ter seletores Dusk previsíveis, **passar `id` explicitamente**.

Direção da correção:
1. Substituir o `<form>` de `index.blade.php:50-54` (e o comentário de `:46-49`) por uma única chamada:

```blade
<x-ui.delete-button
    id="confirm-delete-organization-{{ $organization->id }}"
    :action="route('organizations.destroy', $organization)"
    title="Remover Organização"
    :message="'Remover a Organização \"'.$organization->name.'\" também tira do ar seus usuários, cursos e certificados. Esta ação não poderá ser desfeita.'"
    dusk="delete-organization-{{ $organization->id }}" />
```

   O `dusk="delete-organization-{id}"` pousa no **botão** (via `$attributes->merge()` em `delete-button.blade.php:40`), preservando o seletor. Os seletores do modal passam a ser `@confirm-modal-confirm-delete-organization-{id}`, `…-cancel`, `…-close`. Nenhuma classe nova, nenhum `style=`, nenhum JS.
2. O `dusk="delete-form-{{ $organization->id }}"` do `<form>` externo **desaparece**: o form real agora vive dentro do `<x-ui.confirm-modal>` e não recebe `dusk`. Verificar com `grep -rn 'delete-form-' tests/` antes de remover; hoje nenhum teste o usa.
3. Cancelar não emite flash message nem navega — apenas fecha o modal, comportamento entregue pelo `data-bs-dismiss="modal"` do `<x-ui.button variant="ghost">` em `confirm-modal.blade.php:54-56`.
4. **Atualizar o contrato do teste Dusk** — `tests/Browser/OrganizationCrudTest.php`, método `test_admin_can_soft_delete_an_organization_via_the_ui()` (`:56-72`). As linhas `:65-68` hoje são:

```php
->waitFor('@delete-organization-'.$organization->id)
->click('@delete-organization-'.$organization->id)
->waitForLocation('/organizations')
->assertDontSee('Organização Removível');
```

   Devem passar a abrir o modal e só então confirmar:

```php
->waitFor('@delete-organization-'.$organization->id)
->click('@delete-organization-'.$organization->id)
->waitFor('#confirm-delete-organization-'.$organization->id.'.show')
->click('@confirm-modal-confirm-delete-organization-'.$organization->id.'-confirm')
->waitForLocation('/organizations')
->assertDontSee('Organização Removível');
```

   O `.show` é o estado que o `bootstrap.Modal` aplica ao abrir — esperar por ele (e não só pela presença do `.modal`) evita clicar num diálogo ainda em transição. O `assertSoftDeleted($organization)` de `:71` permanece.
5. Acrescentar ao mesmo arquivo um caso de **cancelamento**, hoje inexistente: clicar em "Remover", clicar em `@confirm-modal-…-cancel`, aguardar o fechamento e asseverar `assertNotSoftDeleted($organization)` + `@organization-row-{id}` ainda presente.
6. Sobre "remover a organização da tabela": o fluxo é full-page redirect e, após o `destroy()`, a listagem recarrega já sem a linha — o critério é satisfeito sem AJAX. Remoção otimista via JS não deve ser implementada aqui.
7. Não alterar `OrganizationController@destroy` — o soft delete e o `->with('success', ...)` permanecem como estão.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/OrganizationCrudTest.php` (arquivo existente — acrescentar casos)
  - `GET /organizations` renderiza, para cada Organização listada, o gatilho de modal e o formulário `DELETE` correspondente.
  - `DELETE /organizations/{id}` continua removendo (soft delete) e redirecionando com a flash `success` (não-regressão).
  - gestor/aluno recebem 403 em `organizations.destroy` (não-regressão do `role:admin`).
- **Browser test (Dusk):** `tests/Browser/OrganizationCrudTest.php` — **arquivo existente, contrato a alterar** (não criar um `OrganizationDeleteTest.php` novo; o fluxo de exclusão já é coberto ali).
  - `test_admin_can_soft_delete_an_organization_via_the_ui()` (`:56-72`): reescrever `:65-68` para abrir o modal antes de confirmar (snippet na §4.4). `assertSoftDeleted` preservado.
  - **novo** `test_admin_can_cancel_the_organization_deletion()`: clicar `@delete-organization-{id}`, aguardar `#confirm-delete-organization-{id}.show`, clicar `@confirm-modal-confirm-delete-organization-{id}-cancel`, `waitUntilMissing('.modal-backdrop')`, então `assertVisible('@organization-row-{id}')`, `assertDontSee('Organização removida com sucesso')` e `$this->assertNotSoftDeleted($organization)`.
  - **novo** o modal nunca aparece no carregamento inicial: `assertMissing('.modal.show')` e `assertMissing('.modal-backdrop')` antes de qualquer clique — ver `spec/bugs/BUG-003-modal-state-always-open.md`. Esta asserção é barata porque `<x-ui.confirm-modal>` não emite `.show` nem `style=` (`confirm-modal.blade.php:29-33`).

## 6. Acceptance Criteria for Fix Verification
- [ ] Clicar em "Remover" abre um modal de confirmação e **não** remove nada.
- [ ] O modal identifica a Organização pelo nome.
- [ ] Confirmar remove a Organização, exibe a mensagem de sucesso e a linha some da tabela.
- [ ] Cancelar fecha o modal sem remover, sem mensagem e sem navegação.
- [ ] O modal permanece fechado no carregamento inicial da tela.
- [ ] Nenhum componente de UI novo foi criado — `<x-ui.modal>` e `ModalManager` foram reutilizados.
- [ ] Nenhuma dependência nova foi adicionada ao `package.json`.
- [ ] `OrganizationController@destroy` permanece inalterado (soft delete preservado).
- [ ] `vendor/bin/sail artisan test --compact --filter=OrganizationCrudTest` passa.
- [ ] `vendor/bin/sail artisan dusk --filter=OrganizationDeleteTest` passa.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** OPEN
- **Reproduction Tests:** —
- **Fixed In Files:** —
