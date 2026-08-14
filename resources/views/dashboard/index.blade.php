@extends('layouts.app')

@section('content')
    <x-slot:title>Dashboard Administrativo — Plataforma EAD</x-slot:title>

    <div dusk="admin-dashboard">
        <x-layout.page-header title="Dashboard" />

        {{-- Grid de 4 Stat Cards --}}
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-5">
            <div class="col">
                <x-ui.stat-card kicker="Alunos ativos" value="{{ $stats['active_students'] }}" delta="+4,2%" class="h-100" dusk="stat-active-students" />
            </div>
            <div class="col">
                <x-ui.stat-card kicker="Certificados" value="{{ $stats['certificates_issued'] }}" delta="+12%" class="h-100" dusk="stat-certificates-issued" />
            </div>
            <div class="col">
                <x-ui.stat-card kicker="Conclusão" value="{{ $stats['completion_rate'] }}%" class="h-100" dusk="stat-completion-rate" />
            </div>
            <div class="col">
                <x-ui.stat-card kicker="Cursos" value="{{ $stats['courses_count'] }}" class="h-100" dusk="stat-courses-count" />
            </div>
        </div>

        {{-- Central de Exportação --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h2 class="h5 mb-0">Matrículas recentes</h2>

            <div class="d-flex flex-wrap gap-2">
                <x-ui.button variant="secondary"
                             size="sm"
                             icon="file-text"
                             :href="route('reports.export', ['type' => 'enrollments'])"
                             dusk="export-enrollments-csv">
                    Exportar Matrículas (CSV)
                </x-ui.button>

                <x-ui.button variant="secondary"
                             size="sm"
                             icon="file-text"
                             :href="route('reports.export', ['type' => 'certificates'])"
                             dusk="export-certificates-csv">
                    Exportar Certificados (CSV)
                </x-ui.button>
            </div>
        </div>

        <x-ui.data-table striped
                         hover
                         responsive
                         :headers="['Nome', 'Curso', 'Status']"
                         dusk="recent-enrollments-table">
            @forelse($recentEnrollments as $enrollment)
                <tr dusk="enrollment-row">
                    <td class="fw-semibold">{{ data_get($enrollment, 'student_name') }}</td>
                    <td class="text-body-secondary">{{ data_get($enrollment, 'course_name') }}</td>
                    <td>
                        <x-ui.badge :variant="data_get($enrollment, 'status_badge_variant')">
                            {{ data_get($enrollment, 'status_label') }}
                        </x-ui.badge>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="3" message="Nenhuma matrícula recente." />
            @endforelse
        </x-ui.data-table>

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
                        <td class="fw-semibold">{{ data_get($organization, 'name') }}</td>
                        <td dusk="org-summary-students-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'students_count') }}</td>
                        <td dusk="org-summary-courses-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'courses_count') }}</td>
                        <td dusk="org-summary-certificates-{{ data_get($organization, 'id') }}">{{ data_get($organization, 'certificates_count') }}</td>
                    </tr>
                @empty
                    <x-ui.empty-state colspan="4" message="Nenhuma Organização cadastrada." />
                @endforelse
            </x-ui.data-table>
        @endisset
    </div>
@endsection
