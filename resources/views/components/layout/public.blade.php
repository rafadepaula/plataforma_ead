{{--
    Shell HTML compartilhado pelas telas públicas standalone — aquelas que NÃO
    usam `layouts.app` (exige sessão autenticada + topbar/sidebar) nem
    `layouts.guest` (painel esquerdo temático de login):

      - `resources/views/landing/show.blade.php`            (`landing.show`)
      - `resources/views/public/certificates/show.blade.php` (`certificates.verify`)

    Emite `<!doctype html>`, `<meta name="csrf-token">`, o `@vite` do projeto e
    `<body class="bg-body text-body ...">`.

    É `layout/` (e não `ui/`) porque é peça estrutural singular do chrome da
    página — mesmo namespace de `topbar`, `sidebar`, `footer` e `alerts`.

    Props:
      - `title`     (obrigatória) conteúdo do `<title>`, já montado pela tela.
      - `container` (default true) embrulha o slot em `.container.py-5`.
                    Telas com seções full-bleed (a landing) passam `:container="false"`
                    e declaram os próprios `.container` por seção.

    Slots opcionais:
      - `head`   extras dentro do `<head>` (meta OG, canonical…).
      - `footer` conteúdo do rodapé; ausente, emite o rodapé padrão do projeto.
--}}
@props([
    'title',
    'container' => true,
])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    @isset($head)
        {{ $head }}
    @endisset

    @stack('styles')
</head>
<body {{ $attributes->merge(['class' => 'antialiased bg-body text-body min-vh-100 d-flex flex-column']) }}>
    <main class="flex-fill">
        @if ($container)
            <div class="container py-5">
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </main>

    @isset($footer)
        <footer class="border-top py-4 text-center small text-body-secondary">
            {{ $footer }}
        </footer>
    @else
        <footer class="border-top py-4 text-center small text-body-secondary">
            &copy; {{ date('Y') }} {{ config('app.name', 'Plataforma EAD') }}. Todos os direitos reservados.
        </footer>
    @endisset

    @stack('scripts')
</body>
</html>
