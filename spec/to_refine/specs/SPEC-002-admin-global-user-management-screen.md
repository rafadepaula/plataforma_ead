# SPEC-002: Tela exclusiva de administração global de usuários

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO. Não existe rota `admin/users` nem qualquer tela administrativa de usuários (`artisan route:list` só registra o resource `users.*`), e `users.index` continua single-org, restrita a aluno/gestor e sem filtros (`app/Http/Controllers/UserController.php:31-46`, `resources/views/users/index.blade.php:11-57`). A migração reescreveu a tela operacional em `<x-ui.*>`, mas não criou nada do escopo desta spec.

## Descrição
A tela de usuários existente (`users.index`) foi desenhada para operação dentro de uma Organização: lista apenas usuários com papel `aluno` ou `gestor`, restritos a um único `org_id`, e oferece somente editar e remover. Ela não serve à administração do sistema.

Esta funcionalidade cria uma tela exclusiva do módulo de Admin, com a lista total de usuários da plataforma — todos os perfis, todas as Organizações — voltada à administração e não à operação. A tela oferece filtros (nome, e-mail, organização, status, tipo/papel, data de criação) e ações administrativas por usuário: ativar, desativar, excluir, visualizar o perfil completo e editar o perfil completo.

Distingue-se deliberadamente da tela operacional: escopo cross-org, inclusão de Admins e demais papéis, e ações de ciclo de vida do usuário que hoje não existem em lugar nenhum (ativar/desativar direto da listagem, visualização de perfil completo).

> Pré-requisito relacionado: hoje o Admin sequer alcança `users.index` sem Impersonate Org ativo. Isso é um defeito de acesso, reportado separadamente em `spec/bugs/BUG-005-users-index-unreachable-for-admin-without-impersonation.md`, e deve ser resolvido independentemente desta funcionalidade.

## Escopo dos filtros de status e papel (decidido)
A demanda original citava um status "pendente" e um tipo "professor" que não existem no modelo de dados. Decisão do solicitante: **os valores inexistentes ficam fora de escopo** — os filtros cobrem apenas o que já existe hoje. Nenhuma migração de enum e nenhum papel novo entram nesta funcionalidade.

- **Status:** apenas `active` e `inactive` (enum atual de `users.status`).
- **Papel:** apenas `admin`, `gestor` e `aluno` (valores atuais de `RolesEnum`).

## Critérios de Aceitação
- [ ] O Admin acessa uma tela de administração de usuários que lista usuários de todas as Organizações.
- [ ] A listagem inclui os três papéis existentes (admin, gestor e aluno), não apenas aluno/gestor.
- [ ] Cada linha identifica o usuário, sua Organização, seu papel, seu status e sua data de criação.
- [ ] É possível filtrar por nome, e-mail, organização, status (`active`, `inactive`), papel (`admin`, `gestor`, `aluno`) e data de criação.
- [ ] Os filtros de status e papel oferecem apenas os valores existentes no modelo — nenhum status "pendente" e nenhum papel "professor".
- [ ] Os filtros podem ser combinados e o resultado permanece paginado.
- [ ] O Admin consegue desativar um usuário ativo diretamente da tela.
- [ ] O Admin consegue ativar um usuário inativo diretamente da tela.
- [ ] O Admin consegue excluir um usuário, com confirmação explícita antes da execução.
- [ ] O Admin consegue visualizar o perfil completo de um usuário.
- [ ] O Admin consegue editar o perfil completo de um usuário.
- [ ] A tela é inacessível a Gestores e Alunos.
- [ ] A tela operacional existente (`users.index`) continua funcionando para o Gestor no escopo da própria Organização.
- [ ] Mudanças de status e exclusões geram registro de auditoria.

## Revalidação técnica (2026-08-13 — vocabulário Bootstrap 5.3)

### Estado atual verificado
- **Rotas:** `artisan route:list --except-vendor` registra apenas `users.index|create|store|edit|update|destroy` (+ `users.import.*`). **Não existe** `admin.users.*` nem `admin/users`. O resource está em `routes/web.php:75`, dentro de `Route::middleware(['auth','role:admin|gestor'])` aberto em `routes/web.php:71`.
- **Controller:** `app/Http/Controllers/UserController.php:31-46` — `index()` faz `resolveOrgId()` estrito (linha 35), filtra `->where('org_id', $orgId)` (linha 38), restringe a `ALUNO|GESTOR` via `whereHas('roles')` (linhas 39-42), `->paginate(20)` (linha 44). Sem nenhum filtro de request.
- **View:** `resources/views/users/index.blade.php` — já migrada: `<x-layout.page-header kicker="Organização" title="Alunos & Gestores">` (linha 4), `<x-ui.data-table>` com 6 colunas (linhas 11-12), `<x-ui.badge>` para papel/status (linhas 19-25), `<x-ui.confirm-modal>` para remoção (linha 43), `<x-ui.empty-state colspan="6">` (linha 53), `<x-ui.pagination>` (linha 57). **Não há** `<x-ui.filter-bar>`, nem coluna de Organização, nem data de criação, nem ações de ativar/desativar/ver perfil.
- **Policy:** `app/Policies/UserPolicy.php:19-21` (`viewAny` = admin ou gestor) e `sharesOrgContext()` em `app/Policies/UserPolicy.php:51-66` — um Admin **sem** `session('active_org_id')` reprova em `view`/`update`/`delete`. Isso bloqueia todas as ações por linha desta spec e é escopo **desta** spec (ver divisão abaixo).
- **Navegação:** `app/Services/Navigation/NavigationRegistry.php:59-67` traz um único item `key: 'users'` → `users.index`, `roles: ['admin','gestor']`.

### Divisão de propriedade com o BUG-005 (explícita)
| Assunto | Dono |
|---|---|
| Tornar `users.index` alcançável pelo Admin sem impersonate (fim do `UnresolvedOrgContextException`) | **BUG-005** |
| Comportamento global de leitura de `users.index` (admin sem `active_org_id` ⇒ sem filtro) | **BUG-005** |
| Preservar `users.index` como tela operacional do Gestor | **BUG-005** (não-regressão) e reafirmado aqui |
| Tela nova `admin.users.index`, cross-org, com os 3 papéis | **SPEC-002** |
| Filtros (nome, e-mail, organização, status, papel, data de criação) | **SPEC-002** |
| Ativar / desativar / excluir / ver perfil completo / editar perfil completo | **SPEC-002** |
| Autorização do Admin global sobre um usuário de qualquer Organização (hoje negada por `UserPolicy::sharesOrgContext`) | **SPEC-002** |
| Auditoria de mudança de status e exclusão | **SPEC-002** (o evento `user.status_changed` já existe em `app/Http/Controllers/UserController.php:100-118` e deve ser reaproveitado) |

Sem sobreposição de arquivos: o BUG-005 altera `UserController::index()`; esta spec cria um controller/rota/view próprios. As duas podem avançar em paralelo.

### Implementação no vocabulário atual
**Rota.** Registrar dentro do grupo `role:admin` já existente em `routes/web.php:44-59` (o mesmo que serve `organizations.*` e `admin.audit-logs.*`) — não no grupo `role:admin|gestor` da linha 71, para satisfazer "inacessível a Gestores e Alunos" pelo middleware e não só pela Policy. Nomes: `admin.users.index`, `admin.users.show`, `admin.users.edit/update`, `admin.users.status` (PATCH), `admin.users.destroy`. O prefixo `admin/` + par de nomes segue o precedente `admin.audit-logs.*` (`routes/web.php:58-59`).

**Controller.** Novo, dedicado (p.ex. `App\Http\Controllers\Admin\UserAdminController`) — **não** estender `UserController`, que carrega a trait `ResolvesOrgContext` estrita (`app/Http/Controllers/Concerns/ResolvesOrgContext.php:16-28`). A listagem é global por definição; nenhum `org_id` vem do request. Paginação `->paginate(25)->withQueryString()` (padrão de `audit-logs`).

**View.** Novo arquivo (p.ex. `resources/views/admin/users/index.blade.php`), **modelado linha a linha em `resources/views/audit-logs/index.blade.php`**, que é o call-site canônico de tela de listagem com filtros. Composição obrigatória:
- `<x-layout.page-header kicker="Administração" title="Usuários do Sistema">` com `<x-slot:actions>`.
- `<x-ui.filter-bar :action="route('admin.users.index')" :reset-url="route('admin.users.index')" label="Filtros de usuários" dusk="admin-users-filter-form">` contendo, cada um em sua `<div class="col-md-3">`:
  - `<x-ui.input name="name" label="Nome" :value="request('name')" dusk="admin-users-name-filter" />`
  - `<x-ui.input type="email" name="email" label="E-mail" ... dusk="admin-users-email-filter" />`
  - `<x-ui.select name="org_id" label="Organização" :placeholder="false" dusk="admin-users-org-filter">` (com `<option value="">Todas</option>`)
  - `<x-ui.select name="status" ...>` — apenas `active` / `inactive`
  - `<x-ui.select name="role" ...>` — apenas `admin` / `gestor` / `aluno`
  - `<x-ui.input type="date" name="created_from" ... />` e `created_to`
- `<x-ui.data-table striped hover responsive :headers="['Nome','E-mail','Organização','Papel','Status','Criado em','Ações']" dusk="admin-users-table">`.
- Ações por linha em `<div class="d-flex flex-wrap align-items-center gap-2">` (e **não** `.btn-group`, quando houver `<form>` entre os filhos — ver a nota já registrada em `resources/views/organizations/index.blade.php:37-40`), com `<x-ui.button>` para "Ver"/"Editar" e `<x-ui.confirm-modal>` para excluir e para desativar (ação destrutiva/sensível ⇒ confirmação explícita, exigida pelo critério de aceitação).
- `<x-ui.empty-state colspan="7" message="Nenhum usuário encontrado." />` no `@empty`.
- `<x-ui.pagination :paginator="$users" />`.
- Zero `style=`, zero Tailwind, zero `rounded*`, zero `var(--color-*)`, zero hex, zero classe fantasma. Superfície de card = `bg-body-tertiary` (já embutida em `<x-ui.data-table>` e `<x-ui.filter-bar>`).

**Navegação.** Adicionar um `NavigationItem` novo em `app/Services/Navigation/NavigationRegistry::items()`, logo após o item `users` (`app/Services/Navigation/NavigationRegistry.php:59-67`): `key: 'admin-users'`, `label: 'Usuários do Sistema'`, `route: 'admin.users.index'`, `activePatterns: ['admin.users.*']`, `roles: ['admin']`, `section: 'Administração'`, reaproveitando `$this->usersIcon()`. O `NavigationComposer` (`app/Http/View/Composers/NavigationComposer.php:31-40`) **não** muda — ele apenas repassa `NavigationService::build()`. O item passa a render com `dusk="sidebar-admin-users-link"` (desktop) e `sidebar-admin-users-link-mobile` (offcanvas), gerados automaticamente por `resources/views/components/layout/sidebar.blade.php:29,95`.
> Atenção de escopo cruzado: o `UX-001` propõe reduzir o menu padrão do Admin. O item novo pertence ao conjunto **reduzido** (administração de sistema), não à subseção "Impersonate".

**Autorização.** `UserPolicy::sharesOrgContext()` (`app/Policies/UserPolicy.php:51-66`) exige impersonate ativo para o Admin — mantê-la intacta para a tela operacional e introduzir abilities separadas para a tela administrativa (Policy própria ou abilities `viewAnyGlobal`/`manageGlobal`). Não relaxar `sharesOrgContext`, sob pena de regressão de isolamento multitenant nas telas existentes.

**Seletores `dusk=` a criar.**
- Container: `dusk="admin-users-index"`; tabela: `admin-users-table`; form de filtros: `admin-users-filter-form`; submit: `admin-users-filter-submit`.
- Filtros: `admin-users-name-filter`, `admin-users-email-filter`, `admin-users-org-filter`, `admin-users-status-filter`, `admin-users-role-filter`, `admin-users-created-from`, `admin-users-created-to`.
- Linha: `admin-user-row-{id}`; célula de status: `admin-user-status-{id}`; papel: `admin-user-role-{id}`.
- Ações: `admin-user-view-{id}`, `admin-user-edit-{id}`, `admin-user-deactivate-{id}`, `admin-user-activate-{id}`, `admin-user-delete-{id}` (os dois últimos abrindo `<x-ui.confirm-modal>`, cujos botões já expõem `confirm-modal-{id}-confirm` / `-cancel`).

**Armadilha de teste (obrigatória no plano de testes).** `.badge` tem `text-transform: uppercase` e o Selenium lê o texto **renderizado**: um `assertSeeIn('@admin-user-status-1', 'Ativo')` falha; a asserção precisa ser `'ATIVO'` (ou o teste deve ler um `data-*`/atributo em vez do texto). Mesmo cuidado para o badge de papel.

## Origem
Demanda original:

> Dado que estou logado com perfil: admin
> Estou na tela: (não consigo acessar)
> Estou vendo:
>     - nada, pois não está sendo possível acessar
>     - a tela de lista de alunos e usuários está visível apenas quando impersonate ativo
>
> Gostaria de estar vendo:
>     - lista todal de usuários e alunos de todo o sistema
>     - visualização e tela exclusiva para módulo de admin
>     - ou seja, uma tela exclusiva para a administração do sistema, com opções e filtros exclusivos
>     - filtros:
>         - nome
>         - email
>         - organização
>         - status (ativo, inativo, pendente)
>         - tipo (admin, aluno, professor, etc)
>         - data de criação
>     - opções exclusivas:
>         - desativar usuário
>         - ativar usuário
>         - excluir usuário
>         - ver perfil completo do usuário
>         - editar perfil completo do usuário

Arquivo de origem: `spec/to_refine/001/admin.md`
