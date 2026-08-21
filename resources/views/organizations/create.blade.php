@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Administração', 'url' => route('organizations.index')], ['label' => 'Organizações', 'url' => route('organizations.index')], ['label' => 'Nova Organização']]"
        kicker="Administração"
        title="Nova Organização"
        subtitle="Cadastre uma nova Organização e seus dados de identificação."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('organizations.store') }}" enctype="multipart/form-data" dusk="organization-form">
            @csrf

            @include('organizations._form')

            <x-ui.form-actions align="end">
                <x-ui.button variant="secondary" href="{{ route('organizations.index') }}">Cancelar</x-ui.button>
                <x-ui.button type="submit" dusk="organization-submit">Criar Organização</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
