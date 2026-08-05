@extends('layouts.guest')

@section('content')
    <div style="margin-bottom: 24px;">
        <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Recuperação de senha</span>
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 26px; margin: 8px 0 0;">Redefinir senha</h1>
    </div>

    <form method="POST" action="{{ route('password.store') }}" dusk="reset-password-form" style="display: flex; flex-direction: column; gap: 16px;">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.input
            type="email"
            name="email"
            label="E-mail"
            value="{{ old('email', $request->query('email')) }}"
            required
            autofocus
            dusk="reset-password-email"
        />

        <x-ui.input
            type="password"
            name="password"
            label="Nova senha"
            required
            dusk="reset-password-password"
        />

        <x-ui.input
            type="password"
            name="password_confirmation"
            label="Confirme a nova senha"
            required
            dusk="reset-password-password-confirmation"
        />

        <x-ui.button type="submit" block dusk="reset-password-submit">Redefinir senha</x-ui.button>
    </form>
@endsection
