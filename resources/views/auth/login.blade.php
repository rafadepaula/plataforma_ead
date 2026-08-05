@extends('layouts.guest')

@section('content')
    <div style="margin-bottom: 32px;">
        <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Acesso</span>
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 28px; margin: 8px 0 0;">Entrar na plataforma</h1>
    </div>

    <form method="POST" action="{{ route('login') }}" dusk="login-form" style="display: flex; flex-direction: column; gap: 16px;">
        @csrf

        <x-ui.input
            type="email"
            name="email"
            label="E-mail"
            value="{{ old('email') }}"
            required
            autofocus
            dusk="login-email"
        />

        <x-ui.input
            type="password"
            name="password"
            label="Senha"
            required
            dusk="login-password"
        />

        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--color-text);">
            <input type="checkbox" name="remember" dusk="login-remember">
            Lembrar-me
        </label>

        <x-ui.button type="submit" block dusk="login-submit">Entrar</x-ui.button>

        <a href="{{ route('password.request') }}"
           dusk="forgot-password-link"
           style="font-size: 13px; text-align: center; color: var(--color-accent); text-decoration: none; font-weight: 600;">
            Esqueceu sua senha?
        </a>
    </form>
@endsection
