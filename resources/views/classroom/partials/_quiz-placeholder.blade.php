{{--
    quiz lessons hand off to the dedicated
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
    <div class="p-5 text-center border border-dashed" dusk="quiz-placeholder">
        <p class="mb-4">Esta lição é um Quiz de avaliação.</p>
        <x-ui.button href="{{ route('student.quizzes.show', $lesson) }}" dusk="start-quiz">Iniciar Quiz</x-ui.button>
    </div>
@else
    <div class="p-5 text-center text-body-secondary border border-dashed" dusk="quiz-placeholder">
        <p class="mb-0">Este conteúdo é um Quiz. A funcionalidade de Quiz estará disponível em breve.</p>
    </div>
@endif
