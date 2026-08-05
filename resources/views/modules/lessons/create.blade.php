@extends('layouts.app')

@section('content')
    <x-ui.card title="Nova Lição" kicker="{{ $module->course->title }} / {{ $module->title }}">
        <form method="POST" action="{{ route('modules.lessons.store', $module) }}" enctype="multipart/form-data" dusk="lesson-form">
            @csrf

            @include('modules.lessons._form')

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="lesson-submit">Criar Lição</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $module) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
