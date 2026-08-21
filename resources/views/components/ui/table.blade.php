{{--
    Mobile reflow contract: below `md`, `.ds-table` becomes a stacked card
    list purely via CSS (`resources/scss/components/_table-cards.scss`) —
    no duplicate markup, no second `<x-ui.table>`. Every `<td>` in a row
    passed into the slot MUST carry `data-label="Coluna"` matching its
    `<th>` header text; the CSS reads it via `content: attr(data-label)`
    to render the rótulo above the value on narrow screens. A `<td>` that
    only holds actions (buttons/badges with no bare text) still needs
    `data-label` so the card list doesn't show a blank line.
--}}
@props([
    'headers' => [],
    'hoverable' => true,
    'striped' => false,
])

@php
    $tableClasses = collect([
        'table',
        'table-sm',
        'ds-table',
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
                            <th scope="col" class="ds-overline">
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
