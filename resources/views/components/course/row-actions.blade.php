@props([
    'course',
    'manageModulesDusk' => null,
    'manageCompletionRulesDusk' => null,
    'editCourseDusk' => null,
    'deleteCourseDusk' => null,
])

@php
    $activeStudentsCount = (int) $course->active_students_count;
    $manageModulesDusk ??= 'manage-modules-'.$course->id;
    $manageCompletionRulesDusk ??= 'manage-completion-rules-'.$course->id;
    $editCourseDusk ??= 'edit-course-'.$course->id;
    $deleteCourseDusk ??= 'delete-course-'.$course->id;
@endphp

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap align-items-center justify-content-end gap-2']) }}>
    <x-ui.button variant="tonal"
                 size="sm"
                 :href="route('courses.modules.index', $course)"
                 :dusk="$manageModulesDusk">Módulos</x-ui.button>

    <x-ui.button variant="ghost"
                 size="sm"
                 :href="route('courses.completion-rules.index', $course)"
                 :dusk="$manageCompletionRulesDusk">Regras de conclusão</x-ui.button>

    <x-ui.button variant="ghost"
                 size="sm"
                 :href="route('courses.edit', $course)"
                 :dusk="$editCourseDusk">Editar</x-ui.button>

    <div class="d-flex align-items-center gap-2">
        @if ($activeStudentsCount > 0)
            <x-ui.button variant="ghost"
                         size="sm"
                         icon="trash"
                         disabled
                         aria-label="Remover {{ $course->title }}"
                         :dusk="$deleteCourseDusk"><span class="visually-hidden">Remover</span></x-ui.button>

            <span class="ds-caption text-body-secondary">
                {{ $activeStudentsCount }} {{ $activeStudentsCount === 1 ? 'aluno matriculado' : 'alunos matriculados' }}
            </span>
        @else
            <x-ui.button variant="ghost"
                         size="sm"
                         icon="trash"
                         data-bs-toggle="modal"
                         data-bs-target="#delete-course-{{ $course->id }}"
                         aria-label="Remover {{ $course->title }}"
                         :dusk="$deleteCourseDusk"><span class="visually-hidden">Remover</span></x-ui.button>
        @endif
    </div>
</div>
