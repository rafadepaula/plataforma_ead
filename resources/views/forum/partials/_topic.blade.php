@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\ForumTopic $topic */
    /** @var bool $canPin */

    $repliesCount = $topic->replies_count ?? 0;
    $repliesText = $repliesCount > 0
        ? $repliesCount . ' ' . ($repliesCount === 1 ? 'resposta' : 'respostas')
        : 'Nenhuma resposta ainda';
@endphp

<div
    class="ds-card ds-card-interactive p-3 p-md-4 d-flex align-items-start gap-3 position-relative"
    dusk="topic-row-{{ $topic->id }}"
>
    {{-- Left: User Avatar --}}
    <div class="flex-shrink-0">
        <x-ui.avatar :initials="$topic->user->initials" size="lg" />
    </div>

    {{-- Center: Topic details --}}
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
            @if($topic->is_pinned)
                {{-- Chip de status: `static` renderiza um `<span>`, não um
                     `<button>` — dentro de um card cujo título é um
                     `stretched-link`, um botão que não submete nada só
                     poluiria a ordem de foco do teclado. --}}
                <x-ui.chip :static="true" variant="info" dusk="pinned-badge-{{ $topic->id }}">
                    Fixado
                </x-ui.chip>
            @endif

            <a
                href="{{ route('forum.show', [$course, $topic]) }}"
                class="text-decoration-none text-body stretched-link"
                dusk="open-topic-{{ $topic->id }}"
            >
                <h4 class="forum-topic-title mb-0 text-wrap">
                    {{ $topic->title }}
                </h4>
            </a>
        </div>

        <p class="text-body-secondary small mb-2 line-clamp-2 text-break">
            {{ Str::limit($topic->content, 180) }}
        </p>

        <div class="d-flex align-items-center gap-3 text-body-secondary small">
            <span>{{ $topic->user->name }} — {{ $topic->created_at->format('d/m/Y H:i') }}</span>
            <span class="d-inline-flex align-items-center gap-1">
                <x-ui.icon name="message-square" size="16" />
                <span>{{ $repliesText }}</span>
            </span>
        </div>
    </div>

    {{-- Right: Pin toggle form (Gestor / Admin only) - DOM SIBLING of topic link.
         O wrapper `position-relative z-2` é o que mantém o botão clicável por
         cima do `stretched-link` do título (que cobre o card inteiro) — não
         remover sem trocar a âncora esticada por um link comum. --}}
    @if($canPin)
        <div class="flex-shrink-0 position-relative z-2">
            <form
                method="POST"
                action="{{ route('forum.pin', [$course, $topic]) }}"
                dusk="pin-form-{{ $topic->id }}"
            >
                @csrf
                <x-ui.button
                    type="submit"
                    variant="ghost"
                    size="sm"
                    :icon="$topic->is_pinned ? 'pin-off' : 'pin'"
                    dusk="pin-topic-{{ $topic->id }}"
                    title="{{ $topic->is_pinned ? 'Desafixar tópico' : 'Fixar tópico no topo' }}"
                />
            </form>
        </div>
    @endif
</div>
