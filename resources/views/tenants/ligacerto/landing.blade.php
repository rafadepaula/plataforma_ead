<x-layout.public
    :title="$organization->name.' — LigaCerto'"
    :container="false"
    surface="white"
    class="landing-page"
>
    @php
        $brandName = $organization->name;
        $brandLogo = $organization->logo_path;
        $dashboardRoute = auth()->check()
            ? (auth()->user()->hasRole(\App\Enums\Permissions\RolesEnum::ALUNO->value)
                ? (Route::has('student.courses.index') ? route('student.courses.index') : url('/'))
                : (Route::has('admin.dashboard') ? route('admin.dashboard') : url('/')))
            : null;
    @endphp

    {{-- Band 1: Header --}}
    <header class="landing-header border-bottom">
        <div class="landing-header-inner d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                @if ($brandLogo)
                    <img src="{{ Storage::url($brandLogo) }}" alt="{{ $brandName }}" class="brand-logo" dusk="landing-org-logo">
                @else
                    <span class="brand-mark" aria-hidden="true">LC</span>
                @endif
                <span class="landing-brand-name">{{ $brandName }}</span>
            </div>

            <div class="d-flex align-items-center gap-3 gap-md-4">
                <x-help-button key="landing" />
                <x-ui.theme-toggle />

                @auth
                    <x-ui.button variant="primary" size="sm" href="{{ $dashboardRoute }}" dusk="landing-login-link">
                        Acessar plataforma
                    </x-ui.button>
                @else
                    @if (Route::has('login'))
                        <x-ui.button variant="primary" size="sm" href="{{ route('login') }}" dusk="landing-login-link">
                            Entrar
                        </x-ui.button>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    {{-- Band 2: Hero --}}
    <section class="landing-band landing-hero-band ds-band-blue">
        <div class="landing-container">
            <div class="ds-hero-card landing-hero text-center">
                <span class="tag tag-blue mb-3">LigaCerto — Treinamentos Esportivos</span>
                <h1 class="landing-title">Capacitação para ligas esportivas com certificado em dia</h1>
                <p class="landing-lead mx-auto">
                    Cursos organizados pela {{ $brandName }} para técnicos, arbitragem e gestão de
                    ligas. Matrícula pelo link do seu campeonato.
                </p>
                @guest
                    <x-ui.button variant="primary" size="lg" href="{{ route('login') }}" dusk="landing-hero-cta">
                        Entrar na plataforma
                    </x-ui.button>
                @endguest
            </div>
        </div>
    </section>

    {{-- Band 3: Como funciona --}}
    <section class="landing-band">
        <div class="landing-container">
            <h2 class="landing-section-title text-center">Como funciona</h2>
            <div class="row g-4 mt-4">
                <div class="col-md-4">
                    <div class="ds-card p-4 h-100">
                        <h3 class="h5">1. Receba o convite</h3>
                        <p class="mb-0">Sua liga envia o link de matrícula do curso da temporada.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-card p-4 h-100">
                        <h3 class="h5">2. Estude no seu ritmo</h3>
                        <p class="mb-0">Aulas em vídeo, materiais e quizzes no portal da {{ $brandName }}.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-card p-4 h-100">
                        <h3 class="h5">3. Emita o certificado</h3>
                        <p class="mb-0">Concluiu o curso? O certificado sai na hora, com validação pública.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Band 4: Footer --}}
    <footer class="landing-footer border-top">
        <div class="landing-container d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <span class="text-body-secondary">© {{ date('Y') }} {{ $brandName }}</span>
            <div class="d-flex gap-4">
                <a href="{{ route('certificates.verify') }}" class="text-body-secondary">Validar certificado</a>
                @guest
                    <a href="{{ route('login') }}" class="text-body-secondary">Entrar</a>
                @endguest
            </div>
        </div>
    </footer>
</x-layout.public>
