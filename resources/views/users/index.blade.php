@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $users */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Organização'], ['label' => 'Alunos & Gestores']]"
        kicker="Organização"
        title="Alunos & Gestores"
        subtitle="Gerencie os Alunos e Gestores da sua Organização, seus papéis e status de acesso."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('users.import.create') }}" dusk="import-users">Importar CSV</x-ui.button>
            <x-ui.button href="{{ route('users.create') }}" dusk="new-user">Novo Usuário</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Tabela — visível a partir de `md`; abaixo disso a lista de cards assume. --}}
    <div class="d-none d-md-block">
        <x-ui.data-table striped hover responsive
                         :headers="['Usuário', 'CPF', 'Papel', 'Status', 'Ações']">
            @forelse($users as $user)
                <tr dusk="user-row-{{ $user->id }}">
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <x-ui.avatar :initials="$initialsFor($user->name)" />
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <div class="small text-body-secondary text-truncate">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $user->cpf ?? '—' }}</td>
                    <td>
                        <x-ui.badge variant="accent">{{ \App\Enums\Permissions\RolesEnum::label($user->getRoleNames()->first() ?? '') }}</x-ui.badge>
                    </td>
                    <td>
                        @if($user->status === 'active')
                            <x-ui.badge variant="accent" dusk="user-status-{{ $user->id }}">Ativo</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dusk="user-status-{{ $user->id }}">Inativo</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <x-ui.button variant="secondary"
                                         size="sm"
                                         href="{{ route('users.edit', $user) }}"
                                         dusk="edit-user-{{ $user->id }}">Editar</x-ui.button>

                            <x-ui.button variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#delete-user-{{ $user->id }}"
                                         dusk="delete-user-{{ $user->id }}">Remover</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="5" icon="user" title="Nenhum Aluno ou Gestor cadastrado." description="Cadastre o primeiro Aluno ou Gestor da sua Organização, manualmente ou por importação de CSV.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('users.create') }}">Novo Usuário</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </x-ui.data-table>
    </div>

    {{-- Lista de cards — abaixo de `md`, substitui a tabela (mesmo conteúdo, sem `dusk` duplicado). --}}
    <div class="d-md-none d-flex flex-column gap-3">
        @forelse($users as $user)
            <x-ui.card>
                <x-slot:kickerSlot>
                    @if($user->status === 'active')
                        <x-ui.badge variant="accent">Ativo</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Inativo</x-ui.badge>
                    @endif
                </x-slot:kickerSlot>

                <div class="d-flex align-items-center gap-3 mb-2">
                    <x-ui.avatar :initials="$initialsFor($user->name)" />
                    <div class="min-w-0">
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="small text-body-secondary text-truncate">{{ $user->email }}</div>
                    </div>
                </div>

                <p class="mb-0">
                    CPF: {{ $user->cpf ?? '—' }}
                    &middot;
                    <x-ui.badge variant="accent">{{ \App\Enums\Permissions\RolesEnum::label($user->getRoleNames()->first() ?? '') }}</x-ui.badge>
                </p>

                <x-slot:metaSlot>
                    <div class="d-flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" href="{{ route('users.edit', $user) }}">Editar</x-ui.button>
                        <x-ui.button variant="danger" data-bs-toggle="modal" data-bs-target="#delete-user-{{ $user->id }}">Remover</x-ui.button>
                    </div>
                </x-slot:metaSlot>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="user" title="Nenhum Aluno ou Gestor cadastrado." description="Cadastre o primeiro Aluno ou Gestor da sua Organização, manualmente ou por importação de CSV.">
                <x-slot:action>
                    <x-ui.button href="{{ route('users.create') }}">Novo Usuário</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse
    </div>

    {{-- Modais de confirmação — fora dos wrappers `d-none`/`d-md-none`: um
         ancestral `display:none` suprime toda a subárvore mesmo depois do
         Bootstrap alternar `.show` no modal, então o modal precisa existir
         fora de qualquer bloco condicional por breakpoint para ser
         alcançável tanto pelo gatilho desktop quanto pelo mobile. --}}
    @foreach($users as $user)
        <x-ui.confirm-modal id="delete-user-{{ $user->id }}"
                            title="Confirmar remoção"
                            :action="route('users.destroy', $user)"
                            method="DELETE"
                            confirm-label="Remover"
                            message="Remover {{ $user->name }} da organização? Esta ação não poderá ser desfeita."
                            dusk="delete-form-{{ $user->id }}" />
    @endforeach

    <x-ui.pagination :paginator="$users" />
@endsection
