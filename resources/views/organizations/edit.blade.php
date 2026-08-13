@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Organizações" title="Editar Organização" />

    <x-ui.card>
        <form method="POST" action="{{ route('organizations.update', $organization) }}" enctype="multipart/form-data" dusk="organization-form">
            @csrf
            @method('PUT')

            @include('organizations._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="organization-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('organizations.index') }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
