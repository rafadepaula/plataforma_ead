@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $invitationLinks */
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$course->title" title="Links de Convite">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
            <x-ui.button href="{{ route('courses.invitation-links.create', $course) }}" dusk="new-invitation-link">Novo Link</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.data-table striped hover responsive
                     :headers="['Token', 'Usos', 'Expira em', 'Status', 'Ações']">
        @forelse($invitationLinks as $invitationLink)
            <tr dusk="invitation-link-row-{{ $invitationLink->id }}">
                <td>
                    <code class="text-break">{{ url('/convite/'.$invitationLink->token) }}</code>
                </td>
                <td class="text-nowrap">
                    {{ $invitationLink->current_uses }}{{ $invitationLink->max_uses ? '/'.$invitationLink->max_uses : '' }}
                </td>
                <td class="text-nowrap">
                    {{ $invitationLink->expires_at?->format('d/m/Y H:i') ?? 'Sem expiração' }}
                </td>
                <td>
                    @if($invitationLink->revoked_at)
                        <x-ui.badge variant="neutral">Revogado</x-ui.badge>
                    @elseif($invitationLink->isUsable())
                        <x-ui.badge variant="accent">Ativo</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Expirado/Esgotado</x-ui.badge>
                    @endif
                </td>
                <td>
                    @unless($invitationLink->revoked_at)
                        {{--
                            Form preservado (sem <x-ui.delete-button>): o componente
                            põe o $attributes no BOTÃO, então o seletor
                            revoke-form-{id} — contrato §7.11 do inventário —
                            deixaria de existir.
                        --}}
                        <form method="POST"
                              action="{{ route('invitation-links.destroy', $invitationLink) }}"
                              dusk="revoke-form-{{ $invitationLink->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="ghost"
                                         size="sm"
                                         type="submit"
                                         class="text-danger link-danger"
                                         dusk="revoke-invitation-link-{{ $invitationLink->id }}">Revogar</x-ui.button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="5" message="Nenhum link de convite gerado para este Curso." />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$invitationLinks" />
@endsection
