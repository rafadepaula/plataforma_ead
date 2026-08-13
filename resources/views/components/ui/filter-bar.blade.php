{{--
    Barra de filtros de tela de listagem: um `<form>` em grid Bootstrap dentro
    de um card, com os campos vindos do slot default (cada um em sua `.col-md-*`)
    e os botões "Filtrar"/"Limpar" ao final.

    Uso:
    <x-ui.filter-bar :action="route('admin.audit-logs.index')"
                     :reset-url="route('admin.audit-logs.index')"
                     dusk="audit-logs-filter-form">
        <div class="col-md-3">
            <label for="date_from" class="form-label">Data Inicial</label>
            <input type="date" id="date_from" name="date_from" class="form-control"
                   value="{{ request('date_from') }}" dusk="audit-logs-date-from">
        </div>
    </x-ui.filter-bar>

    Os atributos extras (inclusive `dusk=`) são mesclados no `<form>`, que é a
    raiz funcional do componente — o card é apenas o invólucro visual.
--}}
@props([
    'action',
    'method' => 'GET',
    'submitLabel' => 'Filtrar',
    'resetLabel' => 'Limpar',
    'resetUrl' => null,
    'label' => 'Filtros',
    'submitDusk' => 'filter-submit',
    'resetDusk' => 'filter-reset',
])

@php
    $formMethod = strtoupper($method);
    $isSpoofedMethod = ! in_array($formMethod, ['GET', 'POST'], true);
    $htmlMethod = $isSpoofedMethod ? 'POST' : $formMethod;
@endphp

<div class="card mb-3">
    <div class="card-body p-0">
        <form method="{{ $htmlMethod }}"
              action="{{ $action }}"
              role="search"
              aria-label="{{ $label }}"
              {{ $attributes->merge(['class' => 'row g-2 align-items-end p-3 bg-body-tertiary border']) }}>
            @if ($htmlMethod === 'POST')
                @csrf
            @endif

            @if ($isSpoofedMethod)
                @method($formMethod)
            @endif

            {{ $slot }}

            @isset($actions)
                {{ $actions }}
            @else
                {{-- `mb-3` casa com o wrapper fixo de `<x-ui.input>`/`<x-ui.select>`:
                     sem ele, `align-items-end` alinha os botões ~16px abaixo dos campos. --}}
                <div class="col-12 col-md-auto d-flex gap-2 mb-3">
                    <x-ui.button type="submit" dusk="{{ $submitDusk }}">{{ $submitLabel }}</x-ui.button>

                    @if ($resetUrl)
                        <x-ui.button variant="ghost" :href="$resetUrl" dusk="{{ $resetDusk }}">
                            {{ $resetLabel }}
                        </x-ui.button>
                    @endif
                </div>
            @endisset
        </form>
    </div>
</div>
