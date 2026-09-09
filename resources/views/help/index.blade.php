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
<x-layout.public :title="'Centro de Ajuda — '.$brandName">
    <div dusk="help-center-index">
        {{-- Header superior com marca, navegação e alternador de tema --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <a href="{{ route('landing.show') }}" class="d-flex align-items-center gap-2 text-decoration-none text-body">
                <span class="brand-mark" aria-hidden="true">{{ $brandMark }}</span>
                <span class="fw-bold fs-5">{{ $brandName }}</span>
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

        {{-- Cabeçalho da página --}}
        <x-layout.page-header
            title="Centro de Ajuda"
            kicker="Documentação e Suporte"
            subtitle="Guias, tutoriais e respostas para todas as funcionalidades da plataforma."
        />

        {{-- Barra de busca --}}
        <div class="mb-5 max-w-reading">
            <form method="GET" action="{{ route('help.index') }}" class="d-flex gap-2" dusk="help-center-search">
                <div class="input-group">
                    <span class="input-group-text bg-body-secondary border-end-0">
                        <x-ui.icon name="search" size="18" class="text-body-secondary" />
                    </span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        class="form-control border-start-0"
                        placeholder="Buscar por artigos, tópicos ou palavras-chave..."
                        aria-label="Buscar artigos"
                    >
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                @if($search)
                    <a href="{{ route('help.index') }}" class="btn btn-outline-secondary">Limpar</a>
                @endif
            </form>
        </div>

        {{-- Listagem de artigos agrupados por categoria --}}
        @if($groupedArticles->isEmpty())
            <x-ui.empty-state
                icon="help-circle"
                title="Nenhum artigo encontrado"
                message="Não encontramos nenhum artigo correspondente à sua busca."
            >
                @if($search)
                    <x-slot:action>
                        <a href="{{ route('help.index') }}" class="btn btn-primary">Ver todos os artigos</a>
                    </x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            @foreach($groupedArticles as $category => $articles)
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <h2 class="h4 mb-0">{{ $category }}</h2>
                        <span class="badge text-bg-secondary">{{ $articles->count() }}</span>
                    </div>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        @foreach($articles as $article)
                            <div class="col">
                                <a href="{{ route('help.show', $article->slug) }}" class="text-decoration-none h-100 d-block">
                                    <x-ui.card surface="white" class="h-100 border">
                                        <h3 class="card-title h6 fw-bold mb-2 text-primary">
                                            {{ $article->title }}
                                        </h3>
                                        <p class="card-text text-body-secondary small mb-3">
                                            {{ Str::limit(strip_tags(Str::markdown($article->content, ['html_input' => 'strip'])), 120) }}
                                        </p>
                                        <div class="mt-auto pt-2 small text-primary fw-semibold d-flex align-items-center gap-1">
                                            <span>Ler artigo</span>
                                            <x-ui.icon name="arrow-right" size="14" />
                                        </div>
                                    </x-ui.card>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-layout.public>
