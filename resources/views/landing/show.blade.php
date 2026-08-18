{{--
    the public, unauthenticated Landing Page
    (`GET /`, `landing.show`, rendered by
    `App\Http\Controllers\LandingPageController::show()`). Deliberately NOT
    `@extends('layouts.app')` (requires an authenticated session/sidebar)
    nor `layouts.guest` (that layout's left panel is themed around a login
    form) — o shell HTML standalone vive agora em `<x-layout.public>`.

    Passa `:container="false"` porque as faixas são full-bleed: cada seção
    declara o próprio `.container`. Não passa o slot `footer` — o rodapé
    padrão do layout já reproduz o texto desta tela.

    Carrega `<x-help-button key="landing" />` per 's
    100%-of-screens coverage requirement.
--}}
<x-layout.public :title="config('app.name', 'Plataforma EAD').' — Capacitação técnica continuada'" :container="false">

    <nav class="navbar navbar-expand-lg border-bottom bg-body-tertiary">
        <div class="container-fluid px-4">
            <span class="navbar-brand fw-bold mb-0">
                {{ config('app.name', 'Plataforma EAD') }}
            </span>

            <div class="d-flex align-items-center gap-3">
                <x-help-button key="landing" />

                @if(Route::has('login'))
                    <a href="{{ route('login') }}" dusk="landing-login-link" class="btn btn-primary btn-sm">Entrar</a>
                @endif
            </div>
        </div>
    </nav>

    <section class="container py-5 text-center">
        <span class="kicker text-primary">Educação a Distância</span>

        <h1 dusk="landing-headline" class="display-5 fw-bold my-4">
            Capacitação técnica continuada, do jeito certo
        </h1>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <p class="lead text-body-secondary mb-5">
                    Cursos, provas interativas e certificados oficiais em uma única plataforma,
                    pensada para organizações que levam a formação de suas equipes a sério.
                </p>
            </div>
        </div>

        <div class="d-flex gap-3 justify-content-center flex-wrap">
            @if(Route::has('login'))
                <a href="{{ route('login') }}" dusk="landing-cta-login" class="btn btn-primary">Acessar plataforma</a>
            @endif
        </div>
    </section>

    <section class="bg-body-tertiary border-top border-bottom py-5">
        <div class="container">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <div class="col">
                    <x-ui.card class="h-100">
                        <h2 class="h5 mb-2">Cursos e Trilhas</h2>
                        <p class="mb-0 text-body-secondary">Módulos, aulas em vídeo e materiais organizados por curso.</p>
                    </x-ui.card>
                </div>
                <div class="col">
                    <x-ui.card class="h-100">
                        <h2 class="h5 mb-2">Provas Interativas</h2>
                        <p class="mb-0 text-body-secondary">Avaliações automáticas e correção manual de dissertativas.</p>
                    </x-ui.card>
                </div>
                <div class="col">
                    <x-ui.card class="h-100">
                        <h2 class="h5 mb-2">Certificados Oficiais</h2>
                        <p class="mb-0 text-body-secondary">Emissão automática e validação pública por hash único.</p>
                    </x-ui.card>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-6">
                <h2 class="h3 mb-3">Recebeu um convite?</h2>
                <p class="mb-0 text-body-secondary">
                    Acesse o link de convite enviado pela sua organização para se matricular em um curso.
                </p>
            </div>
        </div>
    </section>

</x-layout.public>
