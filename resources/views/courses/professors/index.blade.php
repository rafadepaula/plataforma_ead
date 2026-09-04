@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $assigned */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $available */
    /** @var array<int, string> $assignedByNames */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <div dusk="course-professors-index">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title]]"
            :kicker="$course->title"
            title="Professores"
            subtitle="Atribua Professores da mesma Organização a este curso. A atribuição não cria matrícula nem aparece na coluna de alunos."
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        <x-ui.card>
            <x-slot:title>Professores atribuídos</x-slot:title>

            <x-ui.data-table striped hover responsive
                             :headers="['Professor', 'Atribuído por', 'Atribuído em', 'Ações']">
                @forelse($assigned as $professor)
                    <tr dusk="course-professor-row-{{ $professor->id }}">
                        <td data-label="Professor">
                            <div class="d-flex align-items-center gap-3">
                                <x-ui.avatar :initials="$initialsFor($professor->name)" />
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $professor->name }}</div>
                                    <div class="ds-caption text-body-secondary text-truncate">{{ $professor->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Atribuído por">{{ $assignedByNames[$professor->pivot->assigned_by] ?? '—' }}</td>
                        <td class="text-nowrap ds-tabular-nums" data-label="Atribuído em">{{ $professor->pivot->created_at?->format('d/m/Y') ?? '—' }}</td>
                        <td data-label="Ações">
                            <form id="detach-professor-form-{{ $professor->id }}"
                                  method="POST"
                                  action="{{ route('courses.professors.destroy', [$course, $professor]) }}"
                                  dusk="detach-professor-form-{{ $professor->id }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="danger"
                                             size="sm"
                                             type="button"
                                             icon="x"
                                             data-bs-toggle="modal"
                                             data-bs-target="#detach-professor-modal-{{ $professor->id }}"
                                             dusk="detach-professor-{{ $professor->id }}">Remover</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state
                        colspan="4"
                        icon="user"
                        title="Nenhum Professor atribuído a este curso."
                        description="Use a lista “Professores disponíveis” abaixo para atribuir o primeiro Professor." />
                @endforelse
            </x-ui.data-table>
        </x-ui.card>

        @foreach($assigned as $professor)
            <x-ui.confirm-modal
                id="detach-professor-modal-{{ $professor->id }}"
                title="Remover atribuição"
                form="detach-professor-form-{{ $professor->id }}"
                method="DELETE"
                confirm-label="Remover"
                message="Remover a atribuição de {{ $professor->name }} a este curso? Ele perderá o acesso ao conteúdo, à correção e à moderação do fórum deste curso."
                confirm-dusk="detach-professor-confirm-{{ $professor->id }}" />
        @endforeach

        <x-ui.card class="mt-4">
            <x-slot:title>Professores disponíveis</x-slot:title>

            <x-ui.filter-bar
                :action="route('courses.professors.index', $course)"
                method="GET"
                submit-label="Buscar"
                :reset-url="route('courses.professors.index', $course)"
                label="Buscar professores"
                dense
            >
                <div class="col-12 col-md-6">
                    <x-ui.input
                        type="search"
                        name="q"
                        label="Nome, e-mail ou CPF"
                        value="{{ $search }}"
                        dusk="course-professors-search"
                    />
                </div>
            </x-ui.filter-bar>

            <x-ui.data-table striped hover responsive
                             :headers="['Professor', 'Ações']">
                @forelse($available as $professor)
                    <tr dusk="available-professor-row-{{ $professor->id }}">
                        <td data-label="Professor">
                            <div class="d-flex align-items-center gap-3">
                                <x-ui.avatar :initials="$initialsFor($professor->name)" />
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ $professor->name }}</div>
                                    <div class="ds-caption text-body-secondary text-truncate">{{ $professor->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Ações">
                            <form method="POST"
                                  action="{{ route('courses.professors.store', $course) }}"
                                  dusk="attach-professor-form-{{ $professor->id }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $professor->id }}">
                                <x-ui.button variant="success"
                                             size="sm"
                                             type="submit"
                                             icon="plus"
                                             dusk="attach-professor-{{ $professor->id }}">Atribuir</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state
                        colspan="2"
                        icon="user"
                        :title="$search !== '' ? 'Nenhum Professor encontrado para a busca.' : 'Nenhum Professor disponível para atribuição.'"
                        description="Cadastre Professores na sua Organização pela tela de Professores antes de atribuí-los aos cursos." />
                @endforelse
            </x-ui.data-table>
        </x-ui.card>
    </div>
@endsection
