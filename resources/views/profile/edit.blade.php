{{--
    profile self-service screen.  no
    `{user}` route param exists, the target is always `Auth::user()`.
     global help coverage already comes from `layouts.app`'s
    topbar, which mounts `<x-help-button :key="Route::currentRouteName()" />`
    on every authenticated screen — see `help-conventions` skill: a
    second explicit `<x-help-button>` here would render two buttons keyed
    to the same route, so this page intentionally does not add one
    (unlike `convite/show.blade.php`/`public/certificates/show.blade.php`,
    which use `layouts.guest`/no layout and therefore lack that global
    button).
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Meu Perfil']]"
        kicker="Conta"
        title="Meu Perfil"
        subtitle="Atualize seus dados cadastrais e sua senha de acesso."
    />

    {{-- `col-lg-6` substitui o antigo `max-width: 560px`: o Bootstrap não tem
         utility de largura máxima em px, e a coluna do grid é o degrau
         responsivo equivalente já adotado em `users/create`. --}}
    <div class="row">
        <div class="col-12 col-lg-6 d-flex flex-column gap-4">
            <x-ui.card title="Informações do Perfil" kicker="Dados Cadastrais">
                {{-- Organização e status são somente-leitura aqui de propósito:
                     `ProfileController::update()` nunca aceita `org_id`/`status`
                     do request — só Admin/Gestor mudam isso, em telas próprias. --}}
                <dl class="row mb-4">
                    <dt class="col-sm-4 ds-muted">Organização</dt>
                    <dd class="col-sm-8">{{ $user->organization->name ?? 'Nenhuma — Admin do Sistema' }}</dd>

                    <dt class="col-sm-4 ds-muted">Status</dt>
                    <dd class="col-sm-8">
                        <x-ui.badge :variant="$user->status === 'active' ? 'accent' : 'neutral'" data-status="{{ $user->status }}">
                            {{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}
                        </x-ui.badge>
                    </dd>
                </dl>

                <form method="POST" action="{{ route('profile.update') }}" dusk="profile-form">
                    @csrf
                    @method('PATCH')

                    <x-ui.field-stack>
                        <x-ui.input name="name" label="Nome" required value="{{ old('name', $user->name) }}" />

                        <x-ui.input name="email" type="email" label="E-mail" required value="{{ old('email', $user->email) }}" />

                        <x-ui.input name="cpf" label="CPF" value="{{ old('cpf', $user->cpf) }}" hint="Opcional." />
                    </x-ui.field-stack>

                    <x-ui.form-actions>
                        <x-ui.button type="submit" dusk="profile-submit">Salvar Alterações</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>

            <x-ui.card title="Atualizar Senha" kicker="Segurança">
                {{-- `PasswordController::update()` chama `Auth::logoutOtherDevices()`
                     antes de rotacionar a senha — todas as demais sessões
                     ativas do usuário são encerradas. Este alerta é o aviso
                     obrigatório dessa consequência, exibido antes do envio. --}}
                <x-ui.alert variant="info" class="mb-4">
                    Ao trocar sua senha, todas as suas outras sessões ativas serão desconectadas.
                </x-ui.alert>

                <form method="POST" action="{{ route('password.update') }}" dusk="password-form">
                    @csrf
                    @method('PUT')

                    <x-ui.field-stack>
                        <x-ui.input name="current_password" type="password" label="Senha Atual" required />

                        <x-ui.input name="password" type="password" label="Nova Senha" required />

                        <x-ui.input name="password_confirmation" type="password" label="Confirmar Nova Senha" required />
                    </x-ui.field-stack>

                    <x-ui.form-actions>
                        <x-ui.button type="submit" dusk="password-submit">Atualizar Senha</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
