{{--
    `/admin/users/{user}` (`admin.users.show`), served by
    `App\Http\Controllers\Admin\UserAdminController::show()` (Bucket A).

    Perfil completo, somente leitura, de um usuário — visível para
    qualquer Organização à qual ele esteja vinculado (ou nenhuma, no caso
    de um Admin do sistema). Segue a composição de `<x-layout.page-header>`
    + `<x-ui.card>` já usada nas telas de edição do módulo (ver
    `users/edit.blade.php`), sem markup Bootstrap cru nem `style=`.

    Layout de duas colunas: a coluna esquerda mantém a `<dl>` somente
    leitura original (os 11 seletores `dusk` congelados no snapshot,
    inalterados); a coluna direita soma cards de contexto — matrículas
    (`User::courses()`) e certificados (`User::certificates()`) — que não
    carregam nenhum `dusk` novo (puramente informativo, fora do contrato
    de teste).

    Expected variable: `$user` (com `organization`, `roles`, `courses` e
    `certificates.course` pré-carregados — ver `UserAdminController::show()`).
--}}
@extends('layouts.app')

@section('content')
    <x-slot:title>{{ $user->name }} — Usuários (Administração Global) — Plataforma EAD</x-slot:title>

    <div dusk="admin-user-show">
        <x-layout.page-header kicker="Administração"
                              :title="$user->name"
                              :breadcrumb="[
                                  ['label' => 'Administração'],
                                  ['label' => 'Usuários', 'url' => route('admin.users.index')],
                                  ['label' => $user->name],
                              ]">
            <x-slot:actions>
                <x-ui.button variant="secondary" href="{{ route('admin.users.index') }}" dusk="back-to-admin-users">Voltar</x-ui.button>
                <x-ui.button href="{{ route('admin.users.edit', $user) }}" dusk="edit-admin-user">Editar</x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <x-ui.card>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nome</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-name">{{ $user->name }}</dd>

                        <dt class="col-sm-4">E-mail</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-email">{{ $user->email }}</dd>

                        <dt class="col-sm-4">CPF</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-cpf">{{ $user->cpf ?? '—' }}</dd>

                        <dt class="col-sm-4">Organização</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-organization">{{ $user->organization->name ?? 'Nenhuma — Admin do Sistema' }}</dd>

                        <dt class="col-sm-4">Papel</dt>
                        <dd class="col-sm-8">
                            <x-ui.badge variant="accent" data-role="{{ $user->getRoleNames()->first() ?? '' }}" dusk="admin-user-show-role">
                                {{ \App\Enums\Permissions\RolesEnum::label($user->getRoleNames()->first() ?? '') }}
                            </x-ui.badge>
                        </dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <x-ui.badge :variant="$user->status === 'active' ? 'accent' : 'neutral'"
                                        data-status="{{ $user->status }}"
                                        dusk="admin-user-show-status">
                                {{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}
                            </x-ui.badge>
                        </dd>

                        <dt class="col-sm-4">Criado em</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-created-at">{{ $user->created_at?->format('d/m/Y H:i:s') }}</dd>

                        <dt class="col-sm-4">Atualizado em</dt>
                        <dd class="col-sm-8" dusk="admin-user-show-updated-at">{{ $user->updated_at?->format('d/m/Y H:i:s') }}</dd>
                    </dl>
                </x-ui.card>
            </div>

            <div class="col-12 col-lg-6 d-flex flex-column gap-4">
                <x-ui.card title="Matrículas">
                    @forelse ($user->courses as $course)
                        <div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom border-secondary">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $course->title }}</div>
                                <div class="small text-body-secondary">
                                    Matriculado em {{ $course->pivot->enrolled_at ? \Illuminate\Support\Carbon::parse($course->pivot->enrolled_at)->format('d/m/Y') : '—' }}
                                </div>
                            </div>

                            <x-ui.badge :variant="$course->pivot->status === 'active' ? 'accent' : 'neutral'">
                                {{ match ($course->pivot->status) {
                                    'active' => 'Ativo',
                                    'completed' => 'Concluído',
                                    'cancelled' => 'Cancelado',
                                    default => $course->pivot->status,
                                } }}
                            </x-ui.badge>
                        </div>
                    @empty
                        <p class="small text-body-secondary mb-0">Nenhuma matrícula encontrada.</p>
                    @endforelse
                </x-ui.card>

                <x-ui.card title="Certificados">
                    @forelse ($user->certificates as $certificate)
                        <div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom border-secondary">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $certificate->course->title ?? '—' }}</div>
                                <div class="small text-body-secondary">
                                    Emitido em {{ optional($certificate->issued_at)->format('d/m/Y') ?? '—' }}
                                </div>
                            </div>

                            <x-ui.badge :variant="$certificate->isRevoked() ? 'accent-2' : 'accent'">
                                {{ $certificate->isRevoked() ? 'Revogado' : 'Emitido' }}
                            </x-ui.badge>
                        </div>
                    @empty
                        <p class="small text-body-secondary mb-0">Nenhum certificado emitido.</p>
                    @endforelse
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection
