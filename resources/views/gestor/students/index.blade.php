@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $students */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <div dusk="gestor-students-index">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Organização'], ['label' => 'Alunos Matriculados']]"
            kicker="Organização"
            title="Alunos Matriculados"
            subtitle="Visualize e gerencie os Alunos matriculados nos cursos da sua Organização."
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" href="{{ route('users.import.create') }}" dusk="import-students">Importar CSV</x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        <x-ui.data-table striped hover responsive
                         :headers="['Aluno', 'CPF', 'Cursos', 'Status', 'Ações']">
            @forelse($students as $student)
                <tr dusk="student-row-{{ $student->id }}">
                    <td data-label="Aluno">
                        <div class="d-flex align-items-center gap-3">
                            <x-ui.avatar :initials="$initialsFor($student->name)" />
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $student->name }}</div>
                                <div class="small text-body-secondary text-truncate">{{ $student->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="CPF" class="ds-tabular-nums">{{ $student->cpf ?? '—' }}</td>
                    <td data-label="Cursos">
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($student->courses as $course)
                                <x-ui.badge variant="neutral">{{ $course->title }}</x-ui.badge>
                            @empty
                                <span class="text-body-secondary">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td data-label="Status">
                        @if($student->status === 'active')
                            <x-ui.badge variant="success" dusk="student-status-{{ $student->id }}">Ativo</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dusk="student-status-{{ $student->id }}">Inativo</x-ui.badge>
                        @endif
                    </td>
                    <td data-label="Ações">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <x-ui.button variant="secondary"
                                         size="sm"
                                         href="{{ route('gestor.students.edit', $student) }}"
                                         dusk="edit-student-{{ $student->id }}">Editar</x-ui.button>

                            <x-ui.button variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#delete-student-{{ $student->id }}"
                                         dusk="delete-student-{{ $student->id }}">Remover</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="5" icon="user" title="Nenhum Aluno matriculado ainda." description="Matricule Alunos nos cursos da sua Organização por convite, importação de CSV ou matrícula manual.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('users.import.create') }}">Importar CSV</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </x-ui.data-table>

        {{-- Modais de confirmação ficam fora da tabela para evitar recorte pelo wrapper responsivo. --}}
        @foreach($students as $student)
            <x-ui.confirm-modal id="delete-student-{{ $student->id }}"
                                title="Confirmar remoção"
                                :action="route('gestor.students.destroy', $student)"
                                method="DELETE"
                                confirm-label="Remover"
                                message="Remover {{ $student->name }} da organização? Esta ação não poderá ser desfeita."
                                dusk="delete-form-{{ $student->id }}" />
        @endforeach

        <x-ui.pagination :paginator="$students" />
    </div>
@endsection
