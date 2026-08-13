@props([
    'kicker' => null,
    'title' => null,
    'meta' => null,
    'elevation' => 'none',
    'border' => true,
    'shadow' => false,
])

@php
    $elevationClass = match($elevation) {
        'sm' => 'shadow-sm',
        'md' => 'shadow',
        'lg' => 'shadow-lg',
        default => '',
    };

    $borderClass = $border ? '' : 'border-0';
@endphp

<div {{ $attributes->merge(['class' => "card bg-body-secondary {$elevationClass} {$borderClass}"]) }}>
    @if(isset($image))
        <div class="grayscale w-100 overflow-hidden">
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
            <div class="card-meta mt-auto pt-3 small text-body-secondary border-top border-secondary">
                {{ $meta ?? $metaSlot }}
            </div>
        @endif
    </div>

    @if(isset($footer))
        <div class="card-footer p-3 bg-body-tertiary border-top border-secondary">
            {{ $footer }}
        </div>
    @endif
</div>
