{{--
    x-ui.progress — wrapper do componente Progress do Bootstrap 5.3.
    https://getbootstrap.com/docs/5.3/components/progress/

    ⚠️ EXCEÇÃO AUTORIZADA A `style=` ⚠️
    O `style="width: {{ $pct }}%"` da `.progress-bar` abaixo é a ÚNICA exceção
    aprovada à regra "zero inline style" do projeto: a largura é um valor de
    runtime arbitrário (0–100) e o Bootstrap não expõe utility para percentual
    arbitrário. NÃO remova esse `style` em varreduras futuras de inline styles —
    remover quebra a barra. Nenhum outro `style=` é permitido neste arquivo.

    Props:
      - value     int    (0)        valor atual; é clampado para 0–100 após normalizar por `max`.
      - max       int    (100)      denominador do percentual.
      - label     string (null)     texto acessível (aria-label). Default: "Progresso".
      - variant   string ('primary') primary|success|danger|warning|neutral.
                                     Nunca vermelho/laranja/amarelo: `success` usa a
                                     rampa menta (par --success-container/
                                     --on-success-container, via `.bg-success-subtle`/
                                     `.text-success-emphasis`), `danger` usa o par
                                     --critical-container/--on-critical-container
                                     (`.bg-danger-subtle`/`.text-danger-emphasis`) e
                                     `warning` usa --attention-container/
                                     --on-attention-container (`.bg-warning-subtle`/
                                     `.text-warning-emphasis`). Essas utilities do
                                     Bootstrap já nascem tematizadas: `$success`/
                                     `$danger`/`$warning` foram remapeados em
                                     resources/scss/_bridge.scss, então nenhuma classe
                                     nova foi inventada aqui.
      - height    int    (null)     altura em px. Só aceita as chaves já geradas pela
                                     Utility API em resources/scss/_utilities.scss →
                                     "height-px": 4, 6, 8, 16, 24, 32, 36, 60. Outro valor
                                     é IGNORADO (não inventamos CSS nem inline style).
      - striped   bool   (false)    aplica .progress-bar-striped.
      - animated  bool   (false)    aplica .progress-bar-animated (implica striped).
      - showLabel bool   (false)    renderiza "N%" dentro da barra.

    Hook para JS (CsvImporter.js / QuizTimer.js):
      A `.progress-bar` interna sempre carrega `data-progress-bar`.
      Use `root.querySelector('[data-progress-bar]')` e escreva em `style.width`,
      mantendo `aria-valuenow` do wrapper `.progress` em sincronia.
      Se o chamador passar `dusk="x"` no componente, a barra interna recebe
      automaticamente `dusk="x-bar"`.
--}}

@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
    'variant' => 'primary',
    'height' => null,
    'striped' => false,
    'animated' => false,
    'showLabel' => false,
])

@php
    $maxValue = (float) $max > 0 ? (float) $max : 100;
    $pct = max(0, min(100, (int) round(((float) $value / $maxValue) * 100)));

    $variantClass = match ($variant) {
        'success' => 'bg-success-subtle text-success-emphasis',
        'danger' => 'bg-danger-subtle text-danger-emphasis',
        'warning' => 'bg-warning-subtle text-warning-emphasis',
        'neutral' => 'bg-secondary',
        default => 'bg-primary',
    };

    // Somente as chaves realmente geradas pela Utility API do projeto.
    $allowedHeights = [4, 6, 8, 16, 24, 32, 36, 60];
    $heightClass = in_array((int) $height, $allowedHeights, true) ? 'h-'.(int) $height : null;

    $wrapperClasses = collect([
        'progress',
        'bg-body-tertiary',
        $heightClass,
    ])->filter()->implode(' ');

    $barClasses = collect([
        'progress-bar',
        $variantClass,
        ($striped || $animated) ? 'progress-bar-striped' : null,
        $animated ? 'progress-bar-animated' : null,
    ])->filter()->implode(' ');

    $accessibleLabel = $label ?? 'Progresso';
    $barDusk = $attributes->get('dusk') ? $attributes->get('dusk').'-bar' : null;
@endphp

<div
    {{ $attributes->merge(['class' => $wrapperClasses]) }}
    role="progressbar"
    aria-label="{{ $accessibleLabel }}"
    aria-valuenow="{{ $pct }}"
    aria-valuemin="0"
    aria-valuemax="100"
>
    <div
        class="{{ $barClasses }}"
        data-progress-bar
        @if($barDusk) dusk="{{ $barDusk }}" @endif
        style="width: {{ $pct }}%"
    >
        @if($showLabel)
            {{ $pct }}%
        @endif
    </div>
</div>
