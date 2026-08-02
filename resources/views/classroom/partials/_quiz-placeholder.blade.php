{{--
    SPEC-07 RF20 — quiz lessons are out of scope for this spec (SPEC-08's
    future `SubmitQuizAttemptAction` owns quiz completion). No "Marcar como
    concluída" affordance is rendered here — `lessons.complete` /
    `lessons.progress` both reject `type === 'quiz'` with a 422.
--}}

<div style="padding: 24px; text-align: center; color: var(--color-neutral-600); border: 1px dashed var(--color-divider);" dusk="quiz-placeholder">
    <p style="margin: 0;">Este conteúdo é um Quiz. A funcionalidade de Quiz estará disponível em breve.</p>
</div>
