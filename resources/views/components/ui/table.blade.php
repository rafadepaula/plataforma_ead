@props([
    'headers' => [],
    'hoverable' => true,
    'striped' => false,
])

<div class="table-responsive" style="width: 100%; overflow-x: auto; border: 1px solid var(--color-divider); border-radius: 0px; background: var(--color-surface);">
    <table {{ $attributes->merge(['class' => 'table', 'style' => 'width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; border-radius: 0px;']) }}>
        @if(count($headers) > 0 || isset($header))
            <thead style="background: color-mix(in srgb, var(--color-neutral-900) 6%, transparent); border-bottom: 1px solid var(--color-divider);">
                @if(isset($header))
                    {{ $header }}
                @else
                    <tr>
                        @foreach($headers as $h)
                            <th style="padding: 12px 16px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-neutral-700);">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                @endif
            </thead>
        @endif

        <tbody style="divide-y divide-color-divider;">
            {{ $slot }}
        </tbody>
    </table>
</div>
