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
<body class="antialiased bg-body text-body m-0 p-0 min-vh-100">
    <div class="app-wrapper d-flex flex-column min-vh-100">
        <x-layout.topbar />

        <div class="app-body d-flex flex-1 position-relative">
            <x-layout.sidebar />

            <main class="app-main flex-1 min-w-0">
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
