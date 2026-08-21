{{--
    a single `ForumTopic` row within `forum.index`'s list.

    Expected variables:
      - `$course`, `$topic`  (`user` loaded, `replies_count` via `withCount`).
      - `$canPin`             bool — Gestor/Admin of this Org.

    Expected route (Bucket 2 contract): `forum.pin` POST .../topics/{topic}/pin.
--}}
@php
    $initials = collect(explode(' ', trim($topic->user->name)))
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $repliesCount = $topic->replies_count ?? $topic->replies()->count();
@endphp
<div class="card card-interactive mb-2" dusk="topic-row-{{ $topic->id }}">
    <div class="card-body d-flex align-items-center justify-content-between gap-3 py-3">
        <a href="{{ route('forum.show', [$course, $topic]) }}" class="text-body text-decoration-none flex-fill min-w-0 d-flex align-items-start gap-3" dusk="open-topic-{{ $topic->id }}">
            <x-ui.avatar :initials="$initials" class="flex-shrink-0" />

            <div class="min-w-0">
                <div class="d-flex align-items-center gap-2">
                    @if($topic->is_pinned)
                        <x-ui.badge variant="accent" dusk="pinned-badge-{{ $topic->id }}">Fixado</x-ui.badge>
                    @endif
                    <h4 class="mb-0 fs-6">{{ $topic->title }}</h4>
                </div>

                <p class="small text-body-secondary mt-1 mb-1 line-clamp-2">{{ $topic->content }}</p>

                <div class="small text-body-secondary d-flex align-items-center gap-1">
                    <span>{{ $topic->user->name }} — {{ $topic->created_at->format('d/m/Y H:i') }}</span>
                    <span class="d-inline-flex align-items-center gap-1 ms-2">
                        <x-ui.icon name="message-square" size="16" />
                        {{ $repliesCount }} {{ Str::plural('resposta', $repliesCount) }}
                    </span>
                </div>
            </div>
        </a>

        @if($canPin)
            <form method="POST" action="{{ route('forum.pin', [$course, $topic]) }}" dusk="pin-form-{{ $topic->id }}">
                @csrf
                @method('POST')
                <x-ui.button type="submit" variant="ghost" dusk="pin-topic-{{ $topic->id }}">
                    {{ $topic->is_pinned ? 'Desafixar' : 'Fixar' }}
                </x-ui.button>
            </form>
        @endif
    </div>
</div>
