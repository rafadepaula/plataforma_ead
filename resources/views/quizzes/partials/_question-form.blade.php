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

    <div style="display: flex; flex-direction: column; gap: 16px;">
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

        <p data-essay-hint="{{ $formSuffix }}" style="font-size: 12px; color: var(--color-neutral-600); margin: -8px 0 0; display: none;">
            Questões dissertativas não têm opções — a resposta do aluno é um texto livre corrigido manualmente pelo Gestor.
        </p>

        <div data-options-container="{{ $formSuffix }}" style="display: flex; flex-direction: column; gap: 10px;">
            <span style="font-size: 13px; font-weight: 600; color: var(--color-text);">Opções</span>

            <div data-options-list="{{ $formSuffix }}" style="display: flex; flex-direction: column; gap: 8px;">
                @forelse($existingOptions as $i => $option)
                    <div class="quiz-option-row" data-option-row data-option-id="{{ $option->id }}"
                         style="display: flex; align-items: center; gap: 8px;">
                        <input type="hidden" name="options[{{ $i }}][id]" value="{{ $option->id }}" />
                        <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1"
                               @checked($option->is_correct) data-correct-checkbox
                               style="width: 16px; height: 16px; flex-shrink: 0;" dusk="option-correct-{{ $formSuffix }}-{{ $i }}" />
                        <input type="text" name="options[{{ $i }}][option_text]" value="{{ $option->option_text }}"
                               {{ $isTrueFalse ? 'readonly' : '' }}
                               placeholder="Texto da opção"
                               style="flex: 1; border-radius: 0px; height: 36px; padding: 6px 10px; font-size: 13px; background: var(--color-surface); border: 1px solid var(--color-divider); color: var(--color-text);"
                               dusk="option-text-{{ $formSuffix }}-{{ $i }}" />
                        <button type="button" class="btn btn-ghost" data-remove-option-btn style="padding: 4px 8px;" dusk="remove-option-{{ $formSuffix }}-{{ $i }}">✕</button>
                    </div>
                @empty
                    {{-- Blank forms start with the minimum 2 rows every single_choice/true_false/multiple_choice question needs. --}}
                    @for($i = 0; $i < 2; $i++)
                        <div class="quiz-option-row" data-option-row style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="options[{{ $i }}][is_correct]" value="1" data-correct-checkbox
                                   style="width: 16px; height: 16px; flex-shrink: 0;" dusk="option-correct-{{ $formSuffix }}-{{ $i }}" />
                            <input type="text" name="options[{{ $i }}][option_text]"
                                   value="{{ $isTrueFalse ? ($i === 0 ? 'Verdadeiro' : 'Falso') : '' }}"
                                   {{ $isTrueFalse ? 'readonly' : '' }}
                                   placeholder="Texto da opção"
                                   style="flex: 1; border-radius: 0px; height: 36px; padding: 6px 10px; font-size: 13px; background: var(--color-surface); border: 1px solid var(--color-divider); color: var(--color-text);"
                                   dusk="option-text-{{ $formSuffix }}-{{ $i }}" />
                            <button type="button" class="btn btn-ghost" data-remove-option-btn style="padding: 4px 8px;" dusk="remove-option-{{ $formSuffix }}-{{ $i }}">✕</button>
                        </div>
                    @endfor
                @endforelse
            </div>

            <div>
                <x-ui.button type="button" variant="secondary" size="sm" data-add-option-btn="{{ $formSuffix }}" dusk="add-option-{{ $formSuffix }}">
                    + Adicionar Opção
                </x-ui.button>
            </div>

            {{-- Cloned by `QuizBuilder.addOption()` — kept as an inert `<template>` so it is never itself submitted. --}}
            <template data-option-template="{{ $formSuffix }}">
                <div class="quiz-option-row" data-option-row style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="options[__INDEX__][is_correct]" value="1" data-correct-checkbox
                           style="width: 16px; height: 16px; flex-shrink: 0;" />
                    <input type="text" name="options[__INDEX__][option_text]" placeholder="Texto da opção"
                           style="flex: 1; border-radius: 0px; height: 36px; padding: 6px 10px; font-size: 13px; background: var(--color-surface); border: 1px solid var(--color-divider); color: var(--color-text);" />
                    <button type="button" class="btn btn-ghost" data-remove-option-btn style="padding: 4px 8px;">✕</button>
                </div>
            </template>
        </div>

        <div style="display: flex; gap: 12px; margin-top: 8px;">
            <x-ui.button type="submit" dusk="question-submit-{{ $formSuffix }}">Salvar Questão</x-ui.button>
            <x-ui.button type="button" variant="secondary" data-modal-dismiss="true">Cancelar</x-ui.button>
        </div>
    </div>
</form>
