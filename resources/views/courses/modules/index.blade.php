@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">{{ $course->title }}</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Módulos</h1>
        </div>

        <div style="display: flex; gap: 8px;">
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
            <x-ui.button href="{{ route('courses.modules.create', $course) }}" dusk="new-module">Novo Módulo</x-ui.button>
        </div>
    </div>

    <p style="font-size: 12px; color: var(--color-neutral-600); margin-bottom: 12px;">
        Arraste os módulos para reordená-los. A nova ordem é salva automaticamente.
    </p>

    <div id="module-list-container" dusk="module-list-container">
        @include('courses.modules._list', ['course' => $course, 'modules' => $modules])
    </div>
@endsection
