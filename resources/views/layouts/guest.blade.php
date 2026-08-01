<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Plataforma EAD') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="antialiased" style="background: var(--color-bg); color: var(--color-text); font-family: var(--font-body); margin: 0; padding: 0; min-height: 100vh;">
    <div style="display: flex; min-height: 100vh; width: 100%;">
        {{-- Left Institutional Panel (Desktop) --}}
        <div class="d-none d-lg-flex" 
             style="width: 42%; flex: none; background: var(--color-neutral-900); color: var(--color-neutral-100); flex-direction: column; padding: 56px; border-radius: 0px;">
            <span style="font-family: var(--font-heading); font-weight: 800; font-size: 20px;">
                {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
            </span>

            <div style="margin-top: auto; margin-bottom: auto;">
                <div style="width: 56px; height: 2px; background: var(--color-accent); margin-bottom: 24px;"></div>
                <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 36px; line-height: 1.1; max-width: 10ch; margin-bottom: 16px; color: var(--color-neutral-100);">
                    Acesse a plataforma
                </h1>
                <p style="font-size: 15px; color: var(--color-neutral-400); max-width: 32ch; line-height: 1.5;">
                    Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.
                </p>
            </div>

            <div style="font-size: 12px; color: var(--color-neutral-600);">
                © {{ date('Y') }} Plataforma EAD. Todos os direitos reservados.
            </div>
        </div>

        {{-- Right Form Area --}}
        <div style="flex: 1; background: var(--color-bg); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 32px 24px; border-radius: 0px;">
            <div style="width: 380px; max-width: 100%;">
                <x-layout.alerts />
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
