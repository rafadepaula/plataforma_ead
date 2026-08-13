@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$module->course->title.' / '.$module->title" title="Nova Lição" />

    <x-ui.card>
        <form method="POST" action="{{ route('modules.lessons.store', $module) }}" enctype="multipart/form-data" dusk="lesson-form">
            @csrf

            @include('modules.lessons._form')

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-ui.button type="submit" dusk="lesson-submit">Criar Lição</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('modules.lessons.index', $module) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
