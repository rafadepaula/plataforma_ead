@props([
    'course',
])

<x-ui.card surface="white" dusk="course-card-row-{{ $course->id }}">
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
        {{-- Desktop's `<tr>` renders the same `<x-course.row-actions>` with
             its default (unprefixed) dusk ids; this mobile copy prefixes
             them so both DOM instances stay individually addressable —
             `DuskSelectorContractTest`'s per-course-id uniqueness guard
             would otherwise see 2 elements for one dusk selector. --}}
        <x-course.row-actions :course="$course"
                              manage-modules-dusk="mobile-manage-modules-{{ $course->id }}"
                              manage-completion-rules-dusk="mobile-manage-completion-rules-{{ $course->id }}"
                              edit-course-dusk="mobile-edit-course-{{ $course->id }}"
                              delete-course-dusk="mobile-delete-course-{{ $course->id }}" />
    </x-slot:footer>
</x-ui.card>
