@extends('layouts.app')

@section('content')
    <x-ui.card title="Novo Curso" kicker="Cursos">
        <form method="POST" action="{{ route('courses.store') }}" dusk="course-form">
            @csrf

            @include('courses._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="course-submit">Criar Curso</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
