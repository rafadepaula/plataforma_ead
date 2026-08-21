@props([
    'headers' => [],
    'hoverable' => false,
    'striped' => false,
])

@php
    $tableClasses = collect([
        'table',
        $hoverable ? 'table-hover' : null,
        $striped ? 'table-striped' : null,
    ])->filter()->implode(' ');
@endphp

<div class="table-responsive">
    <table {{ $attributes->merge(['class' => $tableClasses]) }}>
        @if(count($headers) > 0 || isset($header))
            <thead>
                @if(isset($header))
                    {{ $header }}
                @else
                    <tr>
                        @foreach($headers as $h)
                            <th scope="col">
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
