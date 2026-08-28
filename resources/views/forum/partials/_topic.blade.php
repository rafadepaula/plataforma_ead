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
    class="ds-card ds-card-interactive p-3 p-md-4 d-flex align-items-start gap-3 position-relative forum-topic-card"
    dusk="topic-row-{{ $topic->id }}"
>
    {{-- Left: User Avatar --}}
    <div class="flex-shrink-0">
        <x-ui.avatar :name="$topic->user->name" size="lg" />
    </div>

    {{-- Center: Topic details --}}
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
            @if($topic->is_pinned)
                <span class="badge bg-info-subtle text-info-emphasis rounded-pill" dusk="pinned-badge-{{ $topic->id }}">
                    Fixado
                </span>
            @endif

            <a
                href="{{ route('forum.show', [$course, $topic]) }}"
                class="text-decoration-none text-body stretched-link"
                dusk="open-topic-{{ $topic->id }}"
            >
                <h4 class="h6 fw-bold mb-0 text-truncate text-wrap">
                    {{ $topic->title }}
                </h4>
            </a>
        </div>

        <p class="text-body-secondary small mb-2 line-clamp-2 ds-line-clamp-2 text-break">
            {{ Str::limit($topic->content, 180) }}
        </p>

        <div class="d-flex align-items-center gap-3 text-body-secondary small">
            <span>{{ $topic->user->name }} — {{ $topic->created_at->format('d/m/Y H:i') }}</span>
            <span class="d-inline-flex align-items-center gap-1">
                <x-ui.icon name="message-square" size="14" />
                <span>{{ $repliesText }}</span>
            </span>
        </div>
    </div>

    {{-- Right: Pin toggle form (Gestor / Admin only) - DOM SIBLING of topic link --}}
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
