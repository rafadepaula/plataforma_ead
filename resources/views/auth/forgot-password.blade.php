@extends('layouts.guest')

@section('content')
    <div style="margin-bottom: 24px;">
        <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Recuperação de senha</span>
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 26px; margin: 8px 0 0;">Esqueceu sua senha?</h1>
        <p style="font-size: 13px; color: var(--color-neutral-600); margin-top: 8px; line-height: 1.5;">
            Informe o e-mail da sua conta e enviaremos um link de uso único para redefinir sua senha.
        </p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" dusk="forgot-password-form" style="display: flex; flex-direction: column; gap: 16px;">
        @csrf

        <x-ui.input
            type="email"
            name="email"
            label="E-mail"
            value="{{ old('email') }}"
            required
            autofocus
            dusk="forgot-password-email"
        />

        <x-ui.button type="submit" block dusk="forgot-password-submit">Enviar link de redefinição</x-ui.button>

        <a href="{{ route('login') }}"
           dusk="back-to-login-link"
           style="font-size: 13px; text-align: center; color: var(--color-accent); text-decoration: none; font-weight: 600;">
            Voltar para o login
        </a>
    </form>
@endsection
