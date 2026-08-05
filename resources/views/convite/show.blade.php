@php
    /** @var \App\Models\InvitationLink $invitationLink */
@endphp

@extends('layouts.guest')

@section('content')
    <div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
        <div>
            <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Convite</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 28px; margin: 8px 0 0;">
                Matrícula em {{ $invitationLink->course->title }}
            </h1>
            <p style="font-size: 13px; color: var(--color-neutral-600); margin-top: 8px;">
                Informe seu e-mail para continuar. Se você já tem conta na plataforma, basta confirmar sua senha.
            </p>
        </div>

        <x-help-button key="invitation.show" />
    </div>

    <form method="POST"
          action="{{ url('/convite/'.$invitationLink->token) }}"
          data-check-email-url="{{ url('/convite/check-email') }}"
          dusk="invitation-form"
          style="display: flex; flex-direction: column; gap: 16px;">
        @csrf

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

        <p data-invitation-field="existing-account-hint"
           style="display: none; font-size: 12px; color: var(--color-neutral-600); margin: -8px 0 0;"
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

        <x-ui.button type="submit" block dusk="invitation-submit">Matricular-me</x-ui.button>
    </form>
@endsection
