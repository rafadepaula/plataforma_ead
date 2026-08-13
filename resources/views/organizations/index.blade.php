@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Administração" title="Organizações">
        <x-slot:actions>
            <x-ui.button href="{{ route('organizations.create') }}" dusk="new-organization">Nova Organização</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    @if(session('active_org_id'))
        <x-ui.alert variant="warning" dismissable>
            Você está no contexto da Organização
            <strong>{{ \App\Models\Organization::find(session('active_org_id'))?->name }}</strong>.
            <form method="POST" action="{{ route('impersonate-org.destroy') }}" class="d-inline" dusk="exit-impersonation-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link p-0 ps-2 text-decoration-underline" dusk="exit-impersonation">Sair do contexto</button>
            </form>
        </x-ui.alert>
    @endif

    <x-ui.data-table striped hover responsive :headers="['Nome', 'Slug', 'CNPJ', 'Status', 'Ações']">
        @forelse($organizations as $organization)
            <tr dusk="organization-row-{{ $organization->id }}">
                <td>{{ $organization->name }}</td>
                <td class="font-monospace small">{{ $organization->slug }}</td>
                <td>{{ $organization->cnpj ?? '—' }}</td>
                <td>
                    <x-ui.badge :variant="$organization->status === 'active' ? 'accent' : 'neutral'">
                        {{ $organization->status === 'active' ? 'Ativo' : 'Inativo' }}
                    </x-ui.badge>
                </td>
                <td>
                    {{-- `d-flex`, não `.btn-group`: os filhos diretos aqui são `<form>`,
                         e o `.btn-group` só agrupa quando os filhos são os próprios
                         `<button>`/`<a>` — com forms no meio os botões saem colados
                         e sem os cantos/bordas corretos. --}}
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <form method="POST" action="{{ route('impersonate-org.store', $organization) }}" dusk="impersonate-form-{{ $organization->id }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary" dusk="impersonate-{{ $organization->id }}">Entrar como</button>
                        </form>

                        <x-ui.button href="{{ route('organizations.edit', $organization) }}" size="sm" dusk="edit-organization-{{ $organization->id }}">Editar</x-ui.button>

                        {{-- Submit direto, sem `<x-ui.delete-button>`: `OrganizationCrudTest`
                             clica `@delete-organization-{id}` e espera o redirect imediato.
                             Adotar o modal de confirmação aqui é mudança de contrato de
                             teste, agendada para a Fase 7 junto com as demais exclusões. --}}
                        <form method="POST" action="{{ route('organizations.destroy', $organization) }}" dusk="delete-form-{{ $organization->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger link-danger" dusk="delete-organization-{{ $organization->id }}">Remover</x-ui.button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="5" message="Nenhuma Organização cadastrada." />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$organizations" />
@endsection
