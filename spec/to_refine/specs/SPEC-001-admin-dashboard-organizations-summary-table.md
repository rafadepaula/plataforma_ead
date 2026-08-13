# SPEC-001: Tabela de resumo das Organizações no Dashboard do Admin

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** AINDA VÁLIDO. O Dashboard continua entregando exatamente os 4 stat cards, a tabela de matrículas recentes e os dois botões de exportação (`resources/views/dashboard/index.blade.php:12-66`); não existe nenhuma agregação por Organização em `DashboardMetricsService` (`app/Services/DashboardMetricsService.php:36-49`). A migração mudou apenas o vocabulário de markup — a funcionalidade segue ausente.

## Descrição
O Dashboard do Admin (`admin.dashboard`) hoje entrega apenas KPIs agregados (alunos ativos, certificados, percentual de conclusão, número de cursos), a lista de matrículas recentes e os botões de exportação — todos no escopo resolvido pelo contexto atual. Falta ao Admin uma visão comparativa entre Organizações.

Esta funcionalidade adiciona ao Dashboard uma tabela de resumo listando as Organizações cadastradas na plataforma, cada linha com indicadores próprios daquela Organização: número de alunos, número de cursos, certificados emitidos e demais métricas a definir no refinamento.

A tabela é exclusiva do perfil Admin em contexto global (sem Impersonate Org ativo) — um Gestor, restrito à própria Organização, não tem visão cross-org. Serve como ponto de entrada de diagnóstico: identificar rapidamente Organizações inativas, sem cursos publicados ou sem certificados emitidos.

## Critérios de Aceitação
- [ ] O Admin, no Dashboard, vê uma tabela listando as Organizações cadastradas na plataforma.
- [ ] Cada linha exibe o nome da Organização e, no mínimo, número de alunos, número de cursos e certificados emitidos.
- [ ] O conjunto final de colunas é definido no refinamento e cada métrica tem definição inequívoca (ex.: "alunos" conta apenas usuários com papel `aluno` e status `ativo`).
- [ ] A tabela reflete todas as Organizações, inclusive as que não possuem nenhum dado (zeros explícitos, não linhas ausentes).
- [ ] Um Gestor não vê essa tabela.
- [ ] Os KPIs, as matrículas recentes e as exportações já existentes no Dashboard permanecem funcionando sem alteração.
- [ ] A tabela não introduz consultas N+1 conforme o número de Organizações cresce.

## Revalidação técnica (2026-08-13 — vocabulário Bootstrap 5.3)

### Estado atual verificado
- `resources/views/dashboard/index.blade.php:7` — `<x-layout.page-header title="Dashboard" />`.
- `resources/views/dashboard/index.blade.php:12-21` — os 4 `<x-ui.stat-card>` (`dusk="stat-active-students"`, `stat-certificates-issued`, `stat-completion-rate`, `stat-courses-count`).
- `resources/views/dashboard/index.blade.php:34,42` — `dusk="export-enrollments-csv"` / `export-certificates-csv`.
- `resources/views/dashboard/index.blade.php:48-66` — `<x-ui.data-table dusk="recent-enrollments-table">` com `<x-ui.badge>` e `<x-ui.empty-state colspan="3">`.
- `app/Http/Controllers/DashboardController.php:29-37` — `index()` passa apenas `stats` e `recentEnrollments`.
- `app/Http/Controllers/DashboardController.php:42-53` — `resolveViewingOrgId()` já retorna `null` para Admin sem `session('active_org_id')`; **esse `null` é exatamente o gatilho da tabela nova**.
- `app/Services/DashboardMetricsService.php:36-49` — `getStats()` / `recentEnrollments()`; nenhum método por Organização.
- Rota: `admin.dashboard` → `GET admin/dashboard`, grupo `middleware(['auth','role:admin|gestor'])` (`routes/web.php:290-291`), confirmado via `artisan route:list`.

### Implementação no vocabulário atual
**Camada de dados.** Novo método irmão em `app/Services/DashboardMetricsService.php` (ao lado de `recentEnrollments()`, linha 49), p.ex. `organizationsSummary(): Collection`. Deve partir de `Organization::query()` com agregações por `leftJoin`/subquery (`withCount` + subselects) para satisfazer os critérios "zeros explícitos" e "sem N+1" numa única consulta. Seguir a convenção já documentada no docblock da classe (`app/Services/DashboardMetricsService.php:12-28`): o serviço **não** lê `Auth::user()` nem `session('active_org_id')`.

**Controller.** Em `DashboardController::index()` (`app/Http/Controllers/DashboardController.php:29`), passar `organizationsSummary` **apenas** quando `$request->user()->hasRole(RolesEnum::ADMIN->value)` **e** `$this->resolveViewingOrgId($request) === null`. Caso contrário, passar `null` — assim o critério "um Gestor não vê essa tabela" e "Admin impersonando não vê" caem no mesmo `@if` da view.

**View.** Inserir o bloco em `resources/views/dashboard/index.blade.php` **depois** do `</x-ui.data-table>` da linha 66, dentro do `<div dusk="admin-dashboard">`. Composição obrigatória (zero markup Bootstrap cru, zero `style=`):
- Cabeçalho da seção: reaproveitar literalmente o padrão já usado nas linhas 26-28 — `<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">` + `<h2 class="h5 mb-0">Resumo das Organizações</h2>`.
- `<x-ui.data-table striped hover responsive :headers="['Organização', 'Alunos', 'Cursos', 'Certificados']" dusk="organizations-summary-table">`.
- Linhas com `@forelse` e `<x-ui.empty-state colspan="4" message="Nenhuma Organização cadastrada." />` no `@empty`.
- Se a lista crescer, `<x-ui.pagination :paginator="..." />` logo abaixo — **não** paginar na primeira entrega salvo decisão em contrário.
- Espaçamento entre a tabela de matrículas e a nova seção: `mt-5` (mapa `$spacers` padrão do Bootstrap). Nenhuma classe fantasma, nenhum `rounded*`, nenhum hex.

**Navegação.** Nenhuma mudança no `NavigationRegistry`/`NavigationComposer`: a tabela vive dentro da tela `admin.dashboard`, já registrada em `app/Services/Navigation/NavigationRegistry.php:39-47`.

**Seletores `dusk=` a criar.**
- `dusk="organizations-summary-table"` na `<x-ui.data-table>`.
- `dusk="organization-summary-row-{{ $row->id }}"` no `<tr>`.
- `dusk="org-summary-students-{{ $row->id }}"`, `org-summary-courses-{{ $row->id }}`, `org-summary-certificates-{{ $row->id }}` nas `<td>` de métrica (assertar número, não posição de coluna).

**Armadilha de teste.** Se qualquer célula usar `<x-ui.badge>`, o CSS do projeto aplica `text-transform: uppercase` e o Selenium lê o texto **renderizado** — a asserção recebe CAIXA ALTA. Para números puros, preferir `<td>` simples sem badge.

**Conflito com demanda registrada.** Nenhum. `spec/bugs/BUG-005-...` trata do acesso do Admin a `users.index` e não toca no Dashboard.

## Origem
Demanda original:

> Dado que estou logado com perfil: Admin
> Estou na tela: Dashboard
> Estou vendo:
>     - alunos ativos, certificados, porcentagem de conclusão, número de cursos.
>     - matrículas recentes
>     - exportar matrículas e certificados
>
> Gostaria de estar vendo:
>     - resumo das organizações, uma tabela mostrando uma lista de organizações que estão cadastradas na plataforma e alguns dados sobre elas: número de alunos, cursos, certificado emitidos, etc.

Arquivo de origem: `spec/to_refine/001/admin.md`
