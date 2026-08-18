{{--
    `/admin/users` (`admin.users.index`), served by
    `App\Http\Controllers\Admin\UserAdminController::index()` (Bucket A).

    Tela exclusiva de Administração Global de Usuários: lista TODOS os
    usuários da plataforma (Admin/Gestor/Aluno, de qualquer Organização)
    numa única listagem paginada, diferente de `users/index.blade.php`
    (`users.index`), que continua restrita à própria Organização e a
    Aluno/Gestor apenas.

    Bootstrap 5.3 composition (ver `bootstrap-conventions` §4/§5): a tela
    não tem markup Bootstrap cru nem `style=` — é montada a partir de
    `<x-layout.page-header>`, `<x-ui.filter-bar>`, `<x-ui.data-table>`,
    `<x-ui.badge>`, `<x-ui.button>`, `<x-ui.confirm-modal>`,
    `<x-ui.empty-state>` e `<x-ui.pagination>`, seguindo linha a linha o
    precedente de `resources/views/audit-logs/index.blade.php`.

    Expected variables (Bucket A contract):
      - `$users`          `User::query()->...->paginate(25)->withQueryString()`,
                           com `organization` e `roles` pré-carregados.
      - `$organizations`  `Organization::pluck('name', 'id')`.

    Os badges de Status/Papel trazem `data-status="active|inactive"` /
    `data-role="admin|gestor|aluno"` além do texto visível — o texto sai
    em CAIXA ALTA via `text-transform` de `.badge`
    (`resources/scss/components/_index.scss`), então os testes Dusk devem
    ler o atributo `data`, não o texto renderizado.
--}}
@extends('layouts.app')

@section('content')
    <x-slot:title>Usuários (Administração Global) — Plataforma EAD</x-slot:title>

    <div dusk="admin-users-index">
        <x-layout.page-header kicker="Administração" title="Usuários (Administração Global)" />

        {{-- Filtros --}}
        <x-ui.filter-bar :action="route('admin.users.index')"
                         :reset-url="route('admin.users.index')"
                         label="Filtros de usuários"
                         submit-dusk="admin-users-filter-submit"
                         dusk="admin-users-filter-form">
            <div class="col-md-3">
                <x-ui.input name="name"
                            label="Nome"
                            :value="request('name')"
                            dusk="admin-users-name-filter" />
            </div>

            <div class="col-md-3">
                <x-ui.input name="email"
                            type="email"
                            label="E-mail"
                            :value="request('email')"
                            dusk="admin-users-email-filter" />
            </div>

            <div class="col-md-3">
                <x-ui.select name="org_id"
                             label="Organização"
                             :placeholder="false"
                             dusk="admin-users-org-filter">
                    <option value="">Todas</option>
                    @foreach($organizations ?? [] as $orgId => $orgName)
                        <option value="{{ $orgId }}" @selected((string) request('org_id') === (string) $orgId)>{{ $orgName }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="col-md-3">
                <x-ui.select name="status"
                             label="Status"
                             :placeholder="false"
                             dusk="admin-users-status-filter">
                    <option value="">Todos</option>
                    <option value="active" @selected(request('status') === 'active')>Ativo</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inativo</option>
                </x-ui.select>
            </div>

            <div class="col-md-3">
                <x-ui.select name="role"
                             label="Papel"
                             :placeholder="false"
                             dusk="admin-users-role-filter">
                    <option value="">Todos</option>
                    <option value="admin" @selected(request('role') === 'admin')>Administrador</option>
                    <option value="gestor" @selected(request('role') === 'gestor')>Gestor</option>
                    <option value="aluno" @selected(request('role') === 'aluno')>Aluno</option>
                </x-ui.select>
            </div>

            <div class="col-md-3">
                <x-ui.input type="date"
                            name="created_from"
                            label="Criado a partir de"
                            :value="request('created_from')"
                            dusk="admin-users-created-from" />
            </div>

            <div class="col-md-3">
                <x-ui.input type="date"
                            name="created_to"
                            label="Criado até"
                            :value="request('created_to')"
                            dusk="admin-users-created-to" />
            </div>
        </x-ui.filter-bar>

        <x-ui.data-table striped hover responsive
                         :headers="['Nome', 'E-mail', 'Organização', 'Papel', 'Status', 'Criado em', 'Ações']"
                         dusk="admin-users-table">
            @forelse($users as $user)
                @php
                    $userRole = $user->getRoleNames()->first() ?? '';
                    $toggledStatus = $user->status === 'active' ? 'inactive' : 'active';
                @endphp
                <tr dusk="admin-user-row-{{ $user->id }}">
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->organization->name ?? 'Nenhuma — Admin do Sistema' }}</td>
                    <td>
                        <x-ui.badge variant="accent"
                                    data-role="{{ $userRole }}"
                                    dusk="admin-user-role-{{ $user->id }}">
                            {{ \App\Enums\Permissions\RolesEnum::label($userRole) }}
                        </x-ui.badge>
                    </td>
                    <td>
                        <x-ui.badge :variant="$user->status === 'active' ? 'accent' : 'neutral'"
                                    data-status="{{ $user->status }}"
                                    dusk="admin-user-status-{{ $user->id }}">
                            {{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                    <td>
                        {{-- `d-flex`, não `.btn-group`: os filhos diretos aqui são
                             `<form>`, então o agrupamento fica com o `organizations/index.blade.php:37-40`
                             precedente. --}}
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <x-ui.button variant="secondary"
                                         size="sm"
                                         href="{{ route('admin.users.show', $user) }}"
                                         dusk="view-admin-user-{{ $user->id }}">Ver</x-ui.button>

                            <x-ui.button variant="secondary"
                                         size="sm"
                                         href="{{ route('admin.users.edit', $user) }}"
                                         dusk="edit-admin-user-{{ $user->id }}">Editar</x-ui.button>

                            <x-ui.button variant="ghost"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#confirm-status-{{ $user->id }}"
                                         dusk="toggle-status-admin-user-{{ $user->id }}">
                                {{ $user->status === 'active' ? 'Desativar' : 'Ativar' }}
                            </x-ui.button>

                            <x-ui.button variant="ghost"
                                         size="sm"
                                         class="text-danger link-danger"
                                         data-bs-toggle="modal"
                                         data-bs-target="#confirm-delete-{{ $user->id }}"
                                         dusk="delete-admin-user-{{ $user->id }}">Excluir</x-ui.button>
                        </div>

                        {{-- Usa o endpoint dedicado `admin.users.status`
                             (`UserAdminController::updateStatus()`) — só o `status`
                             (não-PII) viaja na query string da `action`; nome, e-mail
                             e CPF do usuário nunca são expostos em URL/logs. --}}
                        <x-ui.confirm-modal id="confirm-status-{{ $user->id }}"
                                            title="{{ $user->status === 'active' ? 'Desativar Usuário' : 'Ativar Usuário' }}"
                                            :action="route('admin.users.status', [
                                                'user' => $user->id,
                                                'status' => $toggledStatus,
                                            ])"
                                            method="PATCH"
                                            :variant="$user->status === 'active' ? 'danger' : 'primary'"
                                            :confirm-label="$user->status === 'active' ? 'Desativar' : 'Ativar'"
                                            :message="($user->status === 'active' ? 'Desativar' : 'Ativar').' “'.$user->name.'” afeta o acesso dele em TODAS as Organizações às quais está vinculado. Esta ação fica registrada na auditoria.'"
                                            dusk="confirm-status-form-{{ $user->id }}" />

                        <x-ui.confirm-modal id="confirm-delete-{{ $user->id }}"
                                            title="Excluir Usuário"
                                            :action="route('admin.users.destroy', $user)"
                                            method="DELETE"
                                            confirm-label="Excluir"
                                            :message="'Excluir “'.$user->name.'” remove o usuário permanentemente, de qualquer Organização à qual esteja vinculado. Esta ação não poderá ser desfeita.'"
                                            dusk="confirm-delete-form-{{ $user->id }}" />
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="7" message="Nenhum usuário encontrado." />
            @endforelse
        </x-ui.data-table>

        <x-ui.pagination :paginator="$users" />
    </div>
@endsection
