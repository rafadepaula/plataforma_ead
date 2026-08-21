@php
    /**
     * @var \App\Models\Quiz $quiz
     * @var \Illuminate\Support\Collection<int, \App\Models\QuizQuestion> $questions
     */
    $typeLabels = [
        'single_choice' => 'Única escolha',
        'multiple_choice' => 'Múltipla escolha',
        'true_false' => 'Verdadeiro ou Falso',
        'essay' => 'Dissertativa',
    ];
@endphp

{{--
    Reorderable list mirroring `courses/modules/_list.blade.php` /
    `modules/lessons/index.blade.php`'s `[data-reorder-url]` contract — no
    dedicated JS module needed here, the existing `ModuleReorder.js`
    already binds to any list carrying that attribute (see
    `quizzes-conventions`).
--}}
<ul data-reorder-url="{{ route('quiz-questions.reorder', $quiz) }}"
    dusk="question-list"
    class="list-group list-unstyled m-0 p-0 d-flex flex-column gap-2">
    @forelse($questions as $question)
        <li data-id="{{ $question->id }}"
            dusk="question-row-{{ $question->id }}"
            draggable="true"
            class="list-group-item sortable-item d-flex align-items-center justify-content-between gap-3">
            <span class="d-flex align-items-center gap-2 min-w-0">
                <x-ui.icon name="grip-vertical" size="20" aria-hidden="true" class="drag-handle" />
                <span class="text-truncate max-w-420">{{ $question->question_text }}</span>
                <x-ui.badge variant="outline">{{ $typeLabels[$question->type] ?? $question->type }}</x-ui.badge>
            </span>

            <span class="d-flex gap-2 flex-shrink-0">
                <x-ui.button
                    variant="secondary"
                    data-bs-toggle="modal" data-bs-target="#question-edit-modal-{{ $question->id }}"
                    dusk="edit-question-{{ $question->id }}"
                >Editar</x-ui.button>

                <form method="POST" action="{{ route('quiz-questions.destroy', $question) }}" dusk="delete-question-form-{{ $question->id }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" dusk="delete-question-{{ $question->id }}">Remover</x-ui.button>
                </form>
            </span>
        </li>
    @empty
        <li class="list-group-item border-dashed text-center text-body-secondary py-4" dusk="question-list-empty">
            Nenhuma Questão cadastrada.
        </li>
    @endforelse
</ul>
