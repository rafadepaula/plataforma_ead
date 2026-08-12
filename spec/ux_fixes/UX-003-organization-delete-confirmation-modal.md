# UX-003: Remoção de Organização é executada imediatamente, sem modal de confirmação

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
- **Blade View / Component:**
  - `resources/views/organizations/index.blade.php:44-48` — o `<form>` de remoção que hoje submete direto.
  - `resources/views/components/ui/modal.blade.php` — componente de modal existente, a reutilizar.
  - `resources/views/components/layout/alerts.blade.php` — onde a mensagem de sucesso aparece.
- **JS:** `resources/js/modules/ModalManager.js` (`open()`, `close()`, contrato `[data-modal-target]` / `[data-modal-dismiss]`), registrado em `resources/js/app.js:18` como `window.ModalManager`.
- **Model:** `App\Models\Organization`

## 4. Root Cause Analysis (apresentação / markup)
Não há falha de backend: `OrganizationController@destroy` autoriza, faz soft delete e redireciona com flash — corretamente. O que falta é uma etapa de confirmação na camada de apresentação:

```blade
{{-- resources/views/organizations/index.blade.php:44-48 --}}
<form method="POST" action="{{ route('organizations.destroy', $organization) }}" dusk="delete-form-{{ $organization->id }}">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-ghost" dusk="delete-organization-{{ $organization->id }}">Remover</button>
</form>
```

O `<button type="submit">` dispara o `DELETE` no primeiro clique. Não há `confirm()`, não há modal, não há segundo passo.

A infraestrutura necessária já existe e deve ser reaproveitada, sem novos componentes:
- `resources/views/components/ui/modal.blade.php` renderiza o backdrop **fechado** por padrão (`style="display: none"` inline) — importante, porque Alpine.js **não** está instalado neste projeto e os atributos `x-data`/`x-show`/`@click` do componente são inertes (ver comentário em `resources/js/modules/ModalManager.js:27`).
- `ModalManager` já delega cliques globalmente: `[data-modal-target="<id>"]` abre e `[data-modal-dismiss]` fecha (`resources/js/modules/ModalManager.js`, `bindGlobalEvents()`), e o botão de fechar do próprio `x-ui.modal` já carrega `data-modal-dismiss="true"`.

Direção da correção:
1. Trocar o `type="submit"` do botão da linha por um gatilho `type="button"` com `data-modal-target` apontando para o id do modal daquela Organização.
2. Renderizar um `<x-ui.modal>` por linha (ou um único modal parametrizado por JS, se a listagem crescer) contendo o nome da Organização e, no slot `actions`, o `<form>` `DELETE` real com o botão "Remover" e um botão "Cancelar" com `data-modal-dismiss`.
3. Cancelar não deve emitir flash message nem navegar — apenas fechar o modal (comportamento já entregue por `ModalManager.close()`).
4. Sobre "remover a organização da tabela": o fluxo atual é full-page redirect, e após o `destroy()` a listagem é recarregada já sem a linha — o critério é satisfeito sem introduzir AJAX. Uma remoção otimista via JS não é necessária e não deve ser implementada aqui.
5. Não alterar `OrganizationController@destroy` — o soft delete e o `->with('success', ...)` permanecem como estão.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/OrganizationCrudTest.php` (arquivo existente — acrescentar casos)
  - `GET /organizations` renderiza, para cada Organização listada, o gatilho de modal e o formulário `DELETE` correspondente.
  - `DELETE /organizations/{id}` continua removendo (soft delete) e redirecionando com a flash `success` (não-regressão).
  - gestor/aluno recebem 403 em `organizations.destroy` (não-regressão do `role:admin`).
- **Browser test (Dusk):** `tests/Browser/OrganizationDeleteTest.php`
  - `dusk="delete-organization-{id}"`: clicar abre o modal (`waitFor('[dusk="delete-organization-modal-{id}"]')`) e a Organização **ainda existe** no banco.
  - `dusk="cancel-delete-organization-{id}"`: clicar fecha o modal, nenhuma mensagem de sucesso é exibida (`assertDontSee('Organização removida com sucesso')`) e `dusk="organization-row-{id}"` continua na tabela.
  - `dusk="confirm-delete-organization-{id}"`: clicar remove a Organização, exibe "Organização removida com sucesso." e `assertMissing('[dusk="organization-row-{id}"]')`.
  - o modal nunca aparece no carregamento inicial da página (`assertMissing` do backdrop antes de qualquer clique) — ver `spec/bugs/BUG-003-modal-state-always-open.md`.

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
