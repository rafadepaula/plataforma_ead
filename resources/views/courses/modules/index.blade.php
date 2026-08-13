@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$course->title" title="Módulos">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
            <x-ui.button href="{{ route('courses.modules.create', $course) }}" dusk="new-module">Novo Módulo</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <p class="form-text mb-3">
        Arraste os módulos para reordená-los. A nova ordem é salva automaticamente.
    </p>

    <div id="module-list-container" dusk="module-list-container">
        @include('courses.modules._list', ['course' => $course, 'modules' => $modules])
    </div>
@endsection
