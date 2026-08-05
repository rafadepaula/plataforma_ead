@extends('layouts.app')

@section('content')
    <x-ui.card title="Editar Lição" kicker="{{ $lesson->module->course->title }} / {{ $lesson->module->title }}">
        <form method="POST" action="{{ route('lessons.update', $lesson) }}" enctype="multipart/form-data" dusk="lesson-form">
            @csrf
            @method('PUT')

            @include('modules.lessons._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="lesson-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $lesson->module) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
