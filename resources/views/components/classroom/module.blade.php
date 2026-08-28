@props([
    'module',
    'completedCount' => 0,
    'totalCount' => 0,
    'index' => null,
])

@php
    $total = (int) $totalCount;
    $completed = (int) $completedCount;
@endphp

<div {{ $attributes->merge(['class' => 'card ds-classroom-module']) }} dusk="module-{{ $module->id }}">
    <div class="ds-classroom-module-header">
        <div class="ds-overline text-primary mb-1">Módulo{{ $index !== null ? ' '.$index : '' }}</div>
        <h2 class="ds-classroom-module-title">{{ $module->title }}</h2>
        @if($total > 0)
            <div class="ds-caption text-secondary mt-1">
                @if($completed === 0)
                    Nenhuma aula concluída
                @else
                    {{ $completed }} de {{ $total }} {{ $total === 1 ? 'aula concluída' : 'aulas concluídas' }}
                @endif
            </div>
        @endif
    </div>

    @if($total > 0)
        <ul class="ds-classroom-module-list">
            {{ $slot }}
        </ul>
    @else
        <div class="p-4 text-center text-body-secondary">
            Nenhuma lição publicada neste módulo.
        </div>
    @endif
</div>
