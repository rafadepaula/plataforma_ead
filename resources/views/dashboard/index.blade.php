@extends('layouts.app')

@php
    $greeting = match (true) {
        now()->hour < 12 => 'Bom dia',
        now()->hour < 18 => 'Boa tarde',
        default => 'Boa noite',
    };
    $firstName = trim(strtok($user->name, ' '));
    $hasAttentionItems = collect($attentionCounts)->contains(fn (int $count): bool => $count > 0);
    $isFirstAccess = ! collect([
        $stats['active_students'],
        $stats['certificates_issued'],
        $stats['courses_count'],
        $stats['draft_courses_count'],
    ])->contains(fn (int $value): bool => $value > 0);
    $activeStudentsDeltaVariant = Str::startsWith((string) $stats['active_students_delta'], '-') ? 'neutral' : 'success';
    $certificatesDeltaVariant = Str::startsWith((string) $stats['certificates_issued_delta'], '-') ? 'neutral' : 'success';
@endphp

@section('content')
    <x-slot:title>Dashboard Administrativo — Plataforma EAD</x-slot:title>

    <div class="dashboard-page" dusk="admin-dashboard" data-dashboard-root>
        <x-layout.page-header kicker="Painel" :title="$greeting.', '.$firstName">
            <x-slot:subtitle>
                {{ $isGlobalAdminView
                    ? 'Um panorama da plataforma nos últimos 30 dias.'
                    : 'Um panorama da sua organização nos últimos 30 dias.' }}
            </x-slot:subtitle>

            <x-slot:actions>
                <x-ui.button variant="tonal"
                             icon="upload"
                             :href="route('reports.export', ['type' => 'enrollments'])"
                             dusk="export-enrollments-csv">
                    Exportar matrículas (CSV)
                </x-ui.button>

                <x-ui.button variant="tonal"
                             icon="upload"
                             :href="route('reports.export', ['type' => 'certificates'])"
                             dusk="export-certificates-csv">
                    Exportar certificados (CSV)
                </x-ui.button>

                @if ($canCreateCourse)
                    <x-ui.button variant="primary"
                                 icon="plus"
                                 :href="route('courses.create')">
                        Novo curso
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-layout.page-header>

        <div class="d-flex align-items-center gap-2 mb-4" data-dashboard-filter-bar>
            <x-ui.chip :pressed="($period ?? '30d') === '7d'" data-period="7d" dusk="filter-period-7d">Últimos 7 dias</x-ui.chip>
            <x-ui.chip :pressed="($period ?? '30d') === '30d'" data-period="30d" dusk="filter-period-30d">Últimos 30 dias</x-ui.chip>
            <x-ui.chip :pressed="($period ?? '30d') === 'year'" data-period="year" dusk="filter-period-year">Este ano</x-ui.chip>
        </div>

        <div class="dashboard-stat-grid">
            <x-ui.stat-card kicker="Alunos ativos"
                            value="{{ $stats['active_students'] }}"
                            :delta="$stats['active_students_delta']"
                            :delta-variant="$activeStudentsDeltaVariant"
                            caption="vs. período anterior"
                            icon="user"
                            tone="primary"
                            :no-data="$isFirstAccess"
                            data-stat-active-students
                            dusk="stat-active-students" />

            <x-ui.stat-card kicker="Certificados emitidos"
                            value="{{ $stats['certificates_issued'] }}"
                            :delta="$stats['certificates_issued_delta']"
                            :delta-variant="$certificatesDeltaVariant"
                            caption="emitidos no total"
                            icon="award"
                            tone="secondary"
                            :no-data="$isFirstAccess"
                            data-stat-certificates-issued
                            dusk="stat-certificates-issued" />

            <x-ui.stat-card kicker="Taxa de conclusão"
                            value="{{ $stats['completion_rate'] }}%"
                            caption="média dos cursos"
                            icon="check"
                            tone="tertiary"
                            :no-data="$isFirstAccess"
                            data-stat-completion-rate
                            dusk="stat-completion-rate" />

            <x-ui.stat-card kicker="Cursos publicados"
                            value="{{ $stats['courses_count'] }}"
                            caption="{{ $stats['draft_courses_count'] }} em rascunho"
                            icon="book-open"
                            tone="neutral"
                            :no-data="$isFirstAccess"
                            data-stat-courses-count
                            dusk="stat-courses-count" />
        </div>

        <div class="dashboard-content-grid">
            <section class="dashboard-section-card" aria-labelledby="recent-enrollments-heading">
                <div class="dashboard-section-header">
                    <div>
                        <h2 class="h5 mb-0" id="recent-enrollments-heading">Matrículas recentes</h2>
                        <p class="ds-caption text-body-secondary mb-0 mt-1">Atualizado agora</p>
                    </div>
                </div>

                <x-ui.data-table hover
                                 :headers="['Aluno', 'Curso', 'Progresso', 'Status']"
                                 aria-label="Matrículas recentes"
                                 dusk="recent-enrollments-table">
                    @forelse($recentEnrollments as $enrollment)
                        <tr dusk="enrollment-row">
                            <td data-label="Aluno">
                                <div class="d-flex align-items-center gap-3">
                                    <x-ui.avatar :initials="data_get($enrollment, 'student_initials')" />
                                    <div>
                                        <div class="fw-semibold">{{ data_get($enrollment, 'student_name') }}</div>
                                        <div class="ds-caption text-body-secondary">{{ data_get($enrollment, 'student_email') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-body-secondary" data-label="Curso">{{ data_get($enrollment, 'course_name') }}</td>
                            <td class="min-w-0 ds-tabular-nums" data-label="Progresso">
                                <x-ui.progress :value="data_get($enrollment, 'progress_percentage')"
                                               :label="'Progresso de '.data_get($enrollment, 'student_name').' no curso '.data_get($enrollment, 'course_name').': '.data_get($enrollment, 'progress_percentage').'%'"
                                               height="8"
                                               :variant="data_get($enrollment, 'progress_percentage') === 100 ? 'success' : 'primary'" />
                            </td>
                            <td data-label="Status">
                                <x-ui.badge :variant="data_get($enrollment, 'status_badge_variant')">
                                    {{ data_get($enrollment, 'status_label') }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state colspan="4"
                                          icon="user"
                                          title="Nenhuma matrícula ainda"
                                          description="Convide alunos por link e as matrículas aparecem aqui.">
                            <x-slot:action>
                                <x-ui.button variant="tonal"
                                             icon="book-open"
                                             :href="route('courses.index')">
                                    Ver cursos para convidar
                                </x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @endforelse
                </x-ui.data-table>
            </section>

            <aside class="dashboard-side-column">
                @if ($hasAttentionItems)
                    <x-ui.card title="Precisa da sua atenção" :border="false" elevation="sm" surface="white">
                        <div class="dashboard-attention-list">
                            @if ($attentionCounts['pending_essays'] > 0)
                                <a href="{{ route('quiz-attempts.pending') }}" class="dashboard-attention-item">
                                    <span class="dashboard-attention-icon">
                                        <x-ui.icon name="file-text" size="20" />
                                    </span>
                                    <span class="dashboard-attention-copy">Redações aguardando correção</span>
                                    <x-ui.badge variant="info" :dot="false" class="dashboard-attention-count">
                                        {{ $attentionCounts['pending_essays'] }}
                                    </x-ui.badge>
                                </a>
                            @endif

                            @if ($attentionCounts['forum_reports'] > 0)
                                <a href="{{ route('forum-moderation.index') }}" class="dashboard-attention-item">
                                    <span class="dashboard-attention-icon">
                                        <x-ui.icon name="shield" size="20" />
                                    </span>
                                    <span class="dashboard-attention-copy">Denúncias no fórum</span>
                                    <x-ui.badge variant="info" :dot="false" class="dashboard-attention-count">
                                        {{ $attentionCounts['forum_reports'] }}
                                    </x-ui.badge>
                                </a>
                            @endif

                            @if ($attentionCounts['certificates_ready'] > 0)
                                <a href="{{ route('reports.export', ['type' => 'certificates']) }}" class="dashboard-attention-item">
                                    <span class="dashboard-attention-icon">
                                        <x-ui.icon name="award" size="20" />
                                    </span>
                                    <span class="dashboard-attention-copy">
                                        Certificados emitidos recentemente
                                        <span class="d-block ds-caption text-body-secondary">Emitidos nos últimos 7 dias</span>
                                    </span>
                                    <x-ui.badge variant="info" :dot="false" class="dashboard-attention-count">
                                        {{ $attentionCounts['certificates_ready'] }}
                                    </x-ui.badge>
                                </a>
                            @endif
                        </div>
                    </x-ui.card>
                @else
                    <x-ui.card title="Nada aguardando você" :border="false" elevation="sm" surface="white">
                        <p class="mb-0 text-body-secondary">Você está em dia com as filas desta organização.</p>
                    </x-ui.card>
                @endif

                <x-ui.card title="Cursos mais concluídos" surface="white">
                    <div class="dashboard-ranking-list">
                        @forelse ($mostCompletedCourses->take(3) as $course)
                            <div class="dashboard-ranking-item">
                                <div class="dashboard-ranking-copy">
                                    <span class="ds-caption">{{ $course->course_name }}</span>
                                    <span class="ds-caption fw-bold">{{ $course->percentage }}%</span>
                                </div>
                                <x-ui.progress :value="$course->percentage"
                                               :label="'Conclusões do curso '.$course->course_name.': '.$course->percentage.'% do curso líder'"
                                               height="8"
                                               variant="success" />
                            </div>
                        @empty
                            <p class="small text-body-secondary mb-0">Nenhuma conclusão registrada ainda.</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </aside>
        </div>

        @isset($organizationsSummary)
            <section class="dashboard-section-card mt-5" aria-labelledby="organizations-summary-heading">
                <div class="dashboard-section-header">
                    <h2 class="h5 mb-0" id="organizations-summary-heading">Resumo das Organizações</h2>
                </div>

                <x-ui.data-table hover
                                 :headers="['Organização', 'Alunos', 'Cursos', 'Certificados']"
                                 aria-label="Resumo das Organizações"
                                 dusk="organizations-summary-table">
                    @forelse($organizationsSummary as $organization)
                        <tr dusk="organization-summary-row-{{ data_get($organization, 'id') }}">
                            <td class="fw-semibold" data-label="Organização">{{ data_get($organization, 'name') }}</td>
                            <td class="ds-tabular-nums" data-label="Alunos" dusk="org-summary-students-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'students_count') }}</td>
                            <td class="ds-tabular-nums" data-label="Cursos" dusk="org-summary-courses-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'courses_count') }}</td>
                            <td class="ds-tabular-nums" data-label="Certificados" dusk="org-summary-certificates-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'certificates_count') }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-state colspan="4"
                                          icon="settings"
                                          title="Nenhuma Organização cadastrada"
                                          description="Crie a primeira Organização para começar a matricular Alunos." />
                    @endforelse
                </x-ui.data-table>
            </section>
        @endisset
    </div>
@endsection
