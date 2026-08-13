@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Alunos & Gestores" title="Novo Usuário" />

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('users.store') }}" dusk="user-form">
                    @csrf

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

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <x-ui.button type="submit" dusk="user-submit">Criar Usuário</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('users.index') }}">Cancelar</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
