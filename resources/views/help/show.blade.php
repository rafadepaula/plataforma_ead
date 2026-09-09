@php
    $brandName = config('app.name', 'Plataforma EAD');
    $brandMark = collect(preg_split('/\s+/', trim((string) $brandName)))
        ->filter()
        ->take(2)
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->implode('');
    if ($brandMark === '') {
        $brandMark = mb_strtoupper(mb_substr((string) $brandName, 0, 2));
    }

    $dashboardRoute = auth()->check()
        ? (auth()->user()->hasRole(\App\Enums\Permissions\RolesEnum::ALUNO->value)
            ? (Route::has('student.courses.index') ? route('student.courses.index') : url('/'))
            : (Route::has('admin.dashboard') ? route('admin.dashboard') : url('/')))
        : null;
@endphp
<x-layout.public :title="$article->title.' — Centro de Ajuda'">
    <div dusk="help-article-page">
        {{-- Header superior com marca, navegação e alternador de tema --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <a href="{{ route('help.index') }}" class="d-flex align-items-center gap-2 text-decoration-none text-body">
                <span class="brand-mark" aria-hidden="true">{{ $brandMark }}</span>
                <span class="fw-bold fs-5">{{ $brandName }}</span>
                <span class="text-body-secondary ms-1">› Centro de Ajuda</span>
            </a>

            <div class="d-flex align-items-center gap-2">
                <x-ui.theme-toggle />
                @auth
                    <a href="{{ $dashboardRoute }}" class="btn btn-outline-primary btn-sm">Acessar Plataforma</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Entrar</a>
                    @endif
                @endauth
            </div>
        </div>

        @php
            $breadcrumbs = [
                ['label' => 'Centro de Ajuda', 'url' => route('help.index')],
                ['label' => $article->category ?: 'Geral'],
            ];
        @endphp

        <x-layout.page-header
            :title="$article->title"
            :breadcrumb="$breadcrumbs"
        >
            <x-slot:actions>
                <a href="{{ route('help.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                    <x-ui.icon name="arrow-left" size="16" />
                    <span>Voltar ao Centro de Ajuda</span>
                </a>
            </x-slot:actions>
        </x-layout.page-header>

        <div class="max-w-reading mb-5">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom text-body-secondary small">
                <x-ui.badge variant="primary" :dot="false">{{ $article->category ?: 'Geral' }}</x-ui.badge>
                <span>Atualizado em {{ $article->updated_at?->format('d/m/Y') ?? date('d/m/Y') }}</span>
            </div>

            <article class="ds-prose">
                {!! Str::markdown($article->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </article>

            <div class="mt-5 pt-4 border-top">
                <a href="{{ route('help.index') }}" class="btn btn-link text-body text-decoration-none ps-0">
                    &larr; Voltar para todos os artigos
                </a>
            </div>
        </div>
    </div>
</x-layout.public>
