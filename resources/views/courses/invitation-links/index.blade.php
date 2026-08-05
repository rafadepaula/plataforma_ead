@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $invitationLinks */
@endphp

@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">{{ $course->title }}</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Links de Convite</h1>
        </div>

        <div style="display: flex; gap: 8px;">
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
            <x-ui.button href="{{ route('courses.invitation-links.create', $course) }}" dusk="new-invitation-link">Novo Link</x-ui.button>
        </div>
    </div>

    <x-ui.table :headers="['Token', 'Usos', 'Expira em', 'Status', 'Ações']">
        @forelse($invitationLinks as $invitationLink)
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="invitation-link-row-{{ $invitationLink->id }}">
                <td style="padding: 12px 16px; word-break: break-all;">
                    <code>{{ url('/convite/'.$invitationLink->token) }}</code>
                </td>
                <td style="padding: 12px 16px;">
                    {{ $invitationLink->current_uses }}{{ $invitationLink->max_uses ? '/'.$invitationLink->max_uses : '' }}
                </td>
                <td style="padding: 12px 16px;">
                    {{ $invitationLink->expires_at?->format('d/m/Y H:i') ?? 'Sem expiração' }}
                </td>
                <td style="padding: 12px 16px;">
                    @if($invitationLink->revoked_at)
                        <x-ui.badge variant="neutral">Revogado</x-ui.badge>
                    @elseif($invitationLink->isUsable())
                        <x-ui.badge variant="accent">Ativo</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Expirado/Esgotado</x-ui.badge>
                    @endif
                </td>
                <td style="padding: 12px 16px;">
                    @unless($invitationLink->revoked_at)
                        <form method="POST" action="{{ route('invitation-links.destroy', $invitationLink) }}" dusk="revoke-form-{{ $invitationLink->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost" dusk="revoke-invitation-link-{{ $invitationLink->id }}">Revogar</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                    Nenhum link de convite gerado para este Curso.
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div style="margin-top: 20px;">
        {{ $invitationLinks->links() }}
    </div>
@endsection
