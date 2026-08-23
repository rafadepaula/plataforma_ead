@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $lesson->module->course->title, 'url' => route('courses.modules.index', $lesson->module->course)], ['label' => $lesson->module->title, 'url' => route('modules.lessons.index', $lesson->module)], ['label' => 'Editar Lição']]"
        :kicker="$lesson->module->course->title.' / '.$lesson->module->title"
        title="Editar Lição"
        subtitle="Trocar o tipo de conteúdo limpa os campos do tipo anterior."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('lessons.update', $lesson) }}" enctype="multipart/form-data" dusk="lesson-form" data-lesson-form>
            @csrf
            @method('PUT')

            @include('modules.lessons._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="lesson-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('modules.lessons.index', $lesson->module) }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
