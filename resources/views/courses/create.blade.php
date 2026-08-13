@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Cursos" title="Novo Curso" />

    <x-ui.card>
        <form method="POST" action="{{ route('courses.store') }}" dusk="course-form">
            @csrf

            @include('courses._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="course-submit">Criar Curso</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
