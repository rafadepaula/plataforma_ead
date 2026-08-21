@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $organizations */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Administração'], ['label' => 'Organizações']]"
        kicker="Administração"
        title="Organizações"
        subtitle="Gerencie as Organizações que utilizam a plataforma, seus dados e o status de acesso."
    >
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

    {{-- Tabela — visível a partir de `md`; abaixo disso a lista de cards assume. --}}
    <div class="d-none d-md-block">
        <x-ui.data-table striped hover responsive :headers="['Organização', 'CNPJ', 'Status', 'Ações']">
            @forelse($organizations as $organization)
                <tr dusk="organization-row-{{ $organization->id }}">
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <x-ui.avatar :initials="$initialsFor($organization->name)" />
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $organization->name }}</div>
                                <div class="small text-body-secondary font-monospace text-truncate">{{ $organization->slug }}</div>
                            </div>
                        </div>
                    </td>
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
                            <x-ui.button variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#delete-organization-{{ $organization->id }}"
                                         dusk="delete-organization-{{ $organization->id }}">Remover</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="4" icon="home" title="Nenhuma Organização cadastrada." description="Cadastre a primeira Organização para começar a liberar acesso a Gestores e Alunos.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('organizations.create') }}">Criar Organização</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </x-ui.data-table>
    </div>

    {{-- Lista de cards — abaixo de `md`, substitui a tabela (mesmo conteúdo, sem `dusk` duplicado). --}}
    <div class="d-md-none d-flex flex-column gap-3">
        @forelse($organizations as $organization)
            <x-ui.card>
                <x-slot:kickerSlot>
                    <x-ui.badge :variant="$organization->status === 'active' ? 'accent' : 'neutral'">
                        {{ $organization->status === 'active' ? 'Ativo' : 'Inativo' }}
                    </x-ui.badge>
                </x-slot:kickerSlot>

                <div class="d-flex align-items-center gap-3 mb-2">
                    <x-ui.avatar :initials="$initialsFor($organization->name)" />
                    <div class="min-w-0">
                        <div class="fw-semibold">{{ $organization->name }}</div>
                        <div class="small text-body-secondary font-monospace text-truncate">{{ $organization->slug }}</div>
                    </div>
                </div>

                <p class="mb-0">CNPJ: {{ $organization->cnpj ?? '—' }}</p>

                <x-slot:metaSlot>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('impersonate-org.store', $organization) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Entrar como</button>
                        </form>
                        <x-ui.button href="{{ route('organizations.edit', $organization) }}">Editar</x-ui.button>
                        <x-ui.button variant="danger" data-bs-toggle="modal" data-bs-target="#delete-organization-{{ $organization->id }}">Remover</x-ui.button>
                    </div>
                </x-slot:metaSlot>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="home" title="Nenhuma Organização cadastrada." description="Cadastre a primeira Organização para começar a liberar acesso a Gestores e Alunos.">
                <x-slot:action>
                    <x-ui.button href="{{ route('organizations.create') }}">Criar Organização</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse
    </div>

    {{-- Modais de confirmação — fora dos wrappers `d-none`/`d-md-none`: um
         ancestral `display:none` suprime toda a subárvore mesmo depois do
         Bootstrap alternar `.show` no modal, então o modal precisa existir
         fora de qualquer bloco condicional por breakpoint para ser
         alcançável tanto pelo gatilho desktop quanto pelo mobile. --}}
    @foreach($organizations as $organization)
        <x-ui.confirm-modal id="delete-organization-{{ $organization->id }}"
                            title="Remover Organização"
                            :action="route('organizations.destroy', $organization)"
                            method="DELETE"
                            confirm-label="Remover"
                            :message="'Remover a Organização “'.$organization->name.'” também tira do ar seus usuários, cursos e certificados. Esta ação não poderá ser desfeita.'"
                            dusk="delete-form-{{ $organization->id }}" />
    @endforeach

    <x-ui.pagination :paginator="$organizations" />
@endsection
