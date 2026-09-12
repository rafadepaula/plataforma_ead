@php
    /**
     * One question block of the quiz builder embedded in the Lesson form
     * (`modules/lessons/_form.blade.php`). Rendered once per persisted /
     * old() question and once inside the inert `<template>` cloned by
     * `LessonQuizBuilder.addQuestion()` — in the template the indexes are
     * the `__QI__`/`__OI__` placeholders, replaced (along with every other
     * `questions[...]`/`options[...]` name on the page) by the builder's
     * full reindex pass after any add/remove/move.
     *
     * @var int|string $qIndex   integer for rendered rows, '__QI__' in the template.
     * @var array{id?: int|string|null, question_text?: string, type?: string, options?: array<int, array{id?: int|string|null, option_text?: string, is_correct?: bool}>} $question
     *
     * Payload contract (see `SaveQuizForLessonAction`):
     *   questions[i][id]           persisted QuizQuestion id (blank = new).
     *   questions[i][question_text]
     *   questions[i][type]         single_choice|multiple_choice|true_false|essay.
     *   questions[i][options][j][id]          persisted QuizOption id (blank = new).
     *   questions[i][options][j][option_text]
     *   questions[i][options][j][is_correct]  checkbox, present only when checked.
     * Removing an option/question client-side just drops its row — the
     * server sync deletes whatever is no longer present. `essay` questions
     * have their options UI hidden client-side and their options ignored
     * (and wiped) server-side.
     */
    $qIndex = $qIndex;
    $questionId = $question['id'] ?? '';
    $questionText = $question['question_text'] ?? '';
    $questionType = $question['type'] ?? 'single_choice';
    $isTrueFalse = $questionType === 'true_false';
    $isEssay = $questionType === 'essay';
    $options = $question['options'] ?? [];
@endphp

<div class="card" data-lesson-question>
    <div class="card-body ds-stack">
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-light" data-lesson-question-position>{{ is_int($qIndex) ? $qIndex + 1 : '#' }}</span>
            <span class="ms-auto d-flex gap-1">
                <x-ui.button type="button" variant="ghost" size="sm" data-move-question-up aria-label="Mover questão para cima">↑</x-ui.button>
                <x-ui.button type="button" variant="ghost" size="sm" data-move-question-down aria-label="Mover questão para baixo">↓</x-ui.button>
                <x-ui.button type="button" variant="ghost" size="sm" data-remove-question aria-label="Remover questão">✕</x-ui.button>
            </span>
        </div>

        <input type="hidden" name="questions[{{ $qIndex }}][id]" value="{{ $questionId }}" />

        <x-ui.input
            type="textarea"
            name="questions[{{ $qIndex }}][question_text]"
            label="Enunciado"
            required
            :value="$questionText"
        />

        <x-ui.select
            name="questions[{{ $qIndex }}][type]"
            label="Tipo de Questão"
            required
            :options="[
                'single_choice' => 'Única escolha',
                'multiple_choice' => 'Múltipla escolha',
                'true_false' => 'Verdadeiro ou Falso',
                'essay' => 'Dissertativa (correção manual)',
            ]"
            :selected="$questionType"
            data-lesson-question-type
        />

        <p data-lesson-essay-hint class="form-text mb-3 {{ $isEssay ? '' : 'd-none' }}">
            Questões dissertativas não têm opções — a resposta do aluno é um texto livre corrigido manualmente pelo Gestor.
        </p>

        <div data-lesson-options class="{{ $isEssay ? 'd-none' : '' }}">
            <span class="form-label d-block">Opções</span>

            <div data-options-list class="d-flex flex-column gap-2">
                @forelse($options as $option)
                    @include('modules.lessons.partials._quiz-option', [
                        'qIndex' => $qIndex,
                        'oIndex' => $loop->index,
                        'option' => $option,
                        'isTrueFalse' => $isTrueFalse,
                    ])
                @empty
                    @if(! $isEssay)
                        {{-- Blank blocks start with the minimum 2 rows every choice question needs. --}}
                        @for($i = 0; $i < 2; $i++)
                            @include('modules.lessons.partials._quiz-option', [
                                'qIndex' => $qIndex,
                                'oIndex' => $i,
                                'option' => [],
                                'isTrueFalse' => $isTrueFalse,
                                'trueFalseText' => $isTrueFalse ? ($i === 0 ? 'Verdadeiro' : 'Falso') : '',
                            ])
                        @endfor
                    @endif
                @endforelse
            </div>

            <div class="mt-2 {{ $isTrueFalse ? 'd-none' : '' }}" data-add-option-wrapper>
                <x-ui.button type="button" variant="secondary" data-add-option dusk="add-lesson-option">+ Adicionar Opção</x-ui.button>
            </div>

            {{-- Cloned by `LessonQuizBuilder.addOption()` — inert `<template>`, never submitted. --}}
            <template data-option-template>
                @include('modules.lessons.partials._quiz-option', [
                    'qIndex' => $qIndex,
                    'oIndex' => '__OI__',
                    'option' => [],
                    'isTrueFalse' => false,
                ])
            </template>
        </div>
    </div>
</div>
