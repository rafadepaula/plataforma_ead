@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Administração', 'url' => route('organizations.index')], ['label' => 'Organizações', 'url' => route('organizations.index')], ['label' => 'Editar Organização']]"
        kicker="Administração"
        title="Editar Organização"
        subtitle="Atualize os dados de identificação e o status desta Organização."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('organizations.update', $organization) }}" enctype="multipart/form-data" dusk="organization-form">
            @csrf
            @method('PUT')

            @include('organizations._form')

            <x-ui.form-actions align="end">
                <x-ui.button variant="secondary" href="{{ route('organizations.index') }}">Cancelar</x-ui.button>
                <x-ui.button type="submit" dusk="organization-submit">Salvar Alterações</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
