@php
    /** @var \App\Models\InvitationLink $invitationLink */
@endphp

@extends('layouts.guest')

@section('content')
    <x-layout.page-header
        kicker="Convite"
        :title="'Matrícula em '.$invitationLink->course->title"
        subtitle="Informe seu e-mail para continuar. Se você já tem conta na plataforma, basta confirmar sua senha."
    >
        <x-slot:actions>
            <x-help-button key="invitation.show" />
        </x-slot:actions>
    </x-layout.page-header>

    <form method="POST"
          action="{{ url('/convite/'.$invitationLink->token) }}"
          data-check-email-url="{{ url('/convite/check-email') }}"
          dusk="invitation-form">
        @csrf

        <x-ui.field-stack>
            <x-ui.input
                type="email"
                name="email"
                label="E-mail"
                value="{{ old('email') }}"
                required
                autofocus
                dusk="invitation-email"
                data-invitation-email
            />

            {{--
                Estado inicial oculto via `d-none`: `SmartInvitationForm.toggleFields()`
                alterna esta dica com `classList.toggle('d-none', !exists)`, então
                "mostrar" significa remover a classe.
            --}}
            <p data-invitation-field="existing-account-hint"
               class="small text-body-secondary mb-3 d-none"
               dusk="invitation-existing-account-hint">
                Já encontramos uma conta com este e-mail. Confirme sua senha para se matricular.
            </p>

            <div data-invitation-field="new-account">
                <x-ui.input
                    type="text"
                    name="name"
                    label="Nome completo"
                    value="{{ old('name') }}"
                    required
                    dusk="invitation-name"
                />
            </div>

            <div data-invitation-field="new-account">
                <x-ui.input
                    type="text"
                    name="cpf"
                    label="CPF"
                    value="{{ old('cpf') }}"
                    required
                    dusk="invitation-cpf"
                />
            </div>

            <x-ui.input
                type="password"
                name="password"
                label="Senha"
                required
                dusk="invitation-password"
            />

            <div data-invitation-field="new-account">
                <x-ui.input
                    type="password"
                    name="password_confirmation"
                    label="Confirmar senha"
                    required
                    dusk="invitation-password-confirmation"
                />
            </div>

            <x-ui.switch
                name="consent"
                label="Concordo em compartilhar meus dados com a organização responsável por este curso."
                required
            />
        </x-ui.field-stack>

        <x-ui.button type="submit" block dusk="invitation-submit">Matricular-me</x-ui.button>
    </form>
@endsection
