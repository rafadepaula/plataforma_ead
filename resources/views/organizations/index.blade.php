@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Administração" title="Organizações">
        <x-slot:actions>
            <x-ui.button href="{{ route('organizations.create') }}" dusk="new-organization">Nova Organização</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{--  este banner permanece: é a confirmação imediata da ação
         "Entrar como", no ponto de origem e com espaço para a frase inteira.
         O badge da topbar cobre um problema diferente (a PERSISTÊNCIA do
         sinal nas demais telas). Os dois não brigam: o alerta é
         `dismissable` e o badge é imutável. `$activeOrganization` vem do
         controller (mesma resolução da topbar), não de uma query aqui. --}}
    @if($activeOrganization ?? null)
        <x-ui.alert variant="warning" dismissable>
            Você está no contexto da Organização
            <strong>{{ $activeOrganization->name }}</strong>.
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

                        {{--  o "Remover" agora só ABRE o modal de confirmação
                             (gatilho declarativo `data-bs-toggle`/`data-bs-target`).
                             O `DELETE` real vive no `<form>` embutido em
                             `<x-ui.confirm-modal>`. Mesmo par gatilho + modal de
                             `users/index.blade.php`: o `id` do modal é sufixado com
                             `$organization->id`, então cada linha do loop tem o seu
                             próprio modal, sem id duplicado no DOM. --}}
                        <x-ui.button variant="ghost"
                                     size="sm"
                                     class="text-danger link-danger"
                                     data-bs-toggle="modal"
                                     data-bs-target="#delete-organization-{{ $organization->id }}"
                                     dusk="delete-organization-{{ $organization->id }}">Remover</x-ui.button>
                    </div>

                    <x-ui.confirm-modal id="delete-organization-{{ $organization->id }}"
                                        title="Remover Organização"
                                        :action="route('organizations.destroy', $organization)"
                                        method="DELETE"
                                        confirm-label="Remover"
                                        :message="'Remover a Organização “'.$organization->name.'” também tira do ar seus usuários, cursos e certificados. Esta ação não poderá ser desfeita.'"
                                        dusk="delete-form-{{ $organization->id }}" />
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="5" message="Nenhuma Organização cadastrada." />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$organizations" />
@endsection
