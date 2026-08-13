@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$module->course->title" title="Editar Módulo" />

    <x-ui.card>
        <form method="POST" action="{{ route('modules.update', $module) }}" dusk="module-form">
            @csrf
            @method('PUT')

            @include('courses.modules._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="module-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $module->course) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
