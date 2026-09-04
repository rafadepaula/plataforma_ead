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
        :breadcrumb="[$coursesRoot, ['label' => $module->course->title, 'url' => route('courses.modules.index', $module->course)], ['label' => 'Editar Módulo']]"
        :kicker="$module->course->title"
        title="Editar Módulo"
        subtitle="Renomeie o módulo ou ajuste a descrição. A ordem das lições não muda aqui."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('modules.update', $module) }}" dusk="module-form">
            @csrf
            @method('PUT')

            @include('courses.modules._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="module-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $module->course) }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
