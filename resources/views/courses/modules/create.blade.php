@extends('layouts.app')

@section('content')
    <x-ui.card title="Novo Módulo" kicker="{{ $course->title }}">
        <form method="POST" action="{{ route('courses.modules.store', $course) }}" dusk="module-form">
            @csrf

            @include('courses.modules._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="module-submit">Criar Módulo</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $course) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
