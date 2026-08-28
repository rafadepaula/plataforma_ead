@extends('layouts.app')

@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $topics */
    /** @var bool $canCreateTopic */
    /** @var bool $canPin */
@endphp

@section('content')
    <div class="mx-auto max-w-880 forum-container-with-fab">
        <x-layout.page-header
            :breadcrumb="[
                ['label' => 'Meus cursos', 'url' => route('student.courses.index')],
                ['label' => $course->title, 'url' => route('classroom.show', $course)],
                ['label' => 'Fórum'],
            ]"
            kicker="Fórum"
            :title="$course->title"
            subtitle="Tire dúvidas e converse com a turma sobre este curso."
        >
            <x-slot:actions>
                @if($canCreateTopic)
                    <div class="d-none d-lg-inline-flex">
                        <x-ui.button
                            variant="primary"
                            icon="plus"
                            data-bs-toggle="modal"
                            data-bs-target="#new-topic-modal"
                            dusk="new-topic-button"
                        >
                            Novo tópico
                        </x-ui.button>
                    </div>
                @endif
            </x-slot:actions>
        </x-layout.page-header>

        @if($topics->isEmpty())
            <x-ui.empty-state
                icon="message-square"
                title="Nenhum tópico criado ainda"
                description="Seja o primeiro a iniciar uma discussão sobre este curso!"
                dusk="no-topics"
            >
                @if($canCreateTopic)
                    <x-slot:action>
                        <x-ui.button
                            variant="primary"
                            icon="plus"
                            data-bs-toggle="modal"
                            data-bs-target="#new-topic-modal"
                            dusk="empty-new-topic-button"
                        >
                            Criar primeiro tópico
                        </x-ui.button>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="d-flex flex-column gap-2 mb-4">
                @foreach($topics as $topic)
                    @include('forum.partials._topic', ['course' => $course, 'topic' => $topic, 'canPin' => $canPin])
                @endforeach
            </div>

            @if($topics->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $topics->links() }}
                </div>
            @endif
        @endif

        {{-- Mobile FAB --}}
        @if($canCreateTopic)
            <div class="d-lg-none">
                <x-ui.fab
                    icon="plus"
                    label="Novo tópico"
                    data-bs-toggle="modal"
                    data-bs-target="#new-topic-modal"
                    dusk="new-topic-fab"
                />
            </div>
        @endif
    </div>

    {{-- Creation Modal --}}
    @if($canCreateTopic)
        <x-ui.modal id="new-topic-modal" title="Novo tópico de discussão" size="md">
            <form id="new-topic-form" method="POST" action="{{ route('forum.store', $course) }}" dusk="new-topic-form">
                @csrf
                <x-ui.input
                    name="title"
                    label="Título do tópico"
                    placeholder="Ex: Dúvida sobre o módulo 2"
                    required
                    dusk="new-topic-title"
                    class="mb-3"
                />

                <x-ui.input
                    type="textarea"
                    name="content"
                    label="Mensagem"
                    rows="5"
                    placeholder="Descreva sua dúvida ou comentário detalhadamente..."
                    required
                    dusk="new-topic-content"
                />
            </form>

            <x-slot:actions>
                <x-ui.button variant="secondary" data-bs-dismiss="modal">
                    Cancelar
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    type="submit"
                    form="new-topic-form"
                    dusk="new-topic-submit"
                >
                    Publicar tópico
                </x-ui.button>
            </x-slot:actions>
        </x-ui.modal>
    @endif
@endsection
