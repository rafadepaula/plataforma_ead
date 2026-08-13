@props([
    'headers' => [],
    'striped' => false,
    'hover' => true,
    'responsive' => true,
    'size' => 'sm',
])

@php
    $tableClasses = collect([
        'table',
        $size === 'sm' ? 'table-sm' : null,
        $hover ? 'table-hover' : null,
        $striped ? 'table-striped' : null,
        'align-middle mb-0',
    ])->filter()->implode(' ');

    $wrapperClasses = collect([
        $responsive ? 'table-responsive' : null,
        'border bg-body-tertiary',
    ])->filter()->implode(' ');
@endphp

<div class="{{ $wrapperClasses }}">
    <table {{ $attributes->merge(['class' => $tableClasses]) }}>
        @if (isset($header) || count($headers) > 0)
            <thead class="table-header">
                @isset($header)
                    {{ $header }}
                @else
                    <tr>
                        @foreach ($headers as $h)
                            <th scope="col" class="small text-body-secondary fw-bold text-uppercase">
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
