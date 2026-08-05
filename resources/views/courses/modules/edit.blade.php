@extends('layouts.app')

@section('content')
    <x-ui.card title="Editar Módulo" kicker="{{ $module->course->title }}">
        <form method="POST" action="{{ route('modules.update', $module) }}" dusk="module-form">
            @csrf
            @method('PUT')

            @include('courses.modules._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="module-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $module->course) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
