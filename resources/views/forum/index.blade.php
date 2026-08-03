{{--
    SPEC-10 RF22 — per-course forum topic list, pinned topics first, then
    newest first. Reached via `GET courses/{course}/forum` (`forum.index`,
    `App\Http\Controllers\ForumTopicController::index()`, Bucket 2),
    behind the enrollment-gated `student.enrolled` middleware (Aluno needs
    an active/completed `course_user` row; Gestor/Admin of the Org are
    always allowed — mirrors `classroom.show`'s guard).

    Expected variables:
      - `$course`   the bound Course.
      - `$topics`   paginated, `is_pinned` desc then `created_at` desc,
                     with `user` eager-loaded and `withCount('replies')`.
      - `$canCreateTopic`  bool.
      - `$canPin`          bool — Gestor/Admin of this Org.

    Expected route (Bucket 2 contract): `forum.store` POST courses/{course}/forum.
--}}
@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Fórum</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">{{ $course->title }}</h1>
        </div>

        @if($canCreateTopic)
            <x-ui.button data-modal-target="new-topic-modal" dusk="new-topic-button">Novo Tópico</x-ui.button>
        @endif
    </div>

    @forelse($topics as $topic)
        @include('forum.partials._topic', ['course' => $course, 'topic' => $topic, 'canPin' => $canPin])
    @empty
        <p style="color: var(--color-neutral-600);" dusk="no-topics">Nenhum tópico criado neste fórum ainda.</p>
    @endforelse

    <div style="margin-top: 20px;">
        {{ $topics->links() }}
    </div>

    @if($canCreateTopic)
        <x-ui.modal id="new-topic-modal" title="Novo Tópico" size="md">
            {{-- `id="new-topic-form"` + each submit/cancel button's `form="new-topic-form"`:
                 `x-ui.modal`'s `actions` slot renders in `.dialog-actions`,
                 a sibling of `.dialog-body` — NOT a descendant of this
                 `<form>` — so a plain nested `<button type="submit">`
                 inside that slot would never trigger this form's submit
                 (native forms only submit for descendant/associated
                 controls, HTML5 §4.10.18.6). --}}
            <form id="new-topic-form" method="POST" action="{{ route('forum.store', $course) }}" dusk="new-topic-form">
                @csrf

                <label for="title" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;">Título</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    required
                    value="{{ old('title') }}"
                    dusk="new-topic-title"
                    style="width: 100%; box-sizing: border-box; border: 1px solid var(--color-divider); padding: 10px; font-family: inherit; font-size: 13px; border-radius: 0px; margin-bottom: 12px;"
                >
                @error('title')
                    <p style="color: var(--color-danger-700, #b3261e); font-size: 12px; margin: -8px 0 12px;">{{ $message }}</p>
                @enderror

                <label for="content" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;">Conteúdo</label>
                <textarea
                    id="content"
                    name="content"
                    rows="5"
                    required
                    dusk="new-topic-content"
                    style="width: 100%; box-sizing: border-box; border: 1px solid var(--color-divider); padding: 10px; font-family: inherit; font-size: 13px; border-radius: 0px;"
                >{{ old('content') }}</textarea>
                @error('content')
                    <p style="color: var(--color-danger-700, #b3261e); font-size: 12px; margin: 6px 0 0;">{{ $message }}</p>
                @enderror

                <x-slot:actions>
                    <button type="button" class="btn btn-ghost" data-modal-dismiss="true" style="border-radius: 0px;">Cancelar</button>
                    <button type="submit" form="new-topic-form" class="btn btn-primary" style="border-radius: 0px;" dusk="new-topic-submit">Publicar Tópico</button>
                </x-slot:actions>
            </form>
        </x-ui.modal>
    @endif

    @push('scripts')
        <script>
            // `x-ui.modal`'s backdrop ships with a static inline `display:
            // flex` and relies on Alpine.js's `x-show="show"` to hide
            // itself, but Alpine.js is not installed in this project (see
            // `resources/views/certificates/index.blade.php`'s same fix) —
            // so this page hides its own modals explicitly on load.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.dialog-backdrop').forEach(function (backdrop) {
                    backdrop.style.display = 'none';
                });
            });
        </script>
    @endpush
@endsection
