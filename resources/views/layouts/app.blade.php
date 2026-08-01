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
<body class="antialiased" style="background: var(--color-bg); color: var(--color-text); font-family: var(--font-body); margin: 0; padding: 0; min-height: 100vh;" x-data="{ sidebarOpen: false }">
    <div class="app-wrapper" style="display: flex; flex-direction: column; min-height: 100vh;">
        <x-layout.topbar />

        <div class="app-body" style="display: flex; flex: 1; position: relative;">
            <x-layout.sidebar />

            <main class="app-main" style="flex: 1; padding: 24px; min-width: 0; background: var(--color-bg);">
                <x-layout.alerts />
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>

        <x-layout.footer />
    </div>

    @stack('scripts')
</body>
</html>
