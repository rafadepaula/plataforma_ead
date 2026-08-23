{{--
    Wrapper de `bootstrap.Modal` — raio 28px, `--elev-5`/scrim 42% e a
    transição de entrada (sobe + escala) vêm de `_bridge.scss`
    (`$modal-content-border-radius`, `$modal-backdrop-opacity`,
    `$transition-base`) e do próprio `.modal.fade` do Bootstrap; nenhum JS
    artesanal aqui, só `data-bs-*` declarativo.
--}}
@props([
    'id' => null,
    'name' => null,
    'title' => null,
    'dismissable' => true,
    'size' => 'md',
    'static' => false,
])

@php
    $modalId = $id ?? $name ?? 'modal-'.uniqid();
    $sizeClass = match($size) {
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'modal fade', 'dusk' => 'modal-'.$modalId]) }}
     id="{{ $modalId }}"
     tabindex="-1"
     aria-labelledby="{{ $modalId }}-label"
     aria-hidden="true"
     @if ($static) data-bs-backdrop="static" data-bs-keyboard="false" @endif>
    <div class="modal-dialog modal-dialog-centered {{ $sizeClass }}">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $modalId }}-label">{{ $title ?? 'Confirmação' }}</h2>
                @if ($dismissable)
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Fechar"
                            dusk="modal-{{ $modalId }}-close"></button>
                @endif
            </div>

            <div class="modal-body">
                {{ $slot }}
            </div>

            @isset($actions)
                <div class="modal-footer">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
