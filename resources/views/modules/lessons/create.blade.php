@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $module->course->title, 'url' => route('courses.modules.index', $module->course)], ['label' => $module->title, 'url' => route('modules.lessons.index', $module)], ['label' => 'Nova Lição']]"
        :kicker="$module->course->title.' / '.$module->title"
        title="Nova Lição"
        subtitle="Escolha o tipo de conteúdo; os campos abaixo mudam conforme a escolha."
    />

    <x-ui.card>
        <form method="POST" action="{{ route('modules.lessons.store', $module) }}" enctype="multipart/form-data" dusk="lesson-form" data-lesson-form>
            @csrf

            @include('modules.lessons._form')

            <x-ui.form-actions>
                <x-ui.button type="submit" dusk="lesson-submit">Criar Lição</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('modules.lessons.index', $module) }}">Cancelar</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.card>
@endsection
