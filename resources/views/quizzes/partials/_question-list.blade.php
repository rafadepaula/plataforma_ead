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
    style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
    @forelse($questions as $question)
        <li data-id="{{ $question->id }}"
            dusk="question-row-{{ $question->id }}"
            draggable="true"
            style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); cursor: grab;">
            <span style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                <span aria-hidden="true" style="opacity: 0.5;">⠿</span>
                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 420px;">{{ $question->question_text }}</span>
                <x-ui.badge variant="outline">{{ $typeLabels[$question->type] ?? $question->type }}</x-ui.badge>
            </span>

            <span style="display: flex; gap: 8px; flex-shrink: 0;">
                <x-ui.button
                    variant="secondary"
                    size="sm"
                    data-modal-target="question-edit-modal-{{ $question->id }}"
                    dusk="edit-question-{{ $question->id }}"
                >Editar</x-ui.button>

                <form method="POST" action="{{ route('quiz-questions.destroy', $question) }}" dusk="delete-question-form-{{ $question->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost" dusk="delete-question-{{ $question->id }}">Remover</button>
                </form>
            </span>
        </li>
    @empty
        <li style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600); border: 1px dashed var(--color-divider);" dusk="question-list-empty">
            Nenhuma Questão cadastrada.
        </li>
    @endforelse
</ul>
