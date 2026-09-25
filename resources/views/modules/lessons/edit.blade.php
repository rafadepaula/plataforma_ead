@extends('layouts.app')

@php
    // mesma gestão de conteúdo atende Admin/Gestor e Professor atribuído
    // — a raiz do breadcrumb aponta para a home de quem está logado.
    $coursesRoot = auth()->user()?->hasRole('professor')
        ? ['label' => 'Meus Cursos', 'url' => route('professor.courses.index')]
        : ['label' => 'Cursos', 'url' => route('courses.index')];
@endphp

@section('content')
    <x-layout.page-header
        :breadcrumb="[$coursesRoot, ['label' => $lesson->module->course->title, 'url' => route('courses.modules.index', $lesson->module->course)], ['label' => $lesson->module->title, 'url' => route('modules.lessons.index', $lesson->module)], ['label' => 'Editar Lição']]"
        :kicker="$lesson->module->course->title.' / '.$lesson->module->title"
        title="Editar Lição"
        subtitle="Trocar o tipo de conteúdo limpa os campos do tipo anterior."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('lessons.update', $lesson) }}" enctype="multipart/form-data" id="lesson-form" dusk="lesson-form" data-lesson-form>
            @csrf
            @method('PUT')

            @include('modules.lessons._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="lesson-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('modules.lessons.index', $lesson->module) }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>

        @if ($lesson->progress()->exists())
            <x-ui.confirm-modal
                id="reset-video-progress-modal"
                title="Resetar progresso dos alunos?"
                form="lesson-form"
                confirmLabel="Sim, resetar progresso"
                variant="danger"
                confirmDusk="confirm-reset-progress"
            >
                Ao salvar com "Resetar progresso dos alunos neste vídeo" marcado e uma nova URL, todo o progresso assistido neste vídeo será apagado e a % de conclusão dos cursos poderá regredir. Esta ação não poderá ser desfeita.
            </x-ui.confirm-modal>
        @endif
    </x-ui.card>
@endsection
