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
    <div class="row g-0 mx-0 min-vh-100 w-100">
        {{-- Left Institutional Panel (Desktop). Coluna `col-lg-5` é a fração
             de grade mais próxima dos 46% pedidos pelo design (ver
             `components/layout/guest-panel.blade.php`). --}}
        <x-layout.guest-panel class="col-lg-5" />

        {{-- Right Form Area --}}
        <div class="col-12 col-lg-7 d-flex flex-column justify-content-center align-items-center position-relative py-5 px-4">
            <div class="position-absolute top-0 end-0">
                <x-help-button :key="Route::currentRouteName() ?? 'unknown'" />
            </div>

            {{-- Coluna de conteúdo de 440px (`--form-max`), largura fixa via
                 `.guest-form` (`resources/scss/components/_guest-panel.scss`). --}}
            <div class="guest-form">
                <x-layout.alerts />
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
