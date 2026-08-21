@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => 'Novo Curso']]"
        kicker="Cursos"
        title="Novo Curso"
        subtitle="Defina título, descrição e status. Os módulos e as lições vêm depois."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('courses.store') }}" dusk="course-form">
            @csrf

            @include('courses._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="course-submit">Criar Curso</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
