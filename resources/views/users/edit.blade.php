@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Alunos & Gestores" title="Editar Usuário" />

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('users.update', $user) }}" dusk="user-form">
                    @csrf
                    @method('PUT')

                    <x-ui.input name="name" label="Nome" required value="{{ old('name', $user->name) }}" />

                    <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email', $user->email) }}" />

                    <x-ui.input name="cpf" label="CPF" value="{{ old('cpf', $user->cpf) }}" hint="Opcional." />

                    <x-ui.select
                        name="role"
                        label="Papel"
                        required
                        :options="['aluno' => 'Aluno', 'gestor' => 'Gestor']"
                        :selected="old('role', $user->getRoleNames()->first())"
                    />

                    <x-ui.select
                        name="status"
                        label="Status"
                        required
                        :options="['active' => 'Ativo', 'inactive' => 'Inativo']"
                        :selected="old('status', $user->status)"
                        dusk="user-status-select"
                    />

                    <x-ui.input
                        name="reason"
                        label="Motivo da mudança de status"
                        hint="Opcional. Usado apenas quando o status é alterado; fica registrado na auditoria."
                        value="{{ old('reason') }}"
                        dusk="user-status-reason"
                    />

                    <x-ui.input name="password" type="password" label="Nova Senha" hint="Deixe em branco para manter a senha atual." />

                    <x-ui.input name="password_confirmation" type="password" label="Confirmar Nova Senha" />

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <x-ui.button type="submit" dusk="user-submit">Salvar Alterações</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('users.index') }}">Cancelar</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
