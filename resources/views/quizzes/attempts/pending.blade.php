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
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">SPEC-08</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Correções Pendentes</h1>
        </div>
    </div>

    <x-ui.table :headers="['Aluno', 'Curso / Quiz', 'Enviado em', 'Ações']">
        @forelse($attempts as $attempt)
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="pending-attempt-row-{{ $attempt->id }}">
                <td style="padding: 12px 16px;">{{ $attempt->user->name }}</td>
                <td style="padding: 12px 16px;">
                    {{ $attempt->quiz->lesson->module->course->title }}
                    <br>
                    <span style="font-size: 12px; color: var(--color-neutral-600);">{{ $attempt->quiz->title }}</span>
                </td>
                <td style="padding: 12px 16px;">{{ optional($attempt->completed_at)->format('d/m/Y H:i') }}</td>
                <td style="padding: 12px 16px;">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('quiz-attempts.show', $attempt) }}" dusk="grade-attempt-{{ $attempt->id }}">
                        Corrigir
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);" dusk="pending-attempts-empty">
                    Nenhuma correção pendente.
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div style="margin-top: 20px;">
        {{ $attempts->links() }}
    </div>
@endsection
