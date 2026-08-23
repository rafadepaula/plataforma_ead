@props([
    'course',
])

@php
    $activeStudentsCount = (int) $course->active_students_count;
@endphp

<x-ui.card surface="white">
    <div>
        <x-ui.badge :variant="$course->is_published ? 'success' : 'neutral'">
            {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
        </x-ui.badge>
    </div>

    <h3 class="card-title fs-6 fw-semibold mb-1 mt-2">
        {{ $course->title }}
    </h3>

    <p class="ds-caption text-body-secondary mb-0">
        @if ((int) $course->modules_count === 0)
            Sem módulos cadastrados
        @else
            {{ $course->modules_count }} {{ Str::plural('módulo', $course->modules_count) }} · {{ $course->lessons_count }} {{ Str::plural('aula', $course->lessons_count) }}
        @endif
        · {{ $course->workload_hours }} {{ Str::plural('hora', $course->workload_hours) }} · {{ (int) $course->students_count === 0 ? 'nenhum aluno' : $course->students_count.' '.Str::plural('aluno', $course->students_count) }}
    </p>

    <x-slot:footer>
        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
            <x-ui.button variant="tonal" size="sm" :href="route('courses.modules.index', $course)">Módulos</x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('courses.completion-rules.index', $course)">Regras</x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('courses.edit', $course)">Editar</x-ui.button>
            @if ($activeStudentsCount > 0)
                <x-ui.button variant="ghost" size="sm" icon="trash" disabled aria-label="Remover {{ $course->title }}"><span class="visually-hidden">Remover</span></x-ui.button>
            @else
                <x-ui.button variant="ghost" size="sm" icon="trash" data-bs-toggle="modal" data-bs-target="#delete-course-{{ $course->id }}" aria-label="Remover {{ $course->title }}"><span class="visually-hidden">Remover</span></x-ui.button>
            @endif
        </div>
    </x-slot:footer>
</x-ui.card>
