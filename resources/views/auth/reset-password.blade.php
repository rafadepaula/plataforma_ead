@extends('layouts.guest')

@section('content')
    <x-layout.page-header level="h2" kicker="Recuperação de senha" title="Redefinir senha" />

    <form method="POST" action="{{ route('password.store') }}" dusk="reset-password-form">
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
            hint="Mínimo de 8 caracteres."
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
