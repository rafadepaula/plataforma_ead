@extends('layouts.guest')

@section('content')
    <x-layout.page-header
        kicker="Recuperação de senha"
        title="Esqueceu sua senha?"
        subtitle="Informe o e-mail da sua conta e enviaremos um link de uso único para redefinir sua senha."
    />

    <form method="POST" action="{{ route('password.email') }}" dusk="forgot-password-form">
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

        <div class="text-center mt-4x">
            <a href="{{ route('login') }}"
               dusk="back-to-login-link"
               class="small fw-semibold text-primary text-decoration-none">
                Voltar para o login
            </a>
        </div>
    </form>
@endsection
