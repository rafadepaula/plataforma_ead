@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Administração</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Organizações</h1>
        </div>

        <x-ui.button href="{{ route('organizations.create') }}" dusk="new-organization">Nova Organização</x-ui.button>
    </div>

    @if(session('active_org_id'))
        <x-ui.alert variant="warning" dismissable>
            Você está no contexto da Organização
            <strong>{{ \App\Models\Organization::find(session('active_org_id'))?->name }}</strong>.
            <form method="POST" action="{{ route('impersonate-org.destroy') }}" style="display: inline;" dusk="exit-impersonation-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost" style="padding: 0 0 0 8px; text-decoration: underline;" dusk="exit-impersonation">Sair do contexto</button>
            </form>
        </x-ui.alert>
    @endif

    <x-ui.table :headers="['Nome', 'Slug', 'CNPJ', 'Status', 'Ações']">
        @forelse($organizations as $organization)
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="organization-row-{{ $organization->id }}">
                <td style="padding: 12px 16px;">{{ $organization->name }}</td>
                <td style="padding: 12px 16px;">{{ $organization->slug }}</td>
                <td style="padding: 12px 16px;">{{ $organization->cnpj ?? '—' }}</td>
                <td style="padding: 12px 16px;">
                    <x-ui.badge :variant="$organization->status === 'active' ? 'accent' : 'neutral'">
                        {{ $organization->status === 'active' ? 'Ativo' : 'Inativo' }}
                    </x-ui.badge>
                </td>
                <td style="padding: 12px 16px; display: flex; gap: 8px;">
                    <form method="POST" action="{{ route('impersonate-org.store', $organization) }}" dusk="impersonate-form-{{ $organization->id }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost" dusk="impersonate-{{ $organization->id }}">Entrar como</button>
                    </form>

                    <x-ui.button variant="secondary" size="sm" href="{{ route('organizations.edit', $organization) }}" dusk="edit-organization-{{ $organization->id }}">Editar</x-ui.button>

                    <form method="POST" action="{{ route('organizations.destroy', $organization) }}" dusk="delete-form-{{ $organization->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost" dusk="delete-organization-{{ $organization->id }}">Remover</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                    Nenhuma Organização cadastrada.
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div style="margin-top: 20px;">
        {{ $organizations->links() }}
    </div>
@endsection
