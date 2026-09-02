@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Organização', 'url' => route('gestor.students.index')], ['label' => 'Alunos Matriculados', 'url' => route('gestor.students.index')], ['label' => 'Editar Aluno']]"
        kicker="Organização"
        title="Editar Aluno"
        subtitle="Atualize os dados e o status de acesso deste Aluno da sua Organização."
    />

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('gestor.students.update', $user) }}" dusk="student-form">
                    @csrf
                    @method('PUT')

                    <x-ui.field-stack>
                        <x-ui.input name="name" label="Nome" required value="{{ old('name', $user->name) }}" />

                        <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email', $user->email) }}" />

                        <x-ui.input name="cpf" label="CPF" value="{{ old('cpf', $user->cpf) }}" hint="Opcional." />

                        <x-ui.select
                            name="status"
                            label="Status"
                            required
                            :options="['active' => 'Ativo', 'inactive' => 'Inativo']"
                            :selected="old('status', $user->status)"
                            dusk="student-status-select"
                        />

                        <x-ui.input
                            name="reason"
                            label="Motivo da mudança de status"
                            hint="Opcional. Usado apenas quando o status é alterado; fica registrado na auditoria."
                            value="{{ old('reason') }}"
                            dusk="student-status-reason"
                        />

                        <x-ui.input name="password" type="password" label="Nova Senha" hint="Deixe em branco para manter a senha atual." />

                        <x-ui.input name="password_confirmation" type="password" label="Confirmar Nova Senha" />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" href="{{ route('gestor.students.index') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="student-submit">Salvar Alterações</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
