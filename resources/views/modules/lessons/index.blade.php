@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $module->course->title, 'url' => route('courses.modules.index', $module->course)], ['label' => $module->title]]"
        :kicker="$module->course->title.' / '.$module->title"
        title="Lições"
        subtitle="Arraste as lições para reordená-las. A nova ordem é salva automaticamente."
    >
        <x-slot:actions>
            <x-ui.button variant="tonal" href="{{ route('courses.modules.index', $module->course) }}">Voltar aos Módulos</x-ui.button>
            <x-ui.button href="{{ route('modules.lessons.create', $module) }}" dusk="new-lesson">Nova Lição</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.sortable-list :reorder-url="route('lessons.reorder', $module)" dusk="lesson-list">
        @forelse($lessons as $lesson)
            <x-ui.sortable-row :id="$lesson->id" :title="$lesson->title" :muted="!$lesson->is_published" dusk="lesson-row-{{ $lesson->id }}">
                <x-slot:chips>
                    @if($lesson->type === 'quiz')
                        <span class="ds-chip ds-chip-primary ds-chip-plain">Quiz</span>
                    @else
                        <span class="ds-chip ds-chip-outline ds-chip-plain">Conteúdo</span>
                    @endif

                    @unless($lesson->is_published)
                        <span class="ds-chip ds-chip-plain ds-tone-neutral">Não publicada</span>
                    @endunless
                </x-slot:chips>

                <x-slot:actions>
                    <x-ui.button variant="ghost" href="{{ route('lessons.edit', $lesson) }}" dusk="edit-lesson-{{ $lesson->id }}">Editar</x-ui.button>

                    <x-ui.button variant="ghost"
                                 size="sm"
                                 icon="trash"
                                 data-bs-toggle="modal"
                                 data-bs-target="#delete-lesson-modal-{{ $lesson->id }}"
                                 dusk="open-delete-lesson-{{ $lesson->id }}"
                                 aria-label="Remover lição {{ $lesson->title }}" />
                </x-slot:actions>
            </x-ui.sortable-row>
        @empty
            <li class="list-group-item border-dashed text-center text-body-secondary py-4">
                Nenhuma Lição cadastrada.
            </li>
        @endforelse
    </x-ui.sortable-list>

    {{-- Modais fora da lista: arrastar um `<li>` não pode carregar o backdrop junto. --}}
    @foreach($lessons as $lesson)
        <x-ui.confirm-modal id="delete-lesson-modal-{{ $lesson->id }}"
                            title="Remover lição"
                            :action="route('lessons.destroy', $lesson)"
                            method="DELETE"
                            confirm-label="Remover lição"
                            :message="'Remover a lição “'.$lesson->title.'” é permanente. Esta ação não poderá ser desfeita.'"
                            form-dusk="delete-lesson-form-{{ $lesson->id }}"
                            confirm-dusk="delete-lesson-{{ $lesson->id }}" />
    @endforeach
@endsection
