# UX-004: Edição de Organização mostra o caminho do arquivo do logo em vez de renderizar a imagem

## 1. Executive Summary & Impact
- **ID:** UX-004
- **Severity:** Medium (o Admin não consegue verificar visualmente qual logo está em uso antes de substituí-lo)
- **Affected Role(s):** admin
- **Tenant Context:** Admin-global
- **Summary:** Em `/organizations/{id}/edit`, o campo de logo exibe a legenda textual `Logo atual: organizations/logos/xYz123.png` — o caminho cru do arquivo em disco. A imagem não é renderizada. O Admin não tem como conferir o logo vigente e o texto exposto é ruído técnico sem valor para o usuário.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Usuário com role `admin`.
2. Uma Organização com `logo_path` preenchido (logo já enviado anteriormente).
3. `vendor/bin/sail artisan storage:link` executado (symlink `public/storage` → `storage/app/public`).

### Reproduction Steps:
1. Fazer login como `admin`.
2. Acessar `/organizations` e clicar em "Editar" na Organização que possui logo.
3. Rolar até o campo "Logo".

### Expected Behavior (Happy Path):
- Abaixo do campo de upload, o logo atual da Organização é exibido como imagem, em dimensão contida (thumbnail), com `alt` descritivo.
- Organizações sem logo não exibem nem imagem nem legenda de caminho — no máximo um estado vazio discreto.

### Actual Behavior (UX atual):
- É exibida a string `Logo atual: {caminho relativo do arquivo}` em cinza, 12px.
- Nenhuma imagem é renderizada.

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** `organizations.edit` (`GET /organizations/{organization}/edit`) — `routes/web.php:45`, sob `middleware(['auth','role:admin'])`.
- **Controller / Action:** `App\Http\Controllers\OrganizationController@edit` — `app/Http/Controllers/OrganizationController.php:54-59` (passa a instância completa de `Organization`; nenhuma alteração necessária).
- **Blade View / Component:**
  - `resources/views/organizations/_form.blade.php:36-46` — bloco do campo de logo (compartilhado por `create` e `edit`).
  - `resources/views/organizations/edit.blade.php`
  - `resources/views/organizations/create.blade.php`
- **Model / Coluna:** `App\Models\Organization`, coluna `logo_path`.
- **Upload / Storage:** `OrganizationController@store` (`app/Http/Controllers/OrganizationController.php:43-46`) e `@update` (`:65-70`) gravam com `->store('organizations/logos', 'public')` — disco `public`.
- **Serviço relacionado:** `App\Services\FileUploadService`.

## 4. Root Cause Analysis (apresentação / markup)
O upload funciona: o arquivo é gravado no disco `public` e o caminho relativo persiste em `organizations.logo_path`. O defeito é só de renderização — o Blade imprime a string em vez de resolver a URL pública:

```blade
{{-- resources/views/organizations/_form.blade.php:40-42 --}}
@if($organization->logo_path)
    <span style="font-size: 12px; color: var(--color-neutral-600);">Logo atual: {{ $organization->logo_path }}</span>
@endif
```

Como o arquivo vive no disco `public`, a URL navegável é obtida por `Storage::disk('public')->url($organization->logo_path)` (ou `asset('storage/'.$organization->logo_path)`), e não pelo valor cru da coluna. A correção é trocar o `<span>` por um `<img>` com `src` resolvido dessa forma, mantendo o `@if` de guarda para Organizações sem logo.

Pontos de atenção:
- O bloco vive em `_form.blade.php`, compartilhado com a tela de criação. Em `create`, `$organization` é uma instância nova (`new Organization`, `app/Http/Controllers/OrganizationController.php:35`) com `logo_path` nulo, então o `@if` já cobre o caso — a mudança não deve introduzir imagem quebrada em `create`.
- A renderização depende de `storage:link`. Se o symlink não existir no ambiente, a imagem quebra — o `<img>` deve ter `alt` útil para degradar de forma legível.
- Convenções do Modernist Design System: dimensão contida (ex.: `max-height` de thumbnail), `border-radius: 0px`, sem sombra, coerente com `resources/views/components/ui/*`.
- Não criar componente novo se um existente já servir; o bloco é local o bastante para permanecer inline em `_form.blade.php`.

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/OrganizationCrudTest.php` (arquivo existente — acrescentar casos)
  - com `Storage::fake('public')` e uma Organização com `logo_path` preenchido, `GET /organizations/{id}/edit` retorna markup contendo um `<img>` cujo `src` aponta para a URL pública do arquivo.
  - a resposta **não** contém mais a string `Logo atual:` seguida do caminho cru.
  - Organização sem `logo_path`: a resposta não contém `<img>` de logo nem `src` vazio.
  - `GET /organizations/create` não renderiza `<img>` de logo.
  - upload/substituição de logo continua funcionando e apagando o arquivo anterior (não-regressão de `@update`).
- **Browser test (Dusk):** `tests/Browser/OrganizationLogoTest.php`
  - `dusk="organization-logo-preview"`: visível na tela de edição de uma Organização com logo (`assertVisible`), e a imagem carrega com dimensões maiores que zero.
  - `assertMissing('[dusk="organization-logo-preview"]')` para Organização sem logo e na tela de criação.
  - após enviar um novo logo e salvar, o preview na tela de edição reflete o arquivo novo.

## 6. Acceptance Criteria for Fix Verification
- [ ] A tela de edição renderiza o logo atual como imagem, abaixo do campo de upload.
- [ ] O caminho cru do arquivo não é mais exibido ao usuário.
- [ ] O preview respeita uma dimensão máxima e não distorce a imagem.
- [ ] A imagem possui `alt` descritivo (ex.: "Logo da Organização {nome}").
- [ ] Organizações sem logo e a tela de criação não renderizam imagem nem `src` vazio.
- [ ] A URL é resolvida a partir do disco `public`, não montada manualmente a partir da coluna.
- [ ] `OrganizationController` permanece inalterado.
- [ ] `vendor/bin/sail artisan test --compact --filter=OrganizationCrudTest` passa.
- [ ] `vendor/bin/sail artisan dusk --filter=OrganizationLogoTest` passa.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** OPEN
- **Reproduction Tests:** —
- **Fixed In Files:** —
