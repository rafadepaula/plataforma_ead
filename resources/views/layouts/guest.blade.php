<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Plataforma EAD') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-body text-body m-0 p-0 min-vh-100 antialiased">
    <div class="d-flex min-vh-100 w-100">
        {{-- Left Institutional Panel (Desktop) --}}
        <div class="guest-panel d-none d-lg-flex flex-column flex-none bg-dark text-light">
            <span class="guest-logo">
                {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
            </span>

            <div class="my-auto">
                <div class="guest-accent-bar bg-primary mb-4"></div>
                <h1 class="guest-title fw-bolder mb-4 text-light lh-base">
                    Acesse a plataforma
                </h1>
                <p class="guest-text lh-base">
                    Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.
                </p>
            </div>

            <div class="guest-footer text-info">
                © {{ date('Y') }} Plataforma EAD. Todos os direitos reservados.
            </div>
        </div>

        {{-- Right Form Area --}}
        <div class="flex-fill bg-body d-flex flex-column justify-content-center align-items-center position-relative guest-form-area">
            <div class="position-absolute top-0 end-0 guest-help-pos">
                <x-help-button :key="Route::currentRouteName() ?? 'unknown'" />
            </div>

            <div class="w-100 guest-form-max-w">
                <x-layout.alerts />
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
