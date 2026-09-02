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

    `dense` é opt-in para telas que precisam de mais aproveitamento
    vertical (ex.: matrículas de curso): enxuga o padding superior/inferior
    do `card-body` sem tocar no default, que é o padrão visual das demais
    telas (cursos, audit-logs).

    Visual: `.card` do Bootstrap já é outlined por padrão (borda `--border-color`,
    sem sombra) com raio 20px vindo da ponte (`$card-border-radius`) — não
    precisa de classe extra nem de um segundo contorno dentro do card-body.
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
    'dense' => false,
])

@php
    $formMethod = strtoupper($method);
    $isSpoofedMethod = ! in_array($formMethod, ['GET', 'POST'], true);
    $htmlMethod = $isSpoofedMethod ? 'POST' : $formMethod;
@endphp

<div class="card mb-3">
    <div class="card-body {{ $dense ? 'py-2' : '' }}">
        <form method="{{ $htmlMethod }}"
              action="{{ $action }}"
              role="search"
              aria-label="{{ $label }}"
              {{ $attributes->merge(['class' => 'ds-filter-form row g-2 align-items-end']) }}>
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
                <div class="col-12 col-md-auto d-flex gap-2">
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
