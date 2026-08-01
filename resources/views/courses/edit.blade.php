@extends('layouts.app')

@section('content')
    <x-ui.card title="Editar Curso" kicker="Cursos">
        <form method="POST" action="{{ route('courses.update', $course) }}" dusk="course-form">
            @csrf
            @method('PUT')

            @include('courses._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="course-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
