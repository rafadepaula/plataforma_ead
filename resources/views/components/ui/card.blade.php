@props([
    'kicker' => null,
    'title' => null,
    'meta' => null,
    'elevation' => 'none',
    'border' => true,
    'shadow' => false,
    'surface' => 'secondary',
])

@php
    $elevationClass = match($elevation) {
        'sm' => 'shadow-sm',
        'md' => 'shadow',
        'lg' => 'shadow-lg',
        default => '',
    };

    $borderClass = $border ? '' : 'border-0';

    $surfaceClass = match($surface) {
        'white' => 'ds-surface',
        'body' => 'bg-body',
        default => 'bg-body-secondary',
    };
@endphp

<div {{ $attributes->merge(['class' => "card {$surfaceClass} {$elevationClass} {$borderClass}"]) }}>
    @if(isset($image))
        <div class="ds-pastel-wash">
            {{ $image }}
        </div>
    @endif

    <div class="card-body p-4 d-flex flex-column gap-2">
        @if($kicker || isset($kickerSlot))
            {{-- Sem cor explícita: o kicker do card herda a cor do corpo, como antes. --}}
            <div class="kicker">
                {{ $kicker ?? $kickerSlot }}
            </div>
        @endif

        @if($title || isset($titleSlot))
            <h3 class="card-title mb-0">
                {{ $title ?? $titleSlot }}
            </h3>
        @endif

        <div class="card-content small text-body">
            {{ $slot }}
        </div>

        @if($meta || isset($metaSlot))
            <div class="card-meta mt-auto pt-3 small text-body-secondary border-top">
                {{ $meta ?? $metaSlot }}
            </div>
        @endif
    </div>

    @if(isset($footer))
        <div class="card-footer p-3 border-top">
            {{ $footer }}
        </div>
    @endif
</div>
