@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title, 'url' => route('courses.modules.index', $course)], ['label' => 'Editar']]"
        kicker="Cursos"
        title="Editar Curso"
        subtitle="As alterações valem para todos os alunos já matriculados."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('courses.update', $course) }}" dusk="course-form">
            @csrf
            @method('PUT')

            @include('courses._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="course-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
