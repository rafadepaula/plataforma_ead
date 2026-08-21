{{--
    Mobile reflow contract: below `md`, `.ds-table` becomes a stacked card
    list purely via CSS (`resources/scss/components/_table-cards.scss`) —
    no duplicate markup, no second `<x-ui.data-table>`. Every `<td>` in a
    row passed into the slot MUST carry `data-label="Coluna"` matching its
    `<th>` header text; the CSS reads it via `content: attr(data-label)`
    to render the rótulo above the value on narrow screens. A `<td>` that
    only holds actions (buttons/badges with no bare text) still needs
    `data-label` so the card list doesn't show a blank line.
--}}
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
        'ds-table',
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
                            <th scope="col" class="ds-overline">
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
