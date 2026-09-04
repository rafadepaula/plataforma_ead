@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Course> $courses */
    /** @var array<int, int> $pendingEssays */
    /** @var array<int, int> $pendingReports */
@endphp

@extends('layouts.app')

@section('content')
    <div dusk="professor-courses-index">
        <x-layout.page-header
            kicker="Ensino"
            title="Meus Cursos"
            subtitle="Cursos atribuídos a você. Gerencie o conteúdo, acompanhe correções e modere o fórum."
        />

        <div class="row g-4" dusk="professor-courses-grid">
            @forelse($courses as $course)
                @php
                    $essays = $pendingEssays[$course->id] ?? 0;
                    $reports = $pendingReports[$course->id] ?? 0;
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100 p-4" dusk="professor-course-card-{{ $course->id }}">
                        <div class="ds-overline text-body-secondary mb-1">
                            {{ $course->lessons_count }} {{ $course->lessons_count === 1 ? 'aula' : 'aulas' }} · {{ $course->modules_count }} {{ $course->modules_count === 1 ? 'módulo' : 'módulos' }}
                        </div>

                        <h3 class="h5 fw-semibold mb-2">
                            <a href="{{ route('courses.modules.index', $course) }}" class="text-decoration-none text-body stretched-link">
                                {{ $course->title }}
                            </a>
                        </h3>

                        <p class="ds-caption text-body-secondary mb-3 line-clamp-2">
                            {{ \Illuminate\Support\Str::limit($course->description ?? '', 120) }}
                        </p>

                        <dl class="row row-cols-2 g-2 mb-3">
                            <div class="col">
                                <dt class="ds-caption text-body-secondary fw-normal">Alunos ativos</dt>
                                <dd class="fw-semibold mb-0 ds-tabular-nums">{{ $course->students_count }}</dd>
                            </div>
                            <div class="col">
                                <dt class="ds-caption text-body-secondary fw-normal">Correções pendentes</dt>
                                <dd class="fw-semibold mb-0 ds-tabular-nums" dusk="professor-course-essays-{{ $course->id }}">{{ $essays }}</dd>
                            </div>
                            <div class="col">
                                <dt class="ds-caption text-body-secondary fw-normal">Denúncias pendentes</dt>
                                <dd class="fw-semibold mb-0 ds-tabular-nums" dusk="professor-course-reports-{{ $course->id }}">{{ $reports }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <x-ui.empty-state
                        dusk="professor-courses-empty"
                        icon="book"
                        title="Nenhum curso atribuído a você."
                        description="Assim que um Gestor ou Administrador atribuir um curso a você, ele aparecerá aqui." />
                </div>
            @endforelse
        </div>
    </div>
@endsection
