@props([
    'title' => 'Acesse a plataforma',
])

@php
    $brandName = session('tenant_name', config('app.name', 'Plataforma EAD'));

    $initials = collect(preg_split('/\s+/', trim((string) $brandName)))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr((string) $brandName, 0, 2));
    }
@endphp

<div {{ $attributes->class(['guest-panel', 'd-none', 'd-lg-flex', 'flex-column', 'justify-content-between', 'bg-primary-subtle', 'text-body', 'p-5', 'min-vh-100']) }}>
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
            {{ $slot->isNotEmpty() ? $slot : 'Capacitação técnica continuada, avaliações interativas e emissão oficial de certificados.' }}
        </p>
    </div>

    <div class="ds-caption text-body-secondary">
        &copy; {{ date('Y') }} {{ config('app.name', 'Plataforma EAD') }}
    </div>
</div>
