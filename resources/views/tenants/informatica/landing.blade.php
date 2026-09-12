<x-layout.public
    :title="$organization->name.' — Informática+'"
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
                    <span class="brand-mark" aria-hidden="true">I+</span>
                @endif
                <span class="landing-brand-name">{{ $brandName }}</span>
            </div>

            <div class="d-flex align-items-center gap-3 gap-md-4">
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
                <span class="tag tag-blue mb-3">Informática+ — Formação em TI</span>
                <h1 class="landing-title">Cursos de informática com certificado reconhecido</h1>
                <p class="landing-lead mx-auto">
                    Formação prática em {{ $brandName }}: suporte, redes, office e programação para
                    quem entra no mercado de tecnologia.
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
                        <h3 class="h5">1. Matricule-se</h3>
                        <p class="mb-0">Escolha o curso e receba o acesso pelo e-mail da {{ $brandName }}.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-card p-4 h-100">
                        <h3 class="h5">2. Pratique</h3>
                        <p class="mb-0">Laboratórios em vídeo e exercícios passo a passo, no seu ritmo.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-card p-4 h-100">
                        <h3 class="h5">3. Certifique-se</h3>
                        <p class="mb-0">Certificado digital ao concluir, com verificação pública de autenticidade.</p>
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
