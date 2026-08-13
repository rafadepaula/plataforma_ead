@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$lesson->module->course->title.' / '.$lesson->module->title" title="Editar Lição" />

    <x-ui.card>
        <form method="POST" action="{{ route('lessons.update', $lesson) }}" enctype="multipart/form-data" dusk="lesson-form">
            @csrf
            @method('PUT')

            @include('modules.lessons._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="lesson-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $lesson->module) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
