@php
    $invitation = $invitation ?? $invitationLink ?? null;
    $course = $course ?? $invitation?->course ?? null;
    $courseTitle = $course?->title ?? '';
    $token = $invitation?->token ?? '';
@endphp

@extends('layouts.guest')

@section('content')
    {{-- `level="h2"`: o `h1` da página é o do painel institucional do shell. --}}
    <x-layout.page-header
        kicker="Convite"
        :title="'Matrícula em '.$courseTitle"
        level="h2"
        subtitle="Informe seu e-mail para continuar. Se você já tem conta na plataforma, basta confirmar sua senha."
    />

    <form method="POST"
          action="{{ route('invitation.store', $token) }}"
          data-check-email-url="{{ route('invitation.check-email') }}"
          data-smart-invitation
          dusk="invitation-form">
        @csrf

        <x-ui.input
            type="email"
            name="email"
            label="E-mail"
            :value="old('email')"
            required
            autofocus
            data-invitation-email
            dusk="invitation-email"
        />

        {{-- Informação neutra, não alerta: bloco em `--blue-50`. O estado
             escondido é a classe `d-none`, alternada pelo
             `SmartInvitationForm.toggleFields()` — nunca o atributo `hidden`. --}}
        <p class="guest-hint mb-3 d-none"
           data-invitation-existing-hint
           data-invitation-field="existing-account-hint"
           dusk="invitation-existing-account-hint">
            Já encontramos uma conta com este e-mail. Confirme sua senha para se matricular.
        </p>

        <div data-invitation-field="new-account">
            <x-ui.input
                name="name"
                label="Nome completo"
                :value="old('name')"
                required
                data-invitation-name
                dusk="invitation-name"
            />
        </div>

        <div data-invitation-field="new-account">
            <x-ui.input
                name="cpf"
                label="CPF"
                :value="old('cpf')"
                required
                data-invitation-cpf
                dusk="invitation-cpf"
            />
        </div>

        {{-- A senha aparece nos dois estados: cadastro novo e vínculo de conta. --}}
        <x-ui.input
            type="password"
            name="password"
            label="Senha"
            required
            data-invitation-password
            dusk="invitation-password"
        />

        <div data-invitation-field="new-account">
            <x-ui.input
                type="password"
                name="password_confirmation"
                label="Confirmar senha"
                required
                data-invitation-password-confirmation
                dusk="invitation-password-confirmation"
            />
        </div>

        {{-- Interruptor obrigatório: o erro vem em contorno `.is-invalid` mais
             mensagem, nunca só a bolha nativa do navegador. --}}
        <div class="mb-4">
            <x-ui.switch
                name="consent"
                value="1"
                required
                label="Concordo em compartilhar meus dados com a organização responsável por este curso."
                dusk="invitation-consent"
            />
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-100" dusk="invitation-submit">
            Matricular-me
        </x-ui.button>
    </form>
@endsection
