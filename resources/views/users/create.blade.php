@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Organização', 'url' => route('users.index')], ['label' => 'Alunos & Gestores', 'url' => route('users.index')], ['label' => 'Novo Usuário']]"
        kicker="Organização"
        title="Novo Usuário"
        subtitle="Cadastre um novo Aluno ou Gestor na sua Organização."
    />

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('users.store') }}" dusk="user-form">
                    @csrf

                    <x-ui.field-stack>
                        <x-ui.input name="name" label="Nome" required value="{{ old('name') }}" />

                        <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email') }}" />

                        <x-ui.input name="cpf" label="CPF" value="{{ old('cpf') }}" hint="Opcional." />

                        <x-ui.select
                            name="role"
                            label="Papel"
                            required
                            :options="['aluno' => 'Aluno', 'gestor' => 'Gestor']"
                            :selected="old('role', 'aluno')"
                        />

                        <x-ui.input name="password" type="password" label="Senha" required />

                        <x-ui.input name="password_confirmation" type="password" label="Confirmar Senha" required />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" href="{{ route('users.index') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="user-submit">Criar Usuário</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
