@props([
    'kicker' => 'Métrica',
    'value' => '0',
    'delta' => null,
    'deltaVariant' => 'accent',
    'caption' => null,
    'icon' => null,
    'tone' => 'primary',
    'noData' => false,
])

@php
    $iconToneClass = match ($tone) {
        'secondary' => 'stat-card-icon-secondary',
        'tertiary' => 'stat-card-icon-tertiary',
        'neutral' => 'stat-card-icon-neutral',
        default => 'stat-card-icon-primary',
    };

    $displayValue = $noData ? '—' : $value;
    $isUnavailable = $noData || trim((string) $displayValue) === '—';
    $displayCaption = $noData ? 'Sem dados no período' : $caption;
@endphp

<div {{ $attributes->merge(['class' => 'card stat-card text-start']) }}>
    @if ($icon)
        <div class="stat-card-icon {{ $iconToneClass }}">
            <x-ui.icon :name="$icon" size="24" />
        </div>
    @endif

    <div class="ds-overline text-body-secondary">
        {{ $kicker }}
    </div>

    <div @class(['stat-card-value', 'stat-card-value-disabled' => $isUnavailable])>
        {{ $displayValue }}
    </div>

    @if ($delta !== null || filled($displayCaption))
        <div class="stat-card-foot">
            @if ($delta !== null)
                <x-ui.badge :variant="$deltaVariant" :dot="false">
                    {{ $delta }}
                </x-ui.badge>
            @endif

            @if (filled($displayCaption))
                <span class="ds-caption">{{ $displayCaption }}</span>
            @endif
        </div>
    @endif
</div>
