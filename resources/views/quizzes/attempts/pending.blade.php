{{--
    Gestor's manual-grading queue: every `QuizAttempt` with
    `status = awaiting_manual_grading`, scoped to the Gestor's own Org (via
    `QuizAttemptPolicy`/the controller query — see `quizzes-conventions`).

    `EssayGradingController@pending` contract:
      - `$attempts`  paginated, with `quiz.lesson.module.course` and `user`
                     eager-loaded, ordered oldest-first (`completed_at asc`)
                     so the queue is worked FIFO.
      - route: `GET route('quiz-attempts.pending')`.

    Material Bootstrap refactor: header count
    chip reuses `$attempts->total()` (the paginator's total, not a fresh
    un-paginated count query), avatar keeps the shared `x-ui.avatar`
    "sm" (32px) size — the design mock asks for 36px but the avatar
    component only ships 3 documented sizes (sm/lg/xl) and widening it is a
    cross-screen decision outside this bucket's file list, so "sm" stays as
    the closest existing alias rather than inventing an ad-hoc class here.
--}}
@extends('layouts.app')

@php
    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Avaliações', 'url' => route('quiz-attempts.pending')], ['label' => 'Correções Pendentes', 'url' => route('quiz-attempts.pending')]]"
        kicker="Avaliações"
        title="Correções Pendentes"
        subtitle="Tentativas com questões dissertativas aguardando correção manual, da mais antiga para a mais recente."
    >
        @if($attempts->total() > 0)
            <x-slot:actions>
                <x-ui.badge size="lg">{{ $attempts->total() }} na fila</x-ui.badge>
            </x-slot:actions>
        @endif
    </x-layout.page-header>

    <x-ui.table :headers="['Aluno', 'Curso / Quiz', 'Enviado em', 'Ações']">
        @forelse($attempts as $attempt)
            <tr dusk="pending-attempt-row-{{ $attempt->id }}">
                <td data-label="Aluno">
                    <div class="d-flex align-items-center gap-3">
                        <x-ui.avatar size="sm" :initials="$initialsFor($attempt->user->name)" aria-hidden="true" />
                        <div class="min-w-0">
                            <div class="fw-semibold">{{ $attempt->user->name }}</div>
                            <div class="ds-caption text-body-secondary text-truncate">{{ $attempt->user->email }}</div>
                        </div>
                    </div>
                </td>
                <td data-label="Curso / Quiz">
                    <span class="fw-semibold">{{ $attempt->quiz->lesson->module->course->title }}</span>
                    <br>
                    <span class="ds-caption text-body-secondary">{{ $attempt->quiz->title }}</span>
                </td>
                <td class="ds-tabular-nums" data-label="Enviado em">
                    {{ optional($attempt->completed_at)->format('d/m/Y H:i') }}
                    <div class="ds-caption text-body-secondary">{{ optional($attempt->completed_at)->diffForHumans() }}</div>
                </td>
                <td data-label="Ações">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('quiz-attempts.show', $attempt) }}" dusk="grade-attempt-{{ $attempt->id }}">
                        Corrigir
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <x-ui.empty-state
                :colspan="4"
                dusk="pending-attempts-empty"
                icon="check"
                tone="success"
                title="Nenhuma correção pendente"
                description="Quando um aluno enviar uma prova com questão dissertativa, ela aparece aqui." />
        @endforelse
    </x-ui.table>

    <x-ui.pagination :paginator="$attempts" />
@endsection
