<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Plataforma EAD') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,400;6..12,500;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-body text-body m-0 p-0 min-vh-100 antialiased">
    <div class="row g-0 mx-0 min-vh-100 w-100">
        {{-- Left Institutional Panel (Desktop) --}}
        <x-layout.guest-panel :tenant-name="$tenantName ?? null" class="col-lg-5" />

        {{-- Right Form Area --}}
        <div class="col-12 col-lg-7 min-vh-100 d-flex flex-column justify-content-center align-items-center position-relative p-4 p-md-5">
            <div class="position-absolute top-0 end-0 p-4">
                <x-help-button :key="Route::currentRouteName() ?? 'login'" />
            </div>

            <div class="guest-form">
                {{-- Abaixo de `lg` o painel institucional não é renderizado: a
                     marca do tenant sobe para o topo da coluna de formulário. --}}
                <x-layout.guest-panel brand-only :tenant-name="$tenantName ?? null" class="d-lg-none mb-4" />

                <x-layout.alerts />
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
