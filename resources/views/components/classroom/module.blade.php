@props([
    'module',
    'completedCount' => 0,
    'totalCount' => null,
    'position' => null,
    'index' => null,
])

@php
    $total = $totalCount ?? ($module->lessons ? $module->lessons->count() : 0);
    $pos = $position ?? ($index ?? ($module->position ?? ($module->order_index ?? null)));
@endphp

<div {{ $attributes->merge(['class' => 'card ds-classroom-module mb-4']) }} dusk="module-{{ $module->id }}">
    <div class="ds-classroom-module-header">
        @if($pos !== null)
            <div class="ds-overline text-primary mb-1">Módulo {{ $pos }}</div>
        @else
            <div class="ds-overline text-primary mb-1">Módulo</div>
        @endif
        <h2 class="ds-classroom-module-title">{{ $module->title }}</h2>
        <div class="ds-caption text-secondary mt-1">
            @if($total === 0)
                Nenhuma lição publicada neste módulo.
            @elseif($completedCount === 0)
                Nenhuma aula concluída
            @else
                {{ $completedCount }} de {{ $total }} {{ $total === 1 ? 'aula concluída' : 'aulas concluídas' }}
            @endif
        </div>
    </div>

    @if($total > 0 || !empty((string) $slot))
        <ul class="ds-classroom-module-list">
            {{ $slot }}
        </ul>
    @else
        <div class="p-4 text-center text-body-secondary">
            Nenhuma lição publicada neste módulo.
        </div>
    @endif
</div>
