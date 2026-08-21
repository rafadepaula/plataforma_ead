@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $invitationLinks */
@endphp

@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title]]"
        :kicker="$course->title"
        title="Links de Convite"
        subtitle="Gere links para que alunos se matriculem sozinhos neste curso."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.index') }}">Voltar aos Cursos</x-ui.button>
            <x-ui.button icon="plus" href="{{ route('courses.invitation-links.create', $course) }}" dusk="new-invitation-link">Novo Link</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.data-table striped hover responsive
                     :headers="['Link', 'Usos', 'Expira em', 'Status', 'Ações']">
        @forelse($invitationLinks as $invitationLink)
            @php
                $fullLink = url('/convite/'.$invitationLink->token);
            @endphp
            <tr dusk="invitation-link-row-{{ $invitationLink->id }}">
                <td class="max-w-400" data-label="Link">
                    <div class="d-flex align-items-center gap-2">
                        <code class="text-truncate flex-1 min-w-0" title="{{ $fullLink }}">{{ $fullLink }}</code>
                        <button type="button"
                                class="btn btn-ghost btn-sm d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                data-copy-link="{{ $fullLink }}"
                                aria-label="Copiar link de convite">
                            <x-ui.icon name="file-text" size="16" aria-hidden="true" />
                        </button>
                    </div>
                </td>
                <td class="text-nowrap" data-label="Usos">
                    {{ $invitationLink->current_uses }}{{ $invitationLink->max_uses ? '/'.$invitationLink->max_uses : '' }}
                </td>
                <td class="text-nowrap" data-label="Expira em">
                    {{ $invitationLink->expires_at?->format('d/m/Y H:i') ?? 'Sem expiração' }}
                </td>
                <td data-label="Status">
                    @if($invitationLink->revoked_at)
                        <x-ui.badge variant="neutral">Revogado</x-ui.badge>
                    @elseif($invitationLink->isUsable())
                        <x-ui.badge variant="accent">Ativo</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Expirado ou esgotado</x-ui.badge>
                    @endif
                </td>
                <td data-label="Ações">
                    @unless($invitationLink->revoked_at)
                        {{--
                            Form preservado (sem <x-ui.delete-button>/<x-ui.confirm-modal>):
                            o contrato §7.11 do inventário exige que o seletor
                            revoke-form-{id} suma imediatamente ao submeter, sem passo de
                            confirmação intermediário.
                        --}}
                        <form method="POST"
                              action="{{ route('invitation-links.destroy', $invitationLink) }}"
                              dusk="revoke-form-{{ $invitationLink->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="danger"
                                         size="sm"
                                         type="submit"
                                         icon="x"
                                         dusk="revoke-invitation-link-{{ $invitationLink->id }}">Revogar</x-ui.button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <x-ui.empty-state
                colspan="5"
                icon="message-square"
                title="Nenhum link de convite gerado para este curso."
                description="Crie um novo link para permitir que alunos se matriculem sozinhos."
            >
                <x-slot:action>
                    <x-ui.button icon="plus" href="{{ route('courses.invitation-links.create', $course) }}">Novo Link</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$invitationLinks" />

    @push('scripts')
        <script>
            // Botão só-ícone "copiar" de cada link de convite — inline, sem novo
            // módulo em resources/js/, mesmo padrão de certificates/index.blade.php.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-copy-link]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var value = button.getAttribute('data-copy-link');

                        navigator.clipboard.writeText(value).then(function () {
                            if (window.NotificationService) {
                                window.NotificationService.success('Link copiado.');
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
@endsection
