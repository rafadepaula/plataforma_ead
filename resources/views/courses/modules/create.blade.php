@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$course->title" title="Novo Módulo" />

    <x-ui.card>
        <form method="POST" action="{{ route('courses.modules.store', $course) }}" dusk="module-form">
            @csrf

            @include('courses.modules._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="module-submit">Criar Módulo</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $course) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
