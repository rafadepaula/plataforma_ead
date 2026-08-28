@extends('layouts.app')

{{--
    "Meus Cursos": the Aluno's own enrollments across every Organization
    they belong to. The controller (`StudentCourseController@index`)
    is contracted to pass:

      - `$rows`: a Collection of per-card view-models for the ACTIVE tab
        only, each exposing `course`, `organization`, `displayStatus`
        (nao_iniciado|em_andamento|concluido|expirado), `progressPercentage`
        (already clamped to a 2% visual minimum), `ctaLabel`, `ctaHref`
        (nullable — see `x-course.card-footer`), `lessonsCount`,
        `workloadHours`, `deadlineLabel` (nullable) — rendered by
        `<x-course.card>`.
      - `$activeTab`: one of 'em_andamento'|'concluidos'|'todos' (default
        'em_andamento').
      - `$tabCounts`: `['em_andamento' => int, 'concluidos' => int, 'todos' => int]`,
        used only to decide whether the "Em andamento" tab shows a count
        badge — a zero count renders no badge at all.

    Tabs are plain `?status=` GET links, NOT `<x-ui.tabs>`: that component
    swaps panels client-side via `data-bs-toggle="pill"` with no server
    round-trip, which cannot filter server-rendered rows. The design
    reference's own annotation calls for a stateless GET reload ("sem JS,
    sem estado no cliente"), so this view builds its own `.nav-pills`
    strip out of anchors instead, reusing the same `.ds-tabs` look.
--}}

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Aprendizado'], ['label' => 'Meus Cursos']]"
        kicker="Aprendizado"
        title="Meus Cursos"
        subtitle="Suas matrículas em todas as organizações, com progresso em tempo real."
    />

    @php
        $activeTab = $activeTab ?? 'em_andamento';
        $tabCounts = $tabCounts ?? [];
        $emAndamentoCount = (int) ($tabCounts['em_andamento'] ?? 0);

        $tabs = [
            'em_andamento' => 'Em andamento',
            'concluidos' => 'Concluídos',
            'todos' => 'Todos',
        ];

        $emptyStateCopy = match ($activeTab) {
            'concluidos' => 'Você ainda não concluiu nenhum curso.',
            'todos' => 'Nenhuma matrícula encontrada.',
            default => 'Você ainda não começou nenhum curso.',
        };
    @endphp

    <ul class="nav nav-pills ds-tabs mb-4x" role="tablist">
        @foreach ($tabs as $tabKey => $tabLabel)
            <li class="nav-item" role="presentation">
                <a
                    href="{{ route('student.courses.index', ['status' => $tabKey]) }}"
                    class="nav-link {{ $activeTab === $tabKey ? 'active' : '' }}"
                    role="tab"
                    aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
                    dusk="tab-{{ Str::slug($tabKey, '-') }}"
                >
                    {{ $tabLabel }}
                    @if ($tabKey === 'em_andamento' && $emAndamentoCount > 0)
                        <x-ui.badge variant="info" :dot="false" size="sm" class="ms-2">
                            {{ $emAndamentoCount }}
                        </x-ui.badge>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @if ($rows->isEmpty())
        <x-ui.empty-state
            dusk="no-enrollments"
            icon="book-open"
            title="Nenhum curso por aqui."
            :description="$emptyStateCopy"
        />
    @else
        <div class="ds-course-grid">
            @foreach ($rows as $row)
                <x-course.card :enrollment="$row" dusk="course-card-{{ optional(data_get($row, 'course'))->id }}" />
            @endforeach
        </div>
    @endif
@endsection
