@php
    /** @var array{taught_courses: int, pending_essays: int, pending_reports: int} $stats */
    /** @var \Illuminate\Support\Collection<int, \App\Models\QuizAttempt> $oldestEssays */
    /** @var array{topics: int, replies: int} $forumActivity */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Course> $quickAccessCourses */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <div dusk="professor-dashboard">
        <x-layout.page-header
            kicker="Ensino"
            title="Dashboard"
            subtitle="Estado dos cursos atribuídos a você: correções, denúncias e atividade do fórum."
        />

        {{-- Cards de estado --}}
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <x-ui.stat-card kicker="Cursos atribuídos" :value="$stats['taught_courses']" icon="book" dusk="stat-taught-courses" />
            </div>
            <div class="col-12 col-md-4">
                <x-ui.stat-card kicker="Correções pendentes" :value="$stats['pending_essays']" icon="clipboard" tone="secondary" dusk="stat-pending-essays" />
            </div>
            <div class="col-12 col-md-4">
                <x-ui.stat-card kicker="Denúncias pendentes" :value="$stats['pending_reports']" icon="shield" tone="neutral" dusk="stat-pending-reports" />
            </div>
        </div>

        <div class="row g-4">
            {{-- Fila "Correções mais antigas" --}}
            <div class="col-12 col-xl-7">
                <x-ui.card>
                    <x-slot:title>Correções mais antigas</x-slot:title>

                    <x-ui.data-table striped hover responsive
                                     :headers="['Aluno', 'Curso / Prova', 'Enviado em', '']">
                        @forelse($oldestEssays as $attempt)
                            <tr dusk="professor-dashboard-attempt-{{ $attempt->id }}">
                                <td data-label="Aluno">
                                    <div class="d-flex align-items-center gap-3">
                                        <x-ui.avatar size="sm" :initials="$initialsFor($attempt->user->name)" aria-hidden="true" />
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ $attempt->user->name }}</div>
                                            <div class="ds-caption text-body-secondary text-truncate">{{ $attempt->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Curso / Prova">
                                    <span class="fw-semibold">{{ $attempt->quiz->lesson->module->course->title }}</span>
                                    <br>
                                    <span class="ds-caption text-body-secondary">{{ $attempt->quiz->title }}</span>
                                </td>
                                <td class="text-nowrap ds-tabular-nums" data-label="Enviado em">
                                    {{ optional($attempt->completed_at)->format('d/m/Y') }}
                                    <div class="ds-caption text-body-secondary">{{ optional($attempt->completed_at)->diffForHumans() }}</div>
                                </td>
                                <td data-label="">
                                    <x-ui.button variant="secondary" size="sm" href="{{ route('quiz-attempts.show', $attempt) }}" dusk="grade-attempt-{{ $attempt->id }}">
                                        Corrigir
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-state
                                :colspan="4"
                                dusk="professor-dashboard-essays-empty"
                                icon="check"
                                tone="success"
                                title="Nenhuma correção pendente"
                                description="Quando um aluno enviar uma prova com questão dissertativa, ela aparece aqui." />
                        @endforelse
                    </x-ui.data-table>
                </x-ui.card>
            </div>

            <div class="col-12 col-xl-5">
                {{-- Atividade do fórum (7 dias) --}}
                <x-ui.card class="mb-4">
                    <x-slot:title>Atividade do fórum — 7 dias</x-slot:title>

                    <div class="d-flex gap-4" dusk="professor-dashboard-forum-activity">
                        <div>
                            <div class="ds-caption text-body-secondary">Tópicos novos</div>
                            <div class="h4 fw-semibold mb-0 ds-tabular-nums" dusk="forum-topics-count">{{ $forumActivity['topics'] }}</div>
                        </div>
                        <div>
                            <div class="ds-caption text-body-secondary">Respostas novas</div>
                            <div class="h4 fw-semibold mb-0 ds-tabular-nums" dusk="forum-replies-count">{{ $forumActivity['replies'] }}</div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Acesso rápido --}}
                <x-ui.card>
                    <x-slot:title>Meus Cursos</x-slot:title>

                    <div class="d-flex flex-column gap-2">
                        @forelse($quickAccessCourses as $course)
                            <a href="{{ route('courses.modules.index', $course) }}"
                               class="d-flex align-items-center justify-content-between text-decoration-none text-body p-2 rounded-1"
                               dusk="professor-dashboard-course-{{ $course->id }}">
                                <span class="fw-semibold">{{ $course->title }}</span>
                                <span class="ds-caption text-body-secondary">
                                    {{ $course->students_count }} {{ $course->students_count === 1 ? 'aluno' : 'alunos' }} · {{ $course->modules_count }} {{ $course->modules_count === 1 ? 'módulo' : 'módulos' }}
                                </span>
                            </a>
                        @empty
                            <p class="ds-caption text-body-secondary mb-0">Nenhum curso atribuído a você.</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection
