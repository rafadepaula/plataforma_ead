@extends('layouts.guest')

@section('content')
    <div class="mb-4">
        <div class="ds-overline text-primary mb-1">Acesso</div>
        <h2 class="h3 fw-bold mb-0">Entrar na plataforma</h2>
    </div>

    <form method="POST" action="{{ route('login') }}" dusk="login-form">
        @csrf

        <x-ui.input
            type="email"
            name="email"
            label="E-mail *"
            :value="old('email')"
            required
            autofocus
            dusk="login-email"
            class="mb-3"
        />

        <div class="position-relative mb-3 password-field" data-password-toggle-field>
            <x-ui.input
                type="password"
                name="password"
                label="Senha *"
                required
                dusk="login-password"
            />

            <button type="button"
                    class="btn btn-sm btn-ghost ds-state-layer position-absolute end-0 top-50 translate-middle-y me-2"
                    data-password-toggle
                    data-password-toggle-btn
                    dusk="password-toggle"
                    aria-label="Mostrar senha">
                <x-ui.icon name="eye" size="18" data-icon-show data-password-toggle-icon="show" aria-hidden="true" />
                <x-ui.icon name="eye-off" size="18" class="d-none" data-icon-hide data-password-toggle-icon="hide" aria-hidden="true" />
            </button>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" dusk="login-remember">
            <label class="form-check-label" for="remember">Lembrar-me</label>
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-100 mb-3" dusk="login-submit">
            Entrar
        </x-ui.button>

        <div class="text-center">
            <a href="{{ route('password.request') }}"
               class="text-body-secondary text-decoration-none small"
               dusk="forgot-password-link">
                Esqueceu sua senha?
            </a>
        </div>
    </form>
@endsection
