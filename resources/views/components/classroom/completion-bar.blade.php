@props([
    'lesson',
    'isCompleted' => false,
    'manual' => true,
    'tracksProgress' => true,
])

@php
    $completed = (bool) $isCompleted;
    /**
     * Vídeo conclui sozinho a 90% assistidos: nesse formato não existe botão manual.
     * Quem apenas visualiza a aula (Admin/Gestor sem matrícula) também não vê o botão:
     * o endpoint de conclusão recusa a escrita para quem não é aluno matriculado.
     */
    $showsManualButton = (bool) $manual && (bool) $tracksProgress;
@endphp

{{--
    A visibilidade do selo e do botão é expressa SOMENTE por `.d-none`.
    O atributo HTML `hidden` é proibido aqui: o reboot do Bootstrap aplica
    `[hidden] { display: none !important; }` e anularia a troca de classes
    feita por `LessonPlayer.js`.
--}}
<div {{ $attributes->merge(['class' => 'd-flex align-items-center gap-3 flex-wrap']) }}>
    <x-ui.badge variant="accent" data-completion-badge data-lesson-id="{{ $lesson->id }}" dusk="lesson-completed-badge" @class(['d-none' => ! $completed])>
        Concluída
    </x-ui.badge>

    @if($showsManualButton)
        <x-ui.button
            data-lesson-id="{{ $lesson->id }}"
            data-mark-complete-url="{{ route('lessons.complete', $lesson) }}"
            @class(['d-none' => $completed])
            dusk="mark-complete-button"
        >
            Marcar como concluída
        </x-ui.button>
    @endif

    {{ $slot }}
</div>
