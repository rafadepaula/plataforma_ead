@php
    /**
     * @var \App\Models\Lesson $lesson
     */
    $type = old('type', $lesson->type ?? 'content');

    // Anexos já persistidos alimentam a lista de remoção da dropzone. O
    // `method_exists` é ponte: enquanto a relação `media()` não existir no
    // modelo, a dropzone apenas não lista anexos antigos.
    $mediaAttachments = $lesson->exists && method_exists($lesson, 'media')
        ? $lesson->media
        : collect();

    $imageAttachments = $mediaAttachments->where('kind', 'image')->values();
    $pdfAttachments = $mediaAttachments->where('kind', 'pdf')->values();

    $isPublished = old('is_published', $lesson->is_published);
    $publishHint = $isPublished
        ? 'A lição fica visível para os alunos imediatamente após salvar.'
        : 'A lição continua oculta para os alunos até ser publicada.';

    // Quiz authoring state — the lesson form is THE surface for quiz
    // authoring (mesma experiência do tipo "Conteúdo"): as regras e as
    // questões vivem aqui e são salvas no MESMO submit da lição, via
    // `SaveQuizForLessonAction`. Repopulação em erro de validação: o
    // builder inteiro (regras + questões + opções) vem de old().
    $quiz = $lesson->quiz;

    $quizInstructions = old('quiz.instructions', $quiz?->instructions);
    $quizMinScore = old('quiz.min_score_percentage', $quiz?->min_score_percentage ?? 70);
    $quizMaxAttempts = old('quiz.max_attempts', $quiz?->max_attempts);
    $quizTimeLimit = old('quiz.time_limit_minutes', $quiz?->time_limit_minutes);

    // O `quiz.allow_retries` desmarcado NÃO chega no old() (checkbox), então
    // um hidden `value="0"` antes do componente preserva o estado anterior.
    $quizAllowRetries = old('quiz.allow_retries', $quiz?->allow_retries ?? true);
    $quizShowCorrectAnswers = old('quiz.show_correct_answers', $quiz?->show_correct_answers ?? false);

    // Em erro de validação, `old('questions')` é o array enviado — renderiza
    // os blocos exatamente como o autor deixou (inclusive ids persistidos);
    // sem erro, os blocos vêm das questões persistidas do quiz 1:1.
    $oldQuestions = old('questions');
    $quizQuestions = is_array($oldQuestions)
        ? collect($oldQuestions)
        : $quiz?->questions->map(fn ($question): array => [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'type' => $question->type,
            'options' => $question->options->map(fn ($option): array => [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'is_correct' => $option->is_correct,
            ])->all(),
        ])->values()
          ?? collect();
@endphp

<x-ui.field-stack class="max-w-640">
    <x-ui.input
        name="title"
        label="Título"
        required
        value="{{ $lesson->title }}"
    />

    <div>
        <x-ui.select
            name="type"
            label="Tipo de Conteúdo"
            required
            :options="['content' => 'Conteúdo', 'quiz' => 'Quiz']"
            :selected="$type"
            dusk="lesson-type-select"
            data-lesson-type-select
        />
    </div>

    <div id="lesson-content-fields" data-lesson-content-fields class="ds-stack">
        <x-ui.input
            type="textarea"
            name="content_text"
            label="Texto (Rich Text)"
            value="{{ $lesson->content_text }}"
        />

        <x-ui.file-drop
            name="images"
            label="Imagens"
            accept="image/*"
            :max-size="2"
            hint="PNG, JPG ou WebP"
            dusk="lesson-image-input"
            :attachments="$imageAttachments"
        />

        <x-ui.file-drop
            name="pdfs"
            label="PDFs"
            accept="application/pdf"
            :max-size="10"
            dusk="lesson-pdf-input"
            :attachments="$pdfAttachments"
        />

        <x-ui.video-field
            :value="$lesson->video_url"
            :provider="$lesson->video_provider"
            label="URL do vídeo"
            hint="O servidor revalida o link no envio: apenas vídeos do YouTube ou Vimeo são aceitos. Use vídeo Público ou Não listado — vídeo Privado do YouTube não reproduz em player incorporado."
            dusk="lesson-video-input"
            preview-dusk="video-preview"
        />
    </div>

    {{--
        Seção do quiz — mostrada/oculta pelo mesmo switch de tipo que os
        campos de conteúdo (`LessonForm.js`). O título do quiz NÃO é um
        campo aqui: ele é mantido em sincronia com o Título da lição no
        servidor (`SaveQuizForLessonAction`), pois a tela tem um único
        campo de título.
    --}}
    <div id="lesson-quiz-fields" data-lesson-quiz-fields class="ds-stack d-none" dusk="lesson-quiz-section">
        <x-ui.textarea
            name="quiz[instructions]"
            label="Instruções"
            :value="$quizInstructions"
            dusk="quiz-instructions"
        />

        <div class="row g-3">
            <div class="col-sm-6">
                <x-ui.input
                    type="number"
                    name="quiz[min_score_percentage]"
                    label="Nota mínima para aprovação (%)"
                    required
                    min="0"
                    max="100"
                    :value="$quizMinScore"
                    dusk="quiz-min-score"
                />
            </div>
            <div class="col-sm-6">
                <x-ui.input
                    type="number"
                    name="quiz[max_attempts]"
                    label="Máximo de tentativas"
                    hint="Deixe em branco para tentativas ilimitadas."
                    :value="$quizMaxAttempts"
                    dusk="quiz-max-attempts"
                />
            </div>
            <div class="col-sm-6">
                <x-ui.input
                    type="number"
                    name="quiz[time_limit_minutes]"
                    label="Limite de tempo (minutos)"
                    hint="Deixe em branco para sem limite. Envios após o limite são aceitos, mas marcados como reprovados."
                    :value="$quizTimeLimit"
                    dusk="quiz-time-limit"
                />
            </div>
        </div>

        <div>
            <input type="hidden" name="quiz[allow_retries]" value="0" />
            <x-ui.checkbox
                name="quiz[allow_retries]"
                label="Permitir novas tentativas"
                :checked="(bool) $quizAllowRetries"
                dusk="quiz-allow-retries"
            />
        </div>

        <div>
            <input type="hidden" name="quiz[show_correct_answers]" value="0" />
            <x-ui.checkbox
                name="quiz[show_correct_answers]"
                label="Exibir gabarito ao aluno após envio"
                :checked="(bool) $quizShowCorrectAnswers"
                dusk="quiz-show-correct-answers"
            />
        </div>

        <div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
                <h2 class="h5 mb-0">Questões</h2>
                <x-ui.button type="button" variant="secondary" data-add-question dusk="add-lesson-question">+ Adicionar Questão</x-ui.button>
            </div>
            <p class="small text-body-secondary mb-3">
                Use ↑ e ↓ para reordenar as questões. A ordem definida aqui é a ordem aplicada ao salvar.
            </p>

            <div data-lesson-questions class="d-flex flex-column gap-3" dusk="lesson-questions-list">
                @foreach($quizQuestions as $questionIndex => $question)
                    @include('modules.lessons.partials._quiz-question', ['qIndex' => $questionIndex, 'question' => $question])
                @endforeach
            </div>

            {{-- Cloned by `LessonQuizBuilder.addQuestion()` — inert `<template>`, never submitted. --}}
            <template data-lesson-question-template>
                @include('modules.lessons.partials._quiz-question', [
                    'qIndex' => '__QI__',
                    'question' => ['type' => 'single_choice', 'options' => []],
                ])
            </template>
        </div>
    </div>

    <div>
        <x-ui.switch
            name="is_published"
            label="Publicado"
            :checked="$lesson->is_published"
        />
        <p class="form-text" data-publish-hint>{{ $publishHint }}</p>
    </div>
</x-ui.field-stack>
