@props([
    'kicker' => 'Métrica',
    'value' => '0',
    'delta' => null,
    'deltaVariant' => 'accent',
])

<div {{ $attributes->merge(['class' => 'card elev-sm', 'style' => 'border-radius: 0px; background: var(--color-surface); border: 1px solid var(--color-divider); padding: 16px; display: flex; flex-direction: column; gap: 6px; box-shadow: var(--shadow-sm); text-align: left;']) }}>
    <div class="card-kicker" style="font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-neutral-600); font-weight: 700;">
        {{ $kicker }}
    </div>

    <div style="font-family: var(--font-heading); font-weight: 800; font-size: 30px; color: var(--color-text); line-height: 1.1; letter-spacing: -0.02em;">
        {{ $value }}
    </div>

    @if($delta)
        <div class="card-meta" style="margin-top: 4px; display: flex; align-items: center; gap: 6px;">
            <x-ui.badge :variant="$deltaVariant">
                {{ $delta }}
            </x-ui.badge>
        </div>
    @endif
</div>
