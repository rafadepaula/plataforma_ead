@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Organização" title="Alunos & Gestores">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('users.import.create') }}" dusk="import-users">Importar CSV</x-ui.button>
            <x-ui.button href="{{ route('users.create') }}" dusk="new-user">Novo Usuário</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.data-table striped hover responsive
                     :headers="['Nome', 'E-mail', 'CPF', 'Papel', 'Status', 'Ações']">
        @forelse($users as $user)
            <tr dusk="user-row-{{ $user->id }}">
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
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
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ações do usuário {{ $user->name }}">
                        <x-ui.button variant="secondary"
                                     size="sm"
                                     href="{{ route('users.edit', $user) }}"
                                     dusk="edit-user-{{ $user->id }}">Editar</x-ui.button>

                        <x-ui.button variant="ghost"
                                     size="sm"
                                     class="text-danger link-danger"
                                     data-bs-toggle="modal"
                                     data-bs-target="#delete-user-{{ $user->id }}"
                                     dusk="delete-user-{{ $user->id }}">Remover</x-ui.button>
                    </div>

                    <x-ui.confirm-modal id="delete-user-{{ $user->id }}"
                                        title="Confirmar remoção"
                                        :action="route('users.destroy', $user)"
                                        method="DELETE"
                                        confirm-label="Remover"
                                        message="Remover {{ $user->name }} da organização? Esta ação não poderá ser desfeita."
                                        dusk="delete-form-{{ $user->id }}" />
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="6" message="Nenhum Aluno ou Gestor cadastrado." />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$users" />
@endsection
