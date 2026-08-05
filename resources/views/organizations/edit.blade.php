@extends('layouts.app')

@section('content')
    <x-ui.card title="Editar Organização" kicker="Organizações">
        <form method="POST" action="{{ route('organizations.update', $organization) }}" enctype="multipart/form-data" dusk="organization-form">
            @csrf
            @method('PUT')

            @include('organizations._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="organization-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('organizations.index') }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
