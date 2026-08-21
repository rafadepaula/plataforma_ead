{{--
    Painel institucional do shell público (`layouts/guest.blade.php`): marca +
    frase de propósito, reutilizado por login, recuperação de senha,
    redefinição de senha e aceite de convite. Extraído do markup que antes
    vivia embutido em `guest.blade.php` para ficar testável isoladamente.

    Fundo: `.bg-primary-subtle` (utilitário real do Bootstrap, alimentado por
    `$primary` em `resources/scss/_bridge.scss`, que por sua vez copia
    `--blue-600`/`--blue-100` dos tokens do design system) — nenhuma cor
    literal aqui. Largura: o Bootstrap não publica uma fração de grade de
    46%, então quem instancia o componente decide a coluna (`col-lg-5` em
    `layouts/guest.blade.php`, a fração mais próxima); o componente cuida
    apenas de exibição (`d-none d-lg-flex`) e conteúdo.
--}}
@props([
    'title' => 'Acesse a plataforma',
])

@php
    $brandName = session('tenant_name') ?? config('app.name', 'Conselho EAD');

    $initials = collect(preg_split('/\s+/', trim((string) $brandName)))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr((string) $brandName, 0, 2));
    }
@endphp

<div {{ $attributes->class(['guest-panel', 'd-none', 'd-lg-flex', 'flex-column', 'bg-primary-subtle', 'text-body', 'p-5']) }}>
    <div class="d-flex align-items-center gap-3">
        <span class="brand-mark" aria-hidden="true">{{ $initials }}</span>
        <span class="fw-bold fs-5 lh-1">{{ $brandName }}</span>
    </div>

    <div class="my-auto">
        <hr class="border-primary border-2 opacity-100 w-25 mb-4">
        <h1 class="fw-bolder mb-4 lh-base">
            {{ $title }}
        </h1>
        <p class="ds-lead mb-0">
            {{ $slot->isNotEmpty() ? $slot : 'Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.' }}
        </p>
    </div>

    <div class="ds-caption">
        © {{ date('Y') }} {{ config('app.name', 'Plataforma EAD') }}. Todos os direitos reservados.
    </div>
</div>
