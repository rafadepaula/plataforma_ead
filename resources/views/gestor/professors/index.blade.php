@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $professors */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <div dusk="gestor-professors-index">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Organização'], ['label' => 'Professores']]"
            kicker="Organização"
            title="Professores"
            subtitle="Cadastre e gerencie os Professores da sua Organização. A atribuição a cursos é feita na gestão de cada curso."
        >
            <x-slot:actions>
                <x-ui.button variant="primary" icon="plus" href="{{ route('gestor.professors.create') }}" dusk="create-professor">Novo Professor</x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        <x-ui.filter-bar :action="route('gestor.professors.index')"
                         :reset-url="route('gestor.professors.index')"
                         label="Filtros de professores"
                         dusk="gestor-professors-filter-form">
            <div class="col-12 col-lg">
                <x-ui.input name="search"
                            label="Buscar por nome, e-mail ou CPF"
                            :value="$search"
                            dusk="gestor-professors-search" />
            </div>
        </x-ui.filter-bar>

        <x-ui.data-table striped hover responsive
                         :headers="['Professor', 'CPF', 'Cursos atribuídos', 'Status', 'Ações']">
            @forelse($professors as $professor)
                <tr dusk="professor-row-{{ $professor->id }}">
                    <td data-label="Professor">
                        <div class="d-flex align-items-center gap-3">
                            <x-ui.avatar :initials="$initialsFor($professor->name)" />
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $professor->name }}</div>
                                <div class="small text-body-secondary text-truncate">{{ $professor->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="CPF" class="ds-tabular-nums">{{ $professor->cpf ?? '—' }}</td>
                    <td data-label="Cursos atribuídos" class="ds-tabular-nums">{{ $professor->taught_courses_count }}</td>
                    <td data-label="Status">
                        @if($professor->status === 'active')
                            <x-ui.badge variant="success" dusk="professor-status-{{ $professor->id }}">Ativo</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dusk="professor-status-{{ $professor->id }}">Inativo</x-ui.badge>
                        @endif
                    </td>
                    <td data-label="Ações">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <x-ui.button variant="secondary"
                                         size="sm"
                                         href="{{ route('gestor.professors.edit', $professor) }}"
                                         dusk="edit-professor-{{ $professor->id }}">Editar</x-ui.button>

                            <x-ui.button variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#delete-professor-{{ $professor->id }}"
                                         dusk="delete-professor-{{ $professor->id }}">Remover</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="5" icon="user" title="Nenhum Professor cadastrado ainda." description="Cadastre o primeiro Professor da sua Organização; em seguida, atribua-o aos cursos que ele vai lecionar.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('gestor.professors.create') }}">Novo Professor</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </x-ui.data-table>

        {{-- Modais de confirmação ficam fora da tabela para evitar recorte pelo wrapper responsivo. --}}
        @foreach($professors as $professor)
            <x-ui.confirm-modal id="delete-professor-{{ $professor->id }}"
                                title="Confirmar remoção"
                                :action="route('gestor.professors.destroy', $professor)"
                                method="DELETE"
                                confirm-label="Remover"
                                message="Remover {{ $professor->name }}? As atribuições dele a cursos também serão removidas. Esta ação não poderá ser desfeita."
                                dusk="delete-form-{{ $professor->id }}" />
        @endforeach

        <x-ui.pagination :paginator="$professors" />
    </div>
@endsection
