---
name: quizzes-maintenance
description: >
  Quizzes/Evaluations: quizzes/quiz_questions/quiz_options/quiz_attempts/quiz_answers
  schema, cascade-inherited tenancy through Lesson, correction engine branching
  automatic vs essay manual grading, max_attempts/time_limit enforcement,
  `OpenQuizAttemptAction` and the `open_slot` invariant, single-page quiz-taking form,
  `SaveQuizForLessonAction`, `QuizBuilder.js`/`QuizTaking.js` contracts. Use when
  designing or reviewing features touching Quiz/QuizQuestion/QuizOption/QuizAttempt/
  QuizAnswer data, before adding a new question type, when writing controller/Policy/
  Form Request/Blade/JS for quiz authoring, quiz-taking or essay-grading screens, or
  when `SubmitQuizAttemptActionTest`, `StudentQuizControllerTest`,
  `QuizAttemptLimitsTest`, `EssayManualGradingTest` or `QuizManagementTest` fails, a
  submission scores unexpectedly, or an essay attempt will not leave
  `awaiting_manual_grading`.
license: MIT
metadata:
  feature: quizzes
  roles: [architecture, conventions, maintenance]
---

# Quizzes & Evaluations (`quizzes-maintenance`)

Quizzes/Evaluations: `quizzes`/`quiz_questions`/`quiz_options`/`quiz_attempts`/`quiz_answers` schema, cascade-inherited tenancy through Lesson, correction engine (automatic vs essay manual grading), `OpenQuizAttemptAction` owning the `in_progress` attempt and the `open_slot` unique-index invariant.

The detailed knowledge for this module lives in the reference files below
— read only the one the task needs:

| Reference | Read when |
| --- | --- |
| `resource/architecture.md` | Designing or reviewing features touching Quiz/QuizQuestion/QuizOption/QuizAttempt/QuizAnswer data, before adding a new question type, or when deciding how quiz-taking or essay-grading screens get scoped and gated. |
| `resource/conventions.md` | Writing controller, Policy, Form Request, Blade view, or JS module managing `Quiz`/`QuizQuestion`/`QuizOption` records or rendering Aluno quiz-taking or Gestor essay-grading screens. |
| `resource/maintenance.md` | `SubmitQuizAttemptActionTest`, `StudentQuizControllerTest`, `QuizAttemptLimitsTest`, `EssayManualGradingTest`, or `QuizManagementTest` fails; quiz submission scores unexpectedly; essay attempt will not leave `awaiting_manual_grading`; options UI does not hide for essay question. |
