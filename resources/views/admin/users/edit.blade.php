{{--
    `/admin/users/{user}/edit` (`admin.users.edit` /
    `admin.users.update`), served by
    `App\Http\Controllers\Admin\UserAdminController`.

    Sob tenancy por host, `org_id`/`status` não são editáveis aqui:
    cada portal tem a própria conta (`credentials`) com o próprio status,
    gerenciada na tela de cada organização. A senha informada aplica-se a
    TODAS as contas da pessoa (superfície global). Sem markup Bootstrap
    cru nem `style=`.
--}}
@extends('layouts.app')

@section('content')
    <x-slot:title>Editar {{ $user->name }} — Usuários (Administração Global) — Plataforma EAD</x-slot:title>

    <x-layout.page-header
        kicker="Administração"
        title="Editar Usuário (Administração Global)"
        subtitle="Altere dados, papel e senha de {{ $user->name }}."
        :breadcrumb="[
            ['label' => 'Administração'],
            ['label' => 'Usuários', 'url' => route('admin.users.index')],
            ['label' => 'Editar'],
        ]"
    />

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('admin.users.update', $user) }}" dusk="admin-user-form">
                    @csrf
                    @method('PUT')

                    <x-ui.field-stack>
                        <x-ui.input name="name" label="Nome" required value="{{ old('name', $user->name) }}" />

                        <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email', $user->email) }}" />

                        <x-ui.input name="cpf" label="CPF" value="{{ old('cpf', $user->cpf) }}" hint="Opcional." />

                        <x-ui.select
                            name="role"
                            label="Papel"
                            required
                            :options="['admin' => 'Administrador', 'gestor' => 'Gestor', 'aluno' => 'Aluno', 'professor' => 'Professor']"
                            :selected="old('role', $user->getRoleNames()->first())"
                            dusk="admin-user-role-select"
                        />

                        <x-ui.input name="password" type="password" label="Nova Senha" hint="Deixe em branco para manter as senhas atuais. Quando definida, aplica-se a todas as organizações da pessoa." />

                        <x-ui.input name="password_confirmation" type="password" label="Confirmar Nova Senha" />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" href="{{ route('admin.users.index') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="admin-user-submit">Salvar Alterações</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>

        <div class="col-12 col-lg-6">
            <x-ui.card>
                <x-slot:header>Contas por organização</x-slot:header>
                @if ($user->credentials->isEmpty())
                    <p class="text-body-secondary mb-0">Nenhuma conta de organização.</p>
                @else
                    <ul class="list-group list-group-flush" dusk="admin-user-memberships">
                        @foreach ($user->credentials as $membership)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $membership->organization?->name ?? 'Admin do Sistema (global)' }}</span>
                                <x-ui.badge :variant="$membership->status === 'active' ? 'success' : 'neutral'">
                                    {{ $membership->status === 'active' ? 'Ativo' : 'Inativo' }}
                                </x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
@endsection
