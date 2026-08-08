@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Organização</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Alunos & Gestores</h1>
        </div>

        <div style="display: flex; gap: 8px;">
            <x-ui.button variant="secondary" href="{{ route('users.import.create') }}" dusk="import-users">Importar CSV</x-ui.button>
            <x-ui.button href="{{ route('users.create') }}" dusk="new-user">Novo Usuário</x-ui.button>
        </div>
    </div>

    <x-ui.table :headers="['Nome', 'E-mail', 'CPF', 'Papel', 'Status', 'Ações']">
        @forelse($users as $user)
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="user-row-{{ $user->id }}">
                <td style="padding: 12px 16px;">{{ $user->name }}</td>
                <td style="padding: 12px 16px;">{{ $user->email }}</td>
                <td style="padding: 12px 16px;">{{ $user->cpf ?? '—' }}</td>
                <td style="padding: 12px 16px;">
                    <x-ui.badge variant="accent">{{ \App\Enums\Permissions\RolesEnum::label($user->getRoleNames()->first() ?? '') }}</x-ui.badge>
                </td>
                <td style="padding: 12px 16px;">
                    @if($user->status === 'active')
                        <x-ui.badge variant="accent" dusk="user-status-{{ $user->id }}">Ativo</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral" dusk="user-status-{{ $user->id }}">Inativo</x-ui.badge>
                    @endif
                </td>
                <td style="padding: 12px 16px; display: flex; gap: 8px;">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('users.edit', $user) }}" dusk="edit-user-{{ $user->id }}">Editar</x-ui.button>

                    <form method="POST" action="{{ route('users.destroy', $user) }}" dusk="delete-form-{{ $user->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost" dusk="delete-user-{{ $user->id }}">Remover</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                    Nenhum Aluno ou Gestor cadastrado.
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div style="margin-top: 20px;">
        {{ $users->links() }}
    </div>
@endsection
