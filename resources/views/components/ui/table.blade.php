@props([
    'headers' => [],
    'hoverable' => true,
    'striped' => false,
])

@php
    $tableClasses = collect([
        'table',
        'table-sm',
        $hoverable ? 'table-hover' : null,
        $striped ? 'table-striped' : null,
    ])->filter()->implode(' ');
@endphp

<div class="table-responsive border bg-body-tertiary">
    <table {{ $attributes->merge(['class' => $tableClasses]) }}>
        @if(count($headers) > 0 || isset($header))
            <thead class="table-header">
                @if(isset($header))
                    {{ $header }}
                @else
                    <tr>
                        @foreach($headers as $h)
                            <th scope="col" class="small text-body-secondary fw-bold text-uppercase">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                @endif
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
