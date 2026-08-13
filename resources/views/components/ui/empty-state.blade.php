@props([
    'colspan' => null,
    'icon' => null,
    'title' => null,
    'message' => null,
    'description' => null,
])

@php
    $hasSlot = trim($slot) !== '';
    $headline = $title ?? $message;
    $sub = $description ?? (($title !== null && $message !== null) ? $message : null);
@endphp

@if ($colspan)
    <tr {{ $attributes->merge(['class' => 'text-center text-body-secondary']) }}>
        <td colspan="{{ $colspan }}" class="py-4">
            @if ($icon)
                <div class="mb-2">
                    <x-ui.icon :name="$icon" size="32" class="opacity-50" />
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
    <div {{ $attributes->merge(['class' => 'border border-dashed text-center text-body-secondary p-5']) }}>
        @if ($icon)
            <div class="mb-2">
                <x-ui.icon :name="$icon" size="32" class="opacity-50" />
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
