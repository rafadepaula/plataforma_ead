@extends('layouts.app')

@php
    // mesma gestão de conteúdo atende Admin/Gestor (catálogo) e Professor
    // atribuído (professor.courses.index) — a raiz do breadcrumb e o botão
    // "voltar" apontam para a home de quem está logado.
    $coursesRoot = auth()->user()?->hasRole('professor')
        ? ['label' => 'Meus Cursos', 'url' => route('professor.courses.index')]
        : ['label' => 'Cursos', 'url' => route('courses.index')];

    // Mesmo perímetro do `ModulePolicy::authorizeForCourse()` (que já
    // autorizou esta tela) e do `EnsureStudentIsEnrolled` no fórum: Admin
    // sempre, Gestor da própria org e Professor atribuído
    // (`User::teaches()`) — o fórum é acessível a todos eles.
    $canManageForum = auth()->user()?->hasRole('admin')
        || (auth()->user()?->hasRole('gestor') && (int) \App\Services\OrgContext::current()->orgId() === (int) $course->org_id)
        || auth()->user()?->teaches($course);
@endphp

@section('content')
    <x-layout.page-header
        :breadcrumb="[$coursesRoot, ['label' => $course->title]]"
        :kicker="$course->title"
        title="Módulos"
        subtitle="Arraste os módulos para reordená-los. A nova ordem é salva automaticamente."
    >
        <x-slot:actions>
            <x-ui.button variant="tonal" :href="$coursesRoot['url']">Voltar</x-ui.button>
            @if($canManageForum)
                <x-ui.button variant="ghost"
                             icon="message-square"
                             :href="route('forum.index', $course)"
                             dusk="course-forum-link">Fórum de dúvidas</x-ui.button>
            @endif
            <x-ui.button href="{{ route('courses.modules.create', $course) }}" dusk="new-module">Novo Módulo</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div id="module-list-container" dusk="module-list-container">
        @include('courses.modules._list', ['course' => $course, 'modules' => $modules])
    </div>
@endsection
