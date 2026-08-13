@extends('layouts.guest')

@section('content')
    <x-layout.page-header kicker="Acesso" title="Entrar na plataforma" />

    <form method="POST" action="{{ route('login') }}" dusk="login-form">
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

        <x-ui.checkbox
            name="remember"
            label="Lembrar-me"
            dusk="login-remember"
        />

        <x-ui.button type="submit" block dusk="login-submit">Entrar</x-ui.button>

        <div class="text-center mt-4x">
            <a href="{{ route('password.request') }}"
               dusk="forgot-password-link"
               class="small fw-semibold text-primary text-decoration-none">
                Esqueceu sua senha?
            </a>
        </div>
    </form>
@endsection
