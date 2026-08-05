@extends('layouts.app')

@section('content')
    <x-slot:title>Dashboard Administrativo — Plataforma EAD</x-slot:title>

    <div class="admin-dashboard-container" dusk="admin-dashboard">
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 26px; margin-bottom: 20px;">
            Dashboard
        </h1>

        {{-- Grid de 4 Stat Cards --}}
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
            <x-ui.stat-card kicker="Alunos ativos" value="{{ $stats['active_students'] }}" delta="+4,2%" dusk="stat-active-students" />
            <x-ui.stat-card kicker="Certificados" value="{{ $stats['certificates_issued'] }}" delta="+12%" dusk="stat-certificates-issued" />
            <x-ui.stat-card kicker="Conclusão" value="{{ $stats['completion_rate'] }}%" dusk="stat-completion-rate" />
            <x-ui.stat-card kicker="Cursos" value="{{ $stats['courses_count'] }}" dusk="stat-courses-count" />
        </div>

        {{-- Central de Exportação --}}
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
            <h4 style="font-family: var(--font-heading); font-weight: 800; margin: 0;">
                Matrículas recentes
            </h4>

            <div style="display: flex; gap: 8px;">
                <a href="{{ route('reports.export', ['type' => 'enrollments']) }}"
                   class="btn btn-secondary"
                   dusk="export-enrollments-csv"
                   style="border-radius: 0px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid var(--color-divider); color: var(--color-text);">
                    Exportar Matrículas (CSV)
                </a>
                <a href="{{ route('reports.export', ['type' => 'certificates']) }}"
                   class="btn btn-secondary"
                   dusk="export-certificates-csv"
                   style="border-radius: 0px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid var(--color-divider); color: var(--color-text);">
                    Exportar Certificados (CSV)
                </a>
            </div>
        </div>

        <x-ui.table dusk="recent-enrollments-table">
            <x-slot:header>
                <tr>
                    <th>Nome</th>
                    <th>Curso</th>
                    <th>Status</th>
                </tr>
            </x-slot:header>

            @forelse($recentEnrollments as $enrollment)
                <tr dusk="enrollment-row">
                    <td style="font-weight: 600;">{{ data_get($enrollment, 'student_name') }}</td>
                    <td class="text-muted">{{ data_get($enrollment, 'course_name') }}</td>
                    <td>
                        <x-ui.badge :variant="data_get($enrollment, 'status_badge_variant')">
                            {{ data_get($enrollment, 'status_label') }}
                        </x-ui.badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; color: var(--color-neutral-600); padding: 24px;">
                        Nenhuma matrícula recente.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </div>
@endsection
