{{--
    dompdf-safe print layout. dompdf only understands a restricted CSS
    subset (no CSS custom properties, no modern flexbox/grid), so this
    layout is intentionally isolated from the app's design system: it
    MUST NOT `@vite` app.scss, pull in Bootstrap, or reference any design
    token. Views extending it (currently only `certificates/pdf.blade.php`)
    keep their own literal-value `<style>` block and inline `style=`
    attributes — see `certificates-conventions` and the public-screens guideline for why this
    screen is a deliberate exception to the rest of the front-end
    migration.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    @yield('styles')
</head>
<body>
    @yield('content')
</body>
</html>
