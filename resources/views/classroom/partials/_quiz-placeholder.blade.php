{{--
    SPEC-07 RF20 / SPEC-08 RF09 — quiz lessons hand off to the dedicated
    student quiz-taking screen (`StudentQuizController@show`), which owns
    completion via `SubmitQuizAttemptAction`/`MarkLessonCompleteAction`
    (`completion_source = quiz_passed`). No "Marcar como concluída"
    affordance is rendered here — `lessons.complete`/`lessons.progress`
    both reject `type === 'quiz'` with a 422, same as before.

    A Lesson of `type = quiz` with no `Quiz` row yet (Gestor hasn't
    authored one) falls back to the original placeholder message instead
    of linking to a 404.
--}}

@if($lesson->quiz)
    <div style="padding: 24px; text-align: center; border: 1px dashed var(--color-divider);" dusk="quiz-placeholder">
        <p style="margin: 0 0 16px;">Esta lição é um Quiz de avaliação.</p>
        <x-ui.button href="{{ route('student.quizzes.show', $lesson) }}" dusk="start-quiz">Iniciar Quiz</x-ui.button>
    </div>
@else
    <div style="padding: 24px; text-align: center; color: var(--color-neutral-600); border: 1px dashed var(--color-divider);" dusk="quiz-placeholder">
        <p style="margin: 0;">Este conteúdo é um Quiz. A funcionalidade de Quiz estará disponível em breve.</p>
    </div>
@endif
