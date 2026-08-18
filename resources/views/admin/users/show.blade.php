{{--
    `/admin/users/{user}` (`admin.users.show`), served by
    `App\Http\Controllers\Admin\UserAdminController::show()` (Bucket A).

    Perfil completo, somente leitura, de um usuário — visível para
    qualquer Organização à qual ele esteja vinculado (ou nenhuma, no caso
    de um Admin do sistema). Segue a composição de `<x-layout.page-header>`
    + `<x-ui.card>` já usada nas telas de edição do módulo (ver
    `users/edit.blade.php`), sem markup Bootstrap cru nem `style=`.

    Expected variable: `$user` (com `organization` e `roles` pré-carregados).
--}}
@extends('layouts.app')

@section('content')
    <x-slot:title>{{ $user->name }} — Usuários (Administração Global) — Plataforma EAD</x-slot:title>

    <div dusk="admin-user-show">
        <x-layout.page-header kicker="Administração" :title="$user->name">
            <x-slot:actions>
                <x-ui.button variant="secondary" href="{{ route('admin.users.index') }}" dusk="back-to-admin-users">Voltar</x-ui.button>
                <x-ui.button href="{{ route('admin.users.edit', $user) }}" dusk="edit-admin-user">Editar</x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        <div class="row">
            <div class="col-12 col-lg-8">
                <x-ui.card>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nome</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-name">{{ $user->name }}</dd>

                        <dt class="col-sm-4">E-mail</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-email">{{ $user->email }}</dd>

                        <dt class="col-sm-4">CPF</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-cpf">{{ $user->cpf ?? '—' }}</dd>

                        <dt class="col-sm-4">Organização</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-organization">{{ $user->organization->name ?? 'Nenhuma — Admin do Sistema' }}</dd>

                        <dt class="col-sm-4">Papel</dt>
                        <dd class="col-sm-8">
                            <x-ui.badge variant="accent" data-role="{{ $user->getRoleNames()->first() ?? '' }}" dusk="admin-user-show-role">
                                {{ \App\Enums\Permissions\RolesEnum::label($user->getRoleNames()->first() ?? '') }}
                            </x-ui.badge>
                        </dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <x-ui.badge :variant="$user->status === 'active' ? 'accent' : 'neutral'"
                                        data-status="{{ $user->status }}"
                                        dusk="admin-user-show-status">
                                {{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}
                            </x-ui.badge>
                        </dd>

                        <dt class="col-sm-4">Criado em</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-created-at">{{ $user->created_at?->format('d/m/Y H:i:s') }}</dd>

                        <dt class="col-sm-4">Atualizado em</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-updated-at">{{ $user->updated_at?->format('d/m/Y H:i:s') }}</dd>
                    </dl>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection
