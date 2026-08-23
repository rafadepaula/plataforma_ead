@php
    /**
     * @var \App\Models\Quiz $quiz
     * @var \App\Models\QuizQuestion $question
     * @var string $formSuffix  unique per-form suffix (this partial is
     *      included once per modal on `quizzes/edit.blade.php` — "create"
     *      for the blank form, "edit-{id}" per existing question — so
     *      every `id`/`name` here must stay unique across the whole page).
     * @var string $action
     * @var string $method      'POST' (create) or 'PUT' (update).
     *
     * Contract with `StoreQuizQuestionRequest`/`UpdateQuizQuestionRequest`
     * (Bucket 1) and `QuizQuestionController` (Bucket 2), documented in
     * full in the `quizzes-conventions` skill:
     *   - `question_text`, `type` (single_choice|multiple_choice|
     *     true_false|essay), `order_index` is server-assigned (append at
     *     end), not submitted here.
     *   - `options[{i}][id]`     existing `QuizOption` id, blank for a new row.
     *   - `options[{i}][option_text]`
     *   - `options[{i}][is_correct]`  checkbox, present only when checked.
     *   - Removing an option client-side (`QuizBuilder.removeOption()`)
     *     just drops its row from the DOM — no separate "removed ids"
     *     field is submitted. `QuizQuestionController::update()` deletes
     *     whatever persisted option ids are no longer present in the
     *     submitted `options[]` array; everything else in `options[]` is
     *     an upsert.
     *   - Entirely skipped/ignored server-side when `type === 'essay'`
     *     (no `quiz_options` row makes sense for an essay question) — the
     *     options UI below is hidden client-side for the same reason, but
     *     the server must not trust that and must independently reject/
     *     ignore an `options` payload sent alongside `type=essay`.
     */
    $existingOptions = $question->relationLoaded('options') ? $question->options : collect();
    $isTrueFalse = $question->type === 'true_false';
@endphp

<form method="POST" action="{{ $action }}" dusk="question-form-{{ $formSuffix }}" data-question-form="{{ $formSuffix }}">
    @csrf
    @if($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <x-ui.input
            type="textarea"
            name="question_text"
            label="Enunciado"
            required
            value="{{ $question->question_text }}"
            dusk="question-text-{{ $formSuffix }}"
        />

        <x-ui.select
            name="type"
            label="Tipo de Questão"
            required
            :options="[
                'single_choice' => 'Única escolha',
                'multiple_choice' => 'Múltipla escolha',
                'true_false' => 'Verdadeiro ou Falso',
                'essay' => 'Dissertativa (correção manual)',
            ]"
            :selected="$question->type ?? 'single_choice'"
            data-question-type-select="{{ $formSuffix }}"
            dusk="question-type-{{ $formSuffix }}"
        />

        <p data-essay-hint="{{ $formSuffix }}" class="form-text mb-3 d-none">
            Questões dissertativas não têm opções — a resposta do aluno é um texto livre corrigido manualmente pelo Gestor.
        </p>

        <div data-options-container="{{ $formSuffix }}" class="mb-3">
            <span class="form-label d-block">Opções</span>

            <div data-options-list="{{ $formSuffix }}" class="d-flex flex-column gap-2">
                @forelse($existingOptions as $i => $option)
                    <div class="d-flex align-items-center gap-2 quiz-option-row{{ $option->is_correct ? ' is-correct' : '' }}" data-option-row data-option-id="{{ $option->id }}">
                        <input type="hidden" name="options[{{ $i }}][id]" value="{{ $option->id }}" />
                        <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1"
                               @checked($option->is_correct) data-correct-checkbox
                               class="form-check-input flex-shrink-0 m-0" dusk="option-correct-{{ $formSuffix }}-{{ $i }}" />
                        <input type="text" name="options[{{ $i }}][option_text]" value="{{ $option->option_text }}"
                               {{ $isTrueFalse ? 'readonly' : '' }}
                               placeholder="Texto da opção"
                               class="form-control form-control-sm flex-fill"
                               dusk="option-text-{{ $formSuffix }}-{{ $i }}" />
                        <x-ui.button type="button" variant="ghost" data-remove-option-btn dusk="remove-option-{{ $formSuffix }}-{{ $i }}">✕</x-ui.button>
                    </div>
                @empty
                    {{-- Blank forms start with the minimum 2 rows every single_choice/true_false/multiple_choice question needs. --}}
                    @for($i = 0; $i < 2; $i++)
                        <div class="d-flex align-items-center gap-2 quiz-option-row" data-option-row>
                            <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1" data-correct-checkbox
                                   class="form-check-input flex-shrink-0 m-0" dusk="option-correct-{{ $formSuffix }}-{{ $i }}" />
                            <input type="text" name="options[{{ $i }}][option_text]"
                                   value="{{ $isTrueFalse ? ($i === 0 ? 'Verdadeiro' : 'Falso') : '' }}"
                                   {{ $isTrueFalse ? 'readonly' : '' }}
                                   placeholder="Texto da opção"
                                   class="form-control form-control-sm flex-fill"
                                   dusk="option-text-{{ $formSuffix }}-{{ $i }}" />
                            <x-ui.button type="button" variant="ghost" data-remove-option-btn dusk="remove-option-{{ $formSuffix }}-{{ $i }}">✕</x-ui.button>
                        </div>
                    @endfor
                @endforelse
            </div>

            <div class="mt-2">
                <x-ui.button type="button" variant="secondary" data-add-option-btn="{{ $formSuffix }}" dusk="add-option-{{ $formSuffix }}">
                    + Adicionar Opção
                </x-ui.button>
            </div>

            {{-- Cloned by `QuizBuilder.addOption()` — kept as an inert `<template>` so it is never itself submitted. A freshly cloned row always starts unchecked/uncorrect, so no `is-correct` class here. --}}
            <template data-option-template="{{ $formSuffix }}">
                <div class="d-flex align-items-center gap-2 quiz-option-row" data-option-row>
                    <input type="checkbox" name="options[__INDEX__][is_correct]" value="1" data-correct-checkbox
                           class="form-check-input flex-shrink-0 m-0" />
                    <input type="text" name="options[__INDEX__][option_text]" placeholder="Texto da opção"
                           class="form-control form-control-sm flex-fill" />
                    <x-ui.button type="button" variant="ghost" data-remove-option-btn>✕</x-ui.button>
                </div>
            </template>
        </div>

        <div class="d-flex gap-3 mt-2">
            <x-ui.button type="submit" dusk="question-submit-{{ $formSuffix }}">Salvar Questão</x-ui.button>
            <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">Cancelar</x-ui.button>
        </div>
    </div>
</form>
