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
        'sm' => 'elev-sm',
        'md' => 'elev-md',
        'lg' => 'elev-lg',
        default => '',
    };

    $cardStyle = 'border-radius: 0px; background: var(--color-surface); text-align: left; display: flex; flex-direction: column; overflow: hidden;';
    if ($border) {
        $cardStyle .= ' border: 1px solid var(--color-divider);';
    } else {
        $cardStyle .= ' border: none;';
    }
    if (!$shadow && $elevation === 'none') {
        $cardStyle .= ' box-shadow: none;';
    }
@endphp

<div {{ $attributes->merge(['class' => "card {$elevationClass}", 'style' => $cardStyle]) }}>
    @if(isset($image))
        <div class="card-image-slot grayscale" style="width: 100%; overflow: hidden; border-radius: 0px;">
            {{ $image }}
        </div>
    @endif

    <div class="card-body" style="padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 8px;">
        @if($kicker || isset($kickerSlot))
            <div class="card-kicker" style="font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-accent); font-weight: 700;">
                {{ $kicker ?? $kickerSlot }}
            </div>
        @endif

        @if($title || isset($titleSlot))
            <h3 class="card-title" style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; color: var(--color-text); margin: 0; line-height: 1.2;">
                {{ $title ?? $titleSlot }}
            </h3>
        @endif

        <div class="card-content" style="font-size: 14px; color: var(--color-text);">
            {{ $slot }}
        </div>

        @if($meta || isset($metaSlot))
            <div class="card-meta" style="margin-top: auto; padding-top: 12px; font-size: 12px; color: var(--color-neutral-600); border-top: 1px solid var(--color-divider);">
                {{ $meta ?? $metaSlot }}
            </div>
        @endif
    </div>

    @if(isset($footer))
        <div class="card-footer" style="padding: 12px 20px; background: color-mix(in srgb, var(--color-neutral-900) 4%, transparent); border-top: 1px solid var(--color-divider); border-radius: 0px;">
            {{ $footer }}
        </div>
    @endif
</div>
