@props([
    'headers' => [],
    'hover' => false,
    'hoverable' => false,
    'striped' => false,
    'responsive' => true,
    'size' => null,
])

@php
    $tableClasses = collect([
        'ds-table',
        $size === 'sm' ? 'ds-table-sm' : null,
        ($hover || $hoverable) ? 'ds-table-hover' : null,
        $striped ? 'ds-table-striped' : null,
    ])->filter()->implode(' ');

    $scrollClasses = collect([
        'ds-table-scroll',
        $responsive ? 'table-responsive' : null,
    ])->filter()->implode(' ');
@endphp

<div class="ds-table-wrap">
    @isset($toolbar)
        <div class="ds-table-toolbar">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="{{ $scrollClasses }}">
        <table {{ $attributes->merge(['class' => $tableClasses]) }}>
            @if (isset($header) || count($headers) > 0)
                <thead>
                    @isset($header)
                        {{ $header }}
                    @else
                        <tr>
                            @foreach ($headers as $heading)
                                <th scope="col">
                                    {{ $heading }}
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
</div>
