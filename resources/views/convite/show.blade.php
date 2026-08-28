@php
    $invitation = $invitation ?? $invitationLink ?? null;
    $course = $course ?? $invitation?->course ?? null;
    $courseTitle = $course?->title ?? '';
    $token = $invitation?->token ?? '';
@endphp

@extends('layouts.guest')

@section('content')
    <div class="mb-4">
        <div class="ds-overline text-primary mb-1">Convite</div>
        <h2 class="h3 fw-bold mb-0">{{ $courseTitle }}</h2>
    </div>

    <form method="POST"
          action="{{ route('invitation.store', $token) }}"
          data-check-email-url="{{ route('invitation.check-email') }}"
          data-smart-invitation
          dusk="invitation-form">
        @csrf

        <x-ui.input
            type="email"
            name="email"
            label="E-mail *"
            :value="old('email')"
            required
            autofocus
            data-invitation-email
            dusk="invitation-email"
            class="mb-3"
        />

        <div class="alert ds-band-blue border-0 rounded-3 p-3 mb-3 d-none"
             data-invitation-existing-hint
             data-invitation-field="existing-account-hint"
             dusk="invitation-existing-account-hint">
            <div class="d-flex align-items-center gap-2">
                <x-ui.icon name="info" size="18" class="text-primary" />
                <span>Já encontramos uma conta com este e-mail. Confirme sua senha para se matricular.</span>
            </div>
        </div>

        <div data-invitation-field="new-account">
            <x-ui.input
                name="name"
                label="Nome completo *"
                :value="old('name')"
                required
                data-invitation-name
                dusk="invitation-name"
                class="mb-3"
            />
        </div>

        <div data-invitation-field="new-account">
            <x-ui.input
                name="cpf"
                label="CPF *"
                :value="old('cpf')"
                required
                data-invitation-cpf
                dusk="invitation-cpf"
                class="mb-3"
            />
        </div>

        <div class="position-relative mb-3">
            <x-ui.input
                type="password"
                name="password"
                label="Senha *"
                required
                data-invitation-password
                dusk="invitation-password"
            />
        </div>

        <div data-invitation-field="new-account">
            <x-ui.input
                type="password"
                name="password_confirmation"
                label="Confirmar senha *"
                required
                data-invitation-password-confirmation
                dusk="invitation-password-confirmation"
                class="mb-3"
            />
        </div>

        <div class="form-check form-switch mb-4">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   name="consent"
                   id="consent"
                   required
                   value="1"
                   dusk="invitation-consent">
            <label class="form-check-label small" for="consent">
                Concordo em compartilhar meus dados com a organização responsável por este curso.
            </label>
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-100" dusk="invitation-submit">
            Matricular-me
        </x-ui.button>
    </form>
@endsection
