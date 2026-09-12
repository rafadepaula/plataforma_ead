@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $students */

    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@extends('layouts.app')

@section('content')
    <div dusk="gestor-students-index">
        <x-layout.page-header
            :breadcrumb="[['label' => 'Organização'], ['label' => 'Alunos Matriculados']]"
            kicker="Organização"
            title="Alunos Matriculados"
            subtitle="Visualize e gerencie os Alunos matriculados nos cursos da sua Organização."
        >
            <x-slot:actions>
                <x-ui.button variant="primary" href="{{ route('gestor.students.create') }}" dusk="create-student">Cadastrar aluno</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('users.import.create') }}" dusk="import-students">Importar CSV</x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        <x-ui.filter-bar :action="route('gestor.students.index')"
                         :reset-url="route('gestor.students.index')"
                         label="Filtros de alunos"
                         dusk="gestor-students-filter-form">
            <div class="col-12 col-lg">
                <x-ui.input name="search"
                            label="Buscar por nome, e-mail ou CPF"
                            :value="$search"
                            dusk="gestor-students-search" />
            </div>
        </x-ui.filter-bar>

        <x-ui.data-table striped hover responsive
                         :headers="['Aluno', 'CPF', 'Cursos', 'Status', 'Ações']">
            @forelse($students as $student)
                <tr dusk="student-row-{{ $student->id }}">
                    <td data-label="Aluno">
                        <div class="d-flex align-items-center gap-3">
                            <x-ui.avatar :initials="$initialsFor($student->name)" />
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $student->name }}</div>
                                <div class="small text-body-secondary text-truncate">{{ $student->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="CPF" class="ds-tabular-nums">{{ $student->cpf ?? '—' }}</td>
                    <td data-label="Cursos">
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($student->courses as $course)
                                <x-ui.badge variant="neutral">{{ $course->title }}</x-ui.badge>
                            @empty
                                <span class="text-body-secondary">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td data-label="Status">
                        @if($student->account_status === 'active')
                            <x-ui.badge variant="success" dusk="student-status-{{ $student->id }}">Ativo</x-ui.badge>
                        @elseif($student->account_status === 'pending')
                            <x-ui.badge variant="info" dusk="student-status-{{ $student->id }}">Convite pendente</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dusk="student-status-{{ $student->id }}">Inativo</x-ui.badge>
                        @endif
                    </td>
                    <td data-label="Ações">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <x-ui.button variant="secondary"
                                         size="sm"
                                         href="{{ route('gestor.students.edit', $student) }}"
                                         dusk="edit-student-{{ $student->id }}">Editar</x-ui.button>

                            {{--
                                Link de convite único deste aluno:
                                "Copiar convite" (fetch + clipboard) é
                                get-or-create — idempotente; "Renovar" é um
                                form com confirm-modal (padrão declarativo
                                da tela) que revoga o token vivo e emite
                                outro, devolvido no banner de flash.
                            --}}
                            <x-ui.button variant="ghost"
                                         size="sm"
                                         type="button"
                                         icon="clipboard"
                                         data-issue-invitation="{{ route('gestor.students.invitations.issue', $student) }}"
                                         dusk="copy-invitation-{{ $student->id }}">Copiar convite</x-ui.button>

                            <x-ui.button variant="ghost"
                                         size="sm"
                                         type="button"
                                         data-bs-toggle="modal"
                                         data-bs-target="#renew-invitation-{{ $student->id }}"
                                         dusk="renew-invitation-{{ $student->id }}">Renovar</x-ui.button>

                            <x-ui.button variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#delete-student-{{ $student->id }}"
                                         dusk="delete-student-{{ $student->id }}">Remover</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="5" icon="user" title="Nenhum Aluno matriculado ainda." description="Matricule Alunos nos cursos da sua Organização por convite, importação de CSV ou matrícula manual.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('users.import.create') }}">Importar CSV</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </x-ui.data-table>

        {{-- Modais de confirmação ficam fora da tabela para evitar recorte pelo wrapper responsivo. --}}
        @foreach($students as $student)
            <x-ui.confirm-modal id="delete-student-{{ $student->id }}"
                                title="Confirmar remoção"
                                :action="route('gestor.students.destroy', $student)"
                                method="DELETE"
                                confirm-label="Remover"
                                message="Remover {{ $student->name }} da organização? Esta ação não poderá ser desfeita."
                                dusk="delete-form-{{ $student->id }}" />

            <x-ui.confirm-modal id="renew-invitation-{{ $student->id }}"
                                title="Renovar link de convite"
                                :action="route('gestor.students.invitations.regenerate', $student)"
                                method="POST"
                                confirm-label="Renovar"
                                message="Renovar o link de convite de {{ $student->name }}? O link atual deixará de funcionar e um novo será gerado."
                                confirm-dusk="renew-invitation-confirm-{{ $student->id }}" />
        @endforeach

        @if(session('invitation_url'))
            <div class="alert alert-success d-flex flex-wrap align-items-center gap-2" dusk="invitation-flash">
                <span class="flex-1 min-w-0 text-truncate" dusk="invitation-flash-link">{{ session('invitation_url') }}</span>
                <button type="button"
                        class="btn btn-sm btn-primary flex-shrink-0"
                        data-copy-link="{{ session('invitation_url') }}"
                        dusk="copy-invitation-flash">Copiar novo link</button>
            </div>
        @endif

        <x-ui.pagination :paginator="$students" />
    </div>
@endsection

@push('scripts')
    <script>
        // "Copiar convite" (e o banner de flash pós-renovação): a URL vai
        // para a área de transferência com toast de confirmação — inline,
        // sem novo módulo em resources/js/, mesmo padrão dos demais.
        document.addEventListener('DOMContentLoaded', function () {
            // `navigator.clipboard` só existe em contexto seguro (HTTPS ou
            // localhost); em hosts HTTP puros (ex.: `laravel.test` interno
            // do Docker) cai no fallback legado `execCommand('copy')`.
            function copyInvitationText(value) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(value);
                }

                var textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();

                try {
                    document.execCommand('copy');
                } finally {
                    textarea.remove();
                }

                return Promise.resolve();
            }
            document.querySelectorAll('[data-issue-invitation], [data-copy-link]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var value = button.getAttribute('data-issue-invitation')
                        ? null
                        : button.getAttribute('data-copy-link');

                    var promise = value !== null
                        ? Promise.resolve(value)
                        : fetch(button.getAttribute('data-issue-invitation'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                            },
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('request failed');
                            }

                            return response.json();
                        }).then(function (payload) {
                            return payload.url;
                        });

                    promise.then(function (url) {
                        return copyInvitationText(url);
                    }).then(function () {
                        if (window.NotificationService) {
                            window.NotificationService.success('Link de convite copiado. Envie ao aluno para ele finalizar o cadastro.');
                        }
                    }).catch(function () {
                        if (window.NotificationService) {
                            window.NotificationService.error('Não foi possível copiar o link de convite.');
                        }
                    });
                });
            });
        });
    </script>
@endpush
