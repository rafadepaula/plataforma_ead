@props([
    'kicker' => 'Métrica',
    'value' => '0',
    'delta' => null,
    'deltaVariant' => 'accent',
])

<div {{ $attributes->merge(['class' => 'card shadow-sm bg-body-secondary p-4 d-flex flex-column gap-1 text-start']) }}>
    <div class="kicker text-body-secondary">
        {{ $kicker }}
    </div>

    <div class="stat-card-value text-body">
        {{ $value }}
    </div>

    @if($delta)
        <div class="mt-1 d-flex align-items-center gap-1">
            <x-ui.badge :variant="$deltaVariant">
                {{ $delta }}
            </x-ui.badge>
        </div>
    @endif
</div>
