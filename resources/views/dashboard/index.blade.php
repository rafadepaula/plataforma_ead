@extends('layouts.app')

@php
    // "Bom dia" / "Boa tarde" / "Boa noite" por hora local — só o primeiro
    // nome, para caber no h1 sem quebrar em telas estreitas.
    $greeting = match (true) {
        now()->hour < 12 => 'Bom dia',
        now()->hour < 18 => 'Boa tarde',
        default => 'Boa noite',
    };
    $firstName = trim(strtok($user->name, ' '));

    $periodLabels = ['7d' => '7 dias', '30d' => '30 dias', 'year' => 'Este ano'];
@endphp

@section('content')
    <x-slot:title>Dashboard Administrativo — Plataforma EAD</x-slot:title>

    <div dusk="admin-dashboard">
        <x-layout.page-header kicker="Painel" :title="$greeting.', '.$firstName">
            <x-slot:subtitle>
                @if ($isGlobalAdminView)
                    Acompanhe o desempenho de todas as Organizações da plataforma.
                @elseif ($activeOrganizationName)
                    Acompanhe o desempenho de {{ $activeOrganizationName }}.
                @else
                    Acompanhe o desempenho da sua Organização.
                @endif
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

                <x-ui.button variant="primary"
                             icon="plus"
                             :href="route('courses.create')">
                    Novo curso
                </x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        {{-- Filtro de período --}}
        <div class="d-flex flex-wrap gap-2 mb-4" role="group" aria-label="Período">
            @foreach ($periodLabels as $value => $label)
                <form method="GET" action="{{ route('admin.dashboard') }}">
                    <input type="hidden" name="period" value="{{ $value }}">
                    <x-ui.chip type="submit" :pressed="$period === $value">
                        {{ $label }}
                    </x-ui.chip>
                </form>
            @endforeach
        </div>

        {{-- Grid de 4 Stat Cards --}}
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-5">
            <div class="col">
                <x-ui.stat-card kicker="Alunos ativos"
                                value="{{ $stats['active_students'] }}"
                                :delta="$stats['active_students_delta']"
                                icon="user"
                                class="h-100"
                                dusk="stat-active-students" />
            </div>
            <div class="col">
                <x-ui.stat-card kicker="Certificados emitidos"
                                value="{{ $stats['certificates_issued'] }}"
                                :delta="$stats['certificates_issued_delta']"
                                icon="award"
                                class="h-100"
                                dusk="stat-certificates-issued" />
            </div>
            <div class="col">
                <x-ui.stat-card kicker="Taxa de conclusão"
                                value="{{ $stats['completion_rate'] }}%"
                                icon="check"
                                class="h-100"
                                dusk="stat-completion-rate" />
            </div>
            <div class="col">
                <x-ui.stat-card kicker="Cursos publicados"
                                value="{{ $stats['courses_count'] }}"
                                icon="book-open"
                                class="h-100"
                                dusk="stat-courses-count" />
            </div>
        </div>

        <div class="row g-4 mb-5">
            {{-- Esquerda — Matrículas recentes --}}
            <div class="col-12 col-lg-8">
                <h2 class="h5 mb-3">Matrículas recentes</h2>

                <x-ui.data-table striped
                                 hover
                                 responsive
                                 :headers="['Aluno', 'Curso', 'Progresso', 'Status']"
                                 dusk="recent-enrollments-table">
                    @forelse($recentEnrollments as $enrollment)
                        <tr dusk="enrollment-row">
                            <td data-label="Aluno">
                                <div class="d-flex align-items-center gap-2">
                                    <x-ui.avatar :initials="Str::of(data_get($enrollment, 'student_name'))->substr(0, 1)->upper()" />
                                    <div>
                                        <div class="fw-semibold">{{ data_get($enrollment, 'student_name') }}</div>
                                        <div class="ds-caption text-body-secondary">{{ data_get($enrollment, 'student_email') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-body-secondary" data-label="Curso">{{ data_get($enrollment, 'course_name') }}</td>
                            <td class="min-w-0" data-label="Progresso">
                                <x-ui.progress :value="data_get($enrollment, 'progress_percentage')" height="8" variant="success" />
                            </td>
                            <td data-label="Status">
                                <x-ui.badge :variant="data_get($enrollment, 'status_badge_variant')">
                                    {{ data_get($enrollment, 'status_label') }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state colspan="4"
                                          icon="book-open"
                                          title="Nenhuma matrícula recente"
                                          description="Convide Alunos ou crie um curso para começar a acompanhar matrículas aqui." />
                    @endforelse
                </x-ui.data-table>
            </div>

            {{-- Direita — Atenção + Ranking --}}
            <div class="col-12 col-lg-4 d-flex flex-column gap-4">
                <x-ui.card title="Precisa da sua atenção">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-card-icon">
                                <x-ui.icon name="file-text" size="20" />
                            </span>
                            <div class="flex-1">
                                <div class="fw-semibold">Redações pendentes</div>
                                <div class="ds-caption text-body-secondary">Aguardando correção</div>
                            </div>
                            <span class="fw-bold">{{ $attentionCounts['pending_essays'] }}</span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-card-icon">
                                <x-ui.icon name="message-square" size="20" />
                            </span>
                            <div class="flex-1">
                                <div class="fw-semibold">Denúncias do fórum</div>
                                <div class="ds-caption text-body-secondary">Aguardando moderação</div>
                            </div>
                            <span class="fw-bold">{{ $attentionCounts['forum_reports'] }}</span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <span class="stat-card-icon">
                                <x-ui.icon name="award" size="20" />
                            </span>
                            <div class="flex-1">
                                <div class="fw-semibold">Certificados prontos</div>
                                <div class="ds-caption text-body-secondary">Emitidos nos últimos 7 dias</div>
                            </div>
                            <span class="fw-bold">{{ $attentionCounts['certificates_ready'] }}</span>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card title="Cursos mais concluídos">
                    @forelse ($mostCompletedCourses as $course)
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <span class="small fw-semibold">{{ $course->course_name }}</span>
                                <span class="ds-caption text-body-secondary">{{ $course->completions }}</span>
                            </div>
                            <x-ui.progress :value="$course->percentage" height="8" variant="success" />
                        </div>
                    @empty
                        <p class="small text-body-secondary mb-0">Nenhuma conclusão registrada ainda.</p>
                    @endforelse
                </x-ui.card>
            </div>
        </div>

        @isset($organizationsSummary)
            {{-- Resumo das Organizações --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 mt-5">
                <h2 class="h5 mb-0">Resumo das Organizações</h2>
            </div>

            <x-ui.data-table striped
                             hover
                             responsive
                             :headers="['Organização', 'Alunos', 'Cursos', 'Certificados']"
                             dusk="organizations-summary-table">
                @forelse($organizationsSummary as $organization)
                    <tr dusk="organization-summary-row-{{ data_get($organization, 'id') }}">
                        <td class="fw-semibold" data-label="Organização">{{ data_get($organization, 'name') }}</td>
                        <td data-label="Alunos" dusk="org-summary-students-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'students_count') }}</td>
                        <td data-label="Cursos" dusk="org-summary-courses-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'courses_count') }}</td>
                        <td data-label="Certificados" dusk="org-summary-certificates-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'certificates_count') }}</td>
                    </tr>
                @empty
                    <x-ui.empty-state colspan="4"
                                      icon="settings"
                                      title="Nenhuma Organização cadastrada"
                                      description="Crie a primeira Organização para começar a matricular Alunos." />
                @endforelse
            </x-ui.data-table>
        @endisset
    </div>
@endsection
