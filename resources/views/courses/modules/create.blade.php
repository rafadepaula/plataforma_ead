@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title, 'url' => route('courses.modules.index', $course)], ['label' => 'Novo Módulo']]"
        :kicker="$course->title"
        title="Novo Módulo"
        subtitle="Módulos agrupam as lições na ordem em que o aluno vai percorrer o curso."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('courses.modules.store', $course) }}" dusk="module-form">
            @csrf

            @include('courses.modules._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="module-submit">Criar Módulo</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $course) }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
