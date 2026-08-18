{{--
    a single `ForumTopic` row within `forum.index`'s list.

    Expected variables:
      - `$course`, `$topic`  (`user` loaded, `replies_count` via `withCount`).
      - `$canPin`             bool — Gestor/Admin of this Org.

    Expected route (Bucket 2 contract): `forum.pin` POST .../topics/{topic}/pin.
--}}
<div class="card mb-2" dusk="topic-row-{{ $topic->id }}">
    <div class="card-body d-flex align-items-center justify-content-between gap-3 py-3">
        <a href="{{ route('forum.show', [$course, $topic]) }}" class="text-body text-decoration-none flex-fill min-w-0" dusk="open-topic-{{ $topic->id }}">
            <div class="d-flex align-items-center gap-2">
                @if($topic->is_pinned)
                    <x-ui.badge variant="accent" dusk="pinned-badge-{{ $topic->id }}">Fixado</x-ui.badge>
                @endif
                <strong>{{ $topic->title }}</strong>
            </div>
            <div class="small text-body-secondary mt-1">
                {{ $topic->user->name }} — {{ $topic->created_at->format('d/m/Y H:i') }}
                — {{ $topic->replies_count ?? $topic->replies()->count() }} {{ Str::plural('resposta', $topic->replies_count ?? 0) }}
            </div>
        </a>

        @if($canPin)
            <form method="POST" action="{{ route('forum.pin', [$course, $topic]) }}" dusk="pin-form-{{ $topic->id }}">
                @csrf
                @method('POST')
                <x-ui.button type="submit" variant="ghost" size="sm" dusk="pin-topic-{{ $topic->id }}">
                    {{ $topic->is_pinned ? 'Desafixar' : 'Fixar' }}
                </x-ui.button>
            </form>
        @endif
    </div>
</div>
