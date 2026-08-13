{{--
    SPEC-08 §2.1 — Gestor's manual-grading queue: every `QuizAttempt` with
    `status = awaiting_manual_grading`, scoped to the Gestor's own Org (via
    `QuizAttemptPolicy`/the controller query — see `quizzes-conventions`).

    Expected `EssayGradingController@pending` contract (Bucket 2):
      - `$attempts`  paginated, with `quiz.lesson.module.course` and `user`
                     eager-loaded, ordered oldest-first (`completed_at asc`)
                     so the queue is worked FIFO.
      - route: `GET route('quiz-attempts.pending')`.
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="SPEC-08" title="Correções Pendentes" />

    <x-ui.table :headers="['Aluno', 'Curso / Quiz', 'Enviado em', 'Ações']">
        @forelse($attempts as $attempt)
            <tr dusk="pending-attempt-row-{{ $attempt->id }}">
                <td>{{ $attempt->user->name }}</td>
                <td>
                    {{ $attempt->quiz->lesson->module->course->title }}
                    <br>
                    <span class="small text-body-secondary">{{ $attempt->quiz->title }}</span>
                </td>
                <td>{{ optional($attempt->completed_at)->format('d/m/Y H:i') }}</td>
                <td>
                    <x-ui.button variant="secondary" size="sm" href="{{ route('quiz-attempts.show', $attempt) }}" dusk="grade-attempt-{{ $attempt->id }}">
                        Corrigir
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <x-ui.empty-state :colspan="4" dusk="pending-attempts-empty">
                Nenhuma correção pendente.
            </x-ui.empty-state>
        @endforelse
    </x-ui.table>

    <x-ui.pagination :paginator="$attempts" />
@endsection
