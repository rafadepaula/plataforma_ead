@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Organização', 'url' => route('gestor.professors.index')], ['label' => 'Professores', 'url' => route('gestor.professors.index')], ['label' => 'Novo Professor']]"
        kicker="Organização"
        title="Novo Professor"
        subtitle="Cadastre um novo Professor da sua Organização. Depois, atribua-o aos cursos na gestão do curso."
    />

    <div class="row">
        <div class="col-12 col-lg-6">
            <x-ui.card>
                <form method="POST" action="{{ route('gestor.professors.store') }}" dusk="professor-form">
                    @csrf

                    <x-ui.field-stack>
                        <x-ui.input name="name" label="Nome" required value="{{ old('name') }}" />

                        <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email') }}" />

                        <x-ui.input name="cpf" label="CPF" value="{{ old('cpf') }}" hint="Opcional." />

                        <x-ui.input name="password" type="password" label="Senha" required />

                        <x-ui.input name="password_confirmation" type="password" label="Confirmar Senha" required />
                    </x-ui.field-stack>

                    <x-ui.form-actions align="end">
                        <x-ui.button variant="secondary" href="{{ route('gestor.professors.index') }}">Cancelar</x-ui.button>
                        <x-ui.button type="submit" dusk="professor-submit">Cadastrar Professor</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
