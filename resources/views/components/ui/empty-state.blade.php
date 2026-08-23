@props([
    'colspan' => null,
    'icon' => null,
    'tone' => null, // null|success — 'success' tinges the icon circle mint (ds-tone-success) for a "good news" empty state (e.g. a cleared queue) instead of the default primary tint.
    'title' => null,
    'message' => null,
    'description' => null,
])

@php
    $hasSlot = trim($slot) !== '';
    $headline = $title ?? $message;
    $sub = $description ?? (($title !== null && $message !== null) ? $message : null);
    $iconToneClass = $tone === 'success' ? 'ds-empty-state-icon--success' : null;
@endphp

@if ($colspan)
    <tr {{ $attributes->merge(['class' => 'text-center text-body-secondary']) }}>
        <td colspan="{{ $colspan }}" class="py-4">
            @if ($icon)
                <div class="mb-2">
                    <span @class(['ds-empty-state-icon', $iconToneClass])>
                        <x-ui.icon :name="$icon" size="32" />
                    </span>
                </div>
            @endif

            @if ($hasSlot)
                <div>{{ $slot }}</div>
            @elseif ($headline)
                <p class="fw-semibold mb-0">{{ $headline }}</p>
            @endif

            @if ($sub)
                <p class="small mb-0 mt-1">{{ $sub }}</p>
            @endif

            @isset($action)
                <div class="mt-3">{{ $action }}</div>
            @endisset
        </td>
    </tr>
@else
    <div {{ $attributes->merge(['class' => 'ds-empty-state text-center text-body-secondary p-5']) }}>
        @if ($icon)
            <div class="mb-2">
                <span @class(['ds-empty-state-icon', $iconToneClass])>
                    <x-ui.icon :name="$icon" size="32" />
                </span>
            </div>
        @endif

        @if ($hasSlot)
            <div>{{ $slot }}</div>
        @elseif ($headline)
            <p class="fw-semibold mb-0">{{ $headline }}</p>
        @endif

        @if ($sub)
            <p class="small mb-0 mt-1">{{ $sub }}</p>
        @endif

        @isset($action)
            <div class="mt-3">{{ $action }}</div>
        @endisset
    </div>
@endif
