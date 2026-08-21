{{--
    per-course forum topic list, pinned topics first, then
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
    <x-layout.page-header
        :breadcrumb="[['label' => 'Meus Cursos', 'url' => route('student.courses.index')], ['label' => $course->title, 'url' => route('classroom.show', $course)], ['label' => 'Fórum']]"
        kicker="Fórum"
        :title="$course->title"
        subtitle="Tire dúvidas e converse com a turma sobre este curso."
    >
        @if($canCreateTopic)
            <x-slot:actions>
                <x-ui.button class="d-none d-lg-inline-flex" data-bs-toggle="modal" data-bs-target="#new-topic-modal" dusk="new-topic-button">Novo Tópico</x-ui.button>
            </x-slot:actions>
        @endif
    </x-layout.page-header>

    @if($canCreateTopic)
        <x-ui.fab label="Novo tópico" icon="plus" class="d-lg-none" data-bs-toggle="modal" data-bs-target="#new-topic-modal" />
    @endif

    @forelse($topics as $topic)
        @include('forum.partials._topic', ['course' => $course, 'topic' => $topic, 'canPin' => $canPin])
    @empty
        <x-ui.empty-state icon="message-square" title="Nenhum tópico por aqui" description="Abra o primeiro tópico e comece a conversa com a turma." dusk="no-topics">
            @if($canCreateTopic)
                <x-slot:action>
                    <x-ui.button variant="tonal" data-bs-toggle="modal" data-bs-target="#new-topic-modal">Novo Tópico</x-ui.button>
                </x-slot:action>
            @endif
        </x-ui.empty-state>
    @endforelse

    <x-ui.pagination :paginator="$topics" label="Paginação dos tópicos" />

    @if($canCreateTopic)
        <x-ui.modal id="new-topic-modal" title="Novo Tópico" size="md">
            {{-- `id="new-topic-form"` + each submit/cancel button's `form="new-topic-form"`:
                 `x-ui.modal`'s `actions` slot renders in `.modal-footer`,
                 a sibling of `.modal-body` — NOT a descendant of this
                 `<form>` — so a plain nested `<button type="submit">`
                 inside that slot would never trigger this form's submit
                 (native forms only submit for descendant/associated
                 controls, HTML5 §4.10.18.6). --}}
            <form id="new-topic-form" method="POST" action="{{ route('forum.store', $course) }}" dusk="new-topic-form">
                @csrf

                <x-ui.field-stack>
                    <x-ui.input name="title" label="Título" required dusk="new-topic-title" />

                    <x-ui.textarea name="content" label="Conteúdo" :rows="5" required dusk="new-topic-content" />
                </x-ui.field-stack>

                <x-slot:actions>
                    <x-ui.button variant="ghost" data-bs-dismiss="modal">Cancelar</x-ui.button>
                    <x-ui.button type="submit" form="new-topic-form" dusk="new-topic-submit">Publicar Tópico</x-ui.button>
                </x-slot:actions>
            </form>
        </x-ui.modal>
    @endif

@endsection
