{{--
    SPEC-10 — a single `ForumTopic` row within `forum.index`'s list.

    Expected variables:
      - `$course`, `$topic`  (`user` loaded, `replies_count` via `withCount`).
      - `$canPin`             bool — Gestor/Admin of this Org.

    Expected route (Bucket 2 contract): `forum.pin` POST .../topics/{topic}/pin.
--}}
<div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); margin-bottom: 8px;" dusk="topic-row-{{ $topic->id }}">
    <a href="{{ route('forum.show', [$course, $topic]) }}" style="color: var(--color-text); text-decoration: none; flex: 1; min-width: 0;" dusk="open-topic-{{ $topic->id }}">
        <div style="display: flex; align-items: center; gap: 8px;">
            @if($topic->is_pinned)
                <x-ui.badge variant="accent" dusk="pinned-badge-{{ $topic->id }}">Fixado</x-ui.badge>
            @endif
            <strong>{{ $topic->title }}</strong>
        </div>
        <div style="font-size: 12px; color: var(--color-neutral-600); margin-top: 4px;">
            {{ $topic->user->name }} — {{ $topic->created_at->format('d/m/Y H:i') }}
            — {{ $topic->replies_count ?? $topic->replies()->count() }} {{ Str::plural('resposta', $topic->replies_count ?? 0) }}
        </div>
    </a>

    @if($canPin)
        <form method="POST" action="{{ route('forum.pin', [$course, $topic]) }}" dusk="pin-form-{{ $topic->id }}">
            @csrf
            @method('POST')
            <button type="submit" class="btn btn-ghost" style="border-radius: 0px; padding: 6px 12px; font-size: 12px;" dusk="pin-topic-{{ $topic->id }}">
                {{ $topic->is_pinned ? 'Desafixar' : 'Fixar' }}
            </button>
        </form>
    @endif
</div>
