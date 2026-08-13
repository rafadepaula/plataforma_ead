@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Cursos" title="Editar Curso" />

    <x-ui.card>
        <form method="POST" action="{{ route('courses.update', $course) }}" dusk="course-form">
            @csrf
            @method('PUT')

            @include('courses._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="course-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
