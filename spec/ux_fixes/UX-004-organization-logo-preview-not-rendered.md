# UX-004: Edição de Organização mostra o caminho do arquivo do logo em vez de renderizar a imagem

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO. A migração apenas trocou o `<span style="...">` por um `<div class="form-text">` — `resources/views/organizations/_form.blade.php:41` continua imprimindo o caminho cru e nenhum `<img>` é renderizado. O bloco de logo também é o único campo do formulário que ainda usa `<input type="file">` cru em vez de `<x-ui.input type="file">`, então a correção deve fechar as duas pontas de uma vez.

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
- É exibida a string `Logo atual: {caminho relativo do arquivo}` dentro de um `.form-text` (pós-migração; antes era um `<span style="font-size:12px; color: var(--color-neutral-600)">`).
- Nenhuma imagem é renderizada.

## 3. Codebase & Architectural Mapping

Verificado por `grep` em 2026-08-13; todas as referências abaixo foram reconfirmadas na árvore pós-migração.

- **Route Name / URL:** `organizations.edit` (`GET /organizations/{organization}/edit`) — `routes/web.php:45` (`Route::resource('organizations', OrganizationController::class)->except(['show'])`), sob `middleware(['auth','role:admin'])`. **Confirmado.**
- **Controller / Action:** `App\Http\Controllers\OrganizationController@edit` — `app/Http/Controllers/OrganizationController.php:54-59` (passa a instância completa de `Organization`; nenhuma alteração necessária). **Confirmado.**
- **Blade View / Component:**
  - `resources/views/organizations/_form.blade.php:36-46` — bloco do campo de logo, hoje markup Bootstrap cru (`<input type="file" class="form-control">` + `.form-text` + `@error`/`.invalid-feedback` manuais). Compartilhado por `create` e `edit`. **Alvo da correção.**
  - `resources/views/organizations/edit.blade.php:11` — `@include('organizations._form')`, dentro de `<x-ui.card>` e do `<form dusk="organization-form" enctype="multipart/form-data">` (`:7`).
  - `resources/views/organizations/create.blade.php:7-13` — mesma estrutura.
- **Componentes disponíveis (ler antes de propor markup):** `resources/views/components/ui/input.blade.php:44-59` (ramo `<input>` genérico + `hint` como `.form-text`), `components/ui/field-stack.blade.php`, `components/ui/form-actions.blade.php`.
- **Model / Coluna:** `App\Models\Organization` — `logo_path` em `$fillable` (`app/Models/Organization.php:24`); coluna `string nullable` (`database/migrations/2026_08_01_000001_create_organizations_table.php:21`).
- **Upload / Storage:** `OrganizationController@store` (`app/Http/Controllers/OrganizationController.php:43-46`, gravação na `:44`) e `@update` (`:65-70`: apaga o anterior via `Storage::disk('public')->delete()` na `:67` e regrava na `:69`) usam `->store('organizations/logos', 'public')`. **Confirmado — nada a mudar aqui.**
- **Disco / URL pública:** `config/filesystems.php:41-48` — disco `public`, `root => storage_path('app/public')`, `url => APP_URL.'/storage'`. O symlink é declarado em `config/filesystems.php:77` (`public_path('storage') => storage_path('app/public')`).
- **Estado do symlink neste ambiente:** `public/storage` **não existe** (o diretório `public/` contém apenas `.htaccess`, `build/`, `favicon.ico`, `fonts/`, `index.php`, `robots.txt`), e o caminho é ignorado pelo Git (`.gitignore:20`). Ou seja: `vendor/bin/sail artisan storage:link` é pré-condição real de verificação manual, não hipótese.
- **Onde o logo da Organização é efetivamente exibido hoje:** apenas em `resources/views/certificates/pdf.blade.php:59-60`, e via `public_path('storage/'.$logo_path)` (caminho de arquivo, exigência do dompdf) com a classe `.org-logo` (`resources/scss/app.scss:188-190`, exceção histórica de `!important`). **Topbar e sidebar não renderizam `logo_path`** — `grep -rn "logo" resources/views/components/layout/ resources/views/layouts/` só retorna `$logoutUrl` e `.guest-logo`. Portanto a correção não tem efeito colateral em layout.
- **Ocorrência irmã (fora do escopo deste UX):** `resources/views/settings/edit.blade.php:55` repete exatamente o mesmo antipadrão, passando `'Logo atual: '.$settings['logo_path']` como `hint` do `<x-ui.input type="file">`. Merece um UX próprio; **não** juntar aqui.
- **Serviço relacionado:** `App\Services\FileUploadService` (não está no caminho desta correção).

## 4. Root Cause Analysis (apresentação / markup)
O upload funciona: o arquivo é gravado no disco `public` e o caminho relativo persiste em `organizations.logo_path`. O defeito é só de renderização — o Blade imprime a string em vez de resolver a URL pública. **A migração Bootstrap 5.3 não corrigiu isso**; apenas trocou o `<span>` inline-styled pela utility class equivalente:

```blade
{{-- resources/views/organizations/_form.blade.php:36-46 — estado ATUAL (pós-migração) --}}
<div class="mb-3">
    <label for="logo" class="form-label">Logo</label>
    <input type="file" id="logo" name="logo" accept="image/*"
           class="form-control @error('logo') is-invalid @enderror" />
    @if($organization->logo_path)
        <div class="form-text">Logo atual: {{ $organization->logo_path }}</div>
    @endif
    @error('logo')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
```

Dois problemas coexistem no mesmo bloco:

1. **O defeito original (UX-004):** o caminho cru é impresso em vez de a imagem ser renderizada. Como o arquivo vive no disco `public`, a URL navegável vem de `Storage::disk('public')->url($organization->logo_path)` — nunca do valor cru da coluna, nem de concatenação manual com `APP_URL`.
2. **Dívida de convenção introduzida pela migração:** este é o único campo do formulário que reconstrói à mão o que `<x-ui.input>` já entrega (label, `.form-control`, `.is-invalid`, `.invalid-feedback`, `aria-describedby`). Viola a regra 12 da lista fechada de padrões proibidos (`bootstrap-conventions` §3): *markup Bootstrap cru numa tela quando existe um `<x-ui.*>` para ele*. Os quatro campos acima (`name`, `slug`, `cnpj`, `status`) já usam `<x-ui.input>`/`<x-ui.select>`.

### Proposta de implementação (vocabulário atual)

Substituir o bloco inteiro `resources/views/organizations/_form.blade.php:36-46` por:

```blade
<x-ui.input
    type="file"
    name="logo"
    label="Logo"
    accept="image/*"
/>

@if($organization->logo_path)
    <div class="mb-3">
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($organization->logo_path) }}"
             alt="Logo da Organização {{ $organization->name }}"
             class="d-block h-60 mw-100 object-fit-contain border p-2"
             dusk="organization-logo-preview">
    </div>
@endif
```

Justificativa classe a classe (árvore de decisão de `bootstrap-conventions` §5 — nenhuma classe nova é necessária, nenhum `style=` inline):

| Classe | Origem | Papel |
| :--- | :--- | :--- |
| `h-60` | utility do projeto — `resources/scss/_utilities.scss:34` (`height: 60px`) | Trava a altura do thumbnail. Com só a altura definida, o `<img>` escala proporcionalmente e **não distorce**. |
| `mw-100` | utility padrão do Bootstrap (`max-width: 100%`) | Impede estouro horizontal num logo muito largo. |
| `object-fit-contain` | utility nativa do Bootstrap 5.3 | Cinto e suspensório: garante proporção mesmo se largura e altura vierem a ser fixadas. |
| `border p-2` | utilities padrão | Delimita o thumbnail contra o fundo do `<x-ui.card>`. O raio 0 vem do override global de `$border-radius` — **não** acrescentar `rounded*`. |
| `d-block` | utility padrão | Evita o baseline gap do `<img>` inline. |

Pontos de atenção:

- **Visibilidade é `@if` de servidor**, não `.d-none` nem `style.display`. O estado "sem logo" simplesmente não emite o `<img>` — assim não existe `src` vazio, que dispararia uma requisição inútil ao próprio HTML da página.
- **`create` já está coberto:** `$organization` é uma instância nova (`new Organization`, `app/Http/Controllers/OrganizationController.php:35`) com `logo_path` nulo, então o `@if` reprova e nenhum `<img>` é renderizado.
- **Dependência de `storage:link`:** o symlink `public/storage` **não existe** na árvore atual e é gitignored (`.gitignore:20`). Sem `vendor/bin/sail artisan storage:link` a imagem quebra — daí a exigência de `alt` descritivo, que é o que o usuário lê no fallback.
- **Não reutilizar `.org-logo`.** Essa classe existe exclusivamente para o PDF do certificado (`resources/scss/app.scss:188-190`) e é a única exceção de `!important` do projeto; puxá-la para uma tela web amplia indevidamente o alcance da exceção.
- **Não criar componente novo.** O padrão "input file + preview" aparece hoje em 2 telas (`organizations/_form`, `settings/edit`) — abaixo do limiar de ≥3 telas da §5. Se a ocorrência irmã de `settings/edit.blade.php:55` for corrigida e uma terceira aparecer, aí sim vale um `<x-ui.image-preview>` pelo agente `bootstrap-component-author`.
- **`<x-ui.input type="file">` emite `value="{{ old($name, $value) }}"`** (`components/ui/input.blade.php:48`). Para `type="file"` o valor sai vazio e o navegador o ignora — é o mesmo caminho já em produção em `settings/edit.blade.php:50-56`, portanto não é regressão.

### Fora de escopo: preview antes do upload (JS)

Este documento cobre apenas o preview **server-side do logo já persistido**. Se no futuro for pedido um preview ao vivo do arquivo escolhido antes de salvar, isso exige JavaScript (`FileReader`/`URL.createObjectURL` no evento `change` do input) e caberia num módulo novo `resources/js/modules/LogoPreview.js`, registrado em `resources/js/modules/index.js` — `import LogoPreview from './LogoPreview';` na ordem alfabética (entre `HttpClient` e `LessonPlayer`) e uma entrada `LogoPreview: new LogoPreview(),` no objeto exportado, que recebe `.init()` no `DOMContentLoaded`. A troca de estado do elemento de preview usaria `.d-none`, nunca `style.display`. **Nenhum JS deve ser escrito para fechar UX-004.**

## 5. Test Specification Plan (TDD Blueprint)
- **Feature test (PHPUnit):** `tests/Feature/OrganizationCrudTest.php` (arquivo existente — acrescentar casos)
  - com `Storage::fake('public')` e uma Organização com `logo_path` preenchido, `GET /organizations/{id}/edit` retorna markup contendo um `<img>` cujo `src` aponta para a URL pública do arquivo.
  - a resposta **não** contém mais a string `Logo atual:` seguida do caminho cru.
  - Organização sem `logo_path`: a resposta não contém `<img>` de logo nem `src` vazio.
  - `GET /organizations/create` não renderiza `<img>` de logo.
  - upload/substituição de logo continua funcionando e apagando o arquivo anterior (não-regressão de `@update`).
  - não-regressão de convenção: a resposta contém `id="logo"` e `name="logo"` (contrato preservado por `<x-ui.input type="file">`), e o erro de validação de `logo` continua saindo em `.invalid-feedback` com `dusk="error-logo"` (agora fornecido pelo componente, `components/ui/input.blade.php:62`).
- **Browser test (Dusk):** acrescentar os casos ao arquivo **existente** `tests/Browser/OrganizationCrudTest.php` — não criar `OrganizationLogoTest.php`; a convenção do projeto é um arquivo Dusk por tela/CRUD, e este já existe e já cobre o formulário (`dusk="organization-form"`, `dusk="organization-submit"`).
  - `dusk="organization-logo-preview"`: visível na tela de edição de uma Organização com logo (`assertVisible`), e a imagem carrega com dimensões maiores que zero.
  - `assertMissing('[dusk="organization-logo-preview"]')` para Organização sem logo e na tela de criação.
  - após enviar um novo logo e salvar, o preview na tela de edição reflete o arquivo novo.
  - **Pré-condição de ambiente:** o teste Dusk depende de `public/storage` existir; garantir `vendor/bin/sail artisan storage:link` no setup do ambiente de browser, senão a asserção de dimensões maiores que zero falha por 404 e não por regressão de markup.

## 6. Acceptance Criteria for Fix Verification
- [ ] A tela de edição renderiza o logo atual como imagem, abaixo do campo de upload.
- [ ] O caminho cru do arquivo não é mais exibido ao usuário.
- [ ] O preview respeita uma dimensão máxima e não distorce a imagem.
- [ ] A imagem possui `alt` descritivo (ex.: "Logo da Organização {nome}").
- [ ] Organizações sem logo e a tela de criação não renderizam imagem nem `src` vazio.
- [ ] A URL é resolvida a partir do disco `public` via `Storage::disk('public')->url()`, não montada manualmente a partir da coluna nem concatenada com `APP_URL`.
- [ ] O campo de upload passa a usar `<x-ui.input type="file">`, eliminando o markup Bootstrap cru do bloco (`bootstrap-conventions` §3, regra 12).
- [ ] Zero `style=` inline no bloco; a visibilidade do preview é decidida por `@if` no servidor (sem `.d-none`, sem `style.display`).
- [ ] Nenhuma classe CSS nova é criada: apenas utilities existentes (`h-60` de `_utilities.scss:34`, `mw-100`, `object-fit-contain`, `border`, `p-2`, `d-block`).
- [ ] Nenhum `rounded*` e nenhum uso de `.org-logo` fora do PDF do certificado.
- [ ] `OrganizationController` permanece inalterado.
- [ ] Nenhum módulo JS é adicionado (o preview pré-upload segue fora de escopo).
- [ ] `vendor/bin/sail artisan test --compact --filter=OrganizationCrudTest` passa.
- [ ] `vendor/bin/sail artisan dusk --filter=OrganizationCrudTest` passa (com `storage:link` presente).
- [ ] `vendor/bin/sail npm run build` executado após a alteração de Blade/SCSS, se aplicável.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` limpo.

## Resolution Status
- **Status:** OPEN — revalidado em 2026-08-13 contra a árvore pós-migração (commit `3088d99`). Veredicto: **AINDA VÁLIDO**, com escopo levemente ampliado para absorver a dívida de convenção do campo de upload.
- **Reproduction Tests:** —
- **Fixed In Files:** —
