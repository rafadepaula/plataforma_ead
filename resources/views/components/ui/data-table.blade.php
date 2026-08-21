@props([
    'headers' => [],
    'striped' => false,
    'hover' => false,
    'responsive' => true,
    'size' => null,
])

@php
    $tableClasses = collect([
        'table',
        $size === 'sm' ? 'table-sm' : null,
        $hover ? 'table-hover' : null,
        $striped ? 'table-striped' : null,
    ])->filter()->implode(' ');

    $wrapperClasses = $responsive ? 'table-responsive' : '';
@endphp

<div class="{{ $wrapperClasses }}">
    <table {{ $attributes->merge(['class' => $tableClasses]) }}>
        @if (isset($header) || count($headers) > 0)
            <thead>
                @isset($header)
                    {{ $header }}
                @else
                    <tr>
                        @foreach ($headers as $h)
                            <th scope="col">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                @endisset
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>

        @isset($footer)
            <tfoot>
                {{ $footer }}
            </tfoot>
        @endisset
    </table>
</div>
