@props([
    'headers' => [],
    'striped' => false,
    'hover' => false,
    'hoverable' => false,
    'responsive' => true,
    'size' => null,
])

<x-ui.table :headers="$headers"
            :striped="$striped"
            :hover="$hover"
            :hoverable="$hoverable"
            :responsive="$responsive"
            :size="$size"
            {{ $attributes }}>
    @isset($toolbar)
        <x-slot:toolbar>{{ $toolbar }}</x-slot:toolbar>
    @endisset

    @isset($header)
        <x-slot:header>{{ $header }}</x-slot:header>
    @endisset

    {{ $slot }}

    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-ui.table>
