{{--
    `/admin/users/{user}/edit` (`admin.users.edit` /
    `admin.users.update`), served by
    `App\Http\Controllers\Admin\UserAdminController` (Bucket A).

    Modelada em `resources/views/users/edit.blade.php` (tela operacional),
    mas com duas diferenças exigidas pela administração global:
      - `org_id` é editável aqui (a tela operacional nunca aceita `org_id`
        do request — é sempre resolvido via `ResolvesOrgContext`), com a
        opção "Nenhuma — Admin do Sistema" para desvincular/tornar Admin;
      - `role` oferece as 3 opções (Admin/Gestor/Aluno), não só
        Aluno/Gestor.

    Mantém status + motivo + senha/confirmação exatamente como a tela
    operacional. Sem markup Bootstrap cru nem `style=`.
--}}
@extends('layouts.app')

@section('content')
    <x-slot:title>Editar {{ $user->name }} — Usuários (Administração Global) — Plataforma EAD</x-slot:title>

    <x-layout.page-header
        kicker="Administração"
        title="Editar Usuário (Administração Global)"
        subtitle="Altere dados, organização, papel e status de {{ $user->name }}."
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
                            name="org_id"
                            label="Organização"
                            :options="$organizations ?? []"
                            :selected="old('org_id', $user->org_id)"
                            placeholder="Nenhuma — Admin do Sistema"
                            dusk="admin-user-org-select"
                        />

                        <x-ui.select
                            name="role"
                            label="Papel"
                            required
                            :options="['admin' => 'Administrador', 'gestor' => 'Gestor', 'aluno' => 'Aluno']"
                            :selected="old('role', $user->getRoleNames()->first())"
                            dusk="admin-user-role-select"
                        />

                        <x-ui.select
                            name="status"
                            label="Status"
                            required
                            :options="['active' => 'Ativo', 'inactive' => 'Inativo']"
                            :selected="old('status', $user->status)"
                            dusk="admin-user-status-select"
                        />

                        <x-ui.input
                            name="reason"
                            label="Motivo da mudança de status"
                            hint="Opcional. Usado apenas quando o status é alterado; fica registrado na auditoria."
                            value="{{ old('reason') }}"
                            dusk="admin-user-status-reason"
                        />

                        <x-ui.input name="password" type="password" label="Nova Senha" hint="Deixe em branco para manter a senha atual." />

                        <x-ui.input name="password_confirmation" type="password" label="Confirmar Nova Senha" />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" href="{{ route('admin.users.index') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="admin-user-submit">Salvar Alterações</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
