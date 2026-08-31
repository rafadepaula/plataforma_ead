@extends('layouts.guest')

@section('content')
    {{-- `level="h2"`: o `h1` da página é o do painel institucional do shell. --}}
    <x-layout.page-header
        kicker="Acesso"
        title="Entrar na plataforma"
        level="h2"
    />

    <form method="POST" action="{{ route('login') }}" dusk="login-form">
        @csrf

        <x-ui.input
            type="email"
            name="email"
            label="E-mail"
            :value="old('email')"
            required
            autofocus
            dusk="login-email"
            class="mb-3"
        />

        <div class="password-field mb-3" data-password-toggle-field>
            <x-ui.input
                type="password"
                name="password"
                label="Senha"
                required
                dusk="login-password"
            />

            {{-- Dois ícones, um sempre escondido por CLASSE (`d-none`) — nunca
                 pelo atributo `hidden`, que venceria o JS. --}}
            <button type="button"
                    class="password-toggle-btn btn btn-ghost ds-state-layer"
                    data-password-toggle
                    data-password-toggle-btn
                    dusk="password-toggle"
                    aria-label="Mostrar senha">
                <x-ui.icon name="eye" size="18" data-icon-show data-password-toggle-icon="show" aria-hidden="true" />
                <x-ui.icon name="eye-off" size="18" class="d-none" data-icon-hide data-password-toggle-icon="hide" aria-hidden="true" />
            </button>
        </div>

        {{-- Caixa de seleção, não interruptor: é preferência de sessão. --}}
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" dusk="login-remember">
            <label class="form-check-label" for="remember">Lembrar-me</label>
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-100 mb-3" dusk="login-submit">
            Entrar
        </x-ui.button>

        {{-- Não existe "criar conta" nesta tela: conta nasce por convite. --}}
        <div class="text-center">
            <a href="{{ route('password.request') }}"
               class="text-primary fw-semibold text-decoration-none"
               dusk="forgot-password-link">
                Esqueceu sua senha?
            </a>
        </div>
    </form>
@endsection
