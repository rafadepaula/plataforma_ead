{{--
    SPEC-11 / RF11 — the public, unauthenticated Landing Page
    (`GET /`, `landing.show`, rendered by
    `App\Http\Controllers\LandingPageController::show()`). Deliberately NOT
    `@extends('layouts.app')` (requires an authenticated session/sidebar)
    nor `layouts.guest` (that layout's left panel is themed around a login
    form) — this is a standalone marketing document that still pulls in the
    app's compiled CSS via `@vite` for the shared Modernist Design System
    tokens. Carries `<x-help-button key="landing" />` per RF12/RN05's
    100%-of-screens coverage requirement.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Plataforma EAD') }} — Capacitação técnica continuada</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="background: var(--color-bg); color: var(--color-text); font-family: var(--font-body); margin: 0; padding: 0; min-height: 100vh;">

    <header class="nav" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: var(--color-surface); border-bottom: 1px solid var(--color-divider); border-radius: 0px;">
        <span style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; color: var(--color-text);">
            {{ config('app.name', 'Plataforma EAD') }}
        </span>

        <div style="display: flex; align-items: center; gap: 12px;">
            <x-help-button key="landing" />

            @if(Route::has('login'))
                <a href="{{ route('login') }}" dusk="landing-login-link" class="btn btn-primary btn-sm" style="border-radius: 0px;">Entrar</a>
            @endif
        </div>
    </header>

    <main>
        <section style="max-width: 960px; margin: 0 auto; padding: 96px 24px 64px; text-align: center;">
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Educação a Distância</span>

            <h1 dusk="landing-headline" style="font-family: var(--font-heading); font-weight: 800; font-size: 44px; line-height: 1.1; margin: 16px 0;">
                Capacitação técnica continuada, do jeito certo
            </h1>

            <p style="font-size: 16px; color: var(--color-neutral-600); max-width: 56ch; margin: 0 auto 32px; line-height: 1.6;">
                Cursos, provas interativas e certificados oficiais em uma única plataforma,
                pensada para organizações que levam a formação de suas equipes a sério.
            </p>

            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                @if(Route::has('login'))
                    <a href="{{ route('login') }}" dusk="landing-cta-login" class="btn btn-primary" style="border-radius: 0px; padding: 12px 24px;">Acessar plataforma</a>
                @endif
            </div>
        </section>

        <section style="background: var(--color-surface); border-top: 1px solid var(--color-divider); border-bottom: 1px solid var(--color-divider); padding: 64px 24px;">
            <div style="max-width: 960px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 32px;">
                <div>
                    <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; margin-bottom: 8px;">Cursos e Trilhas</h2>
                    <p style="font-size: 14px; color: var(--color-neutral-600); line-height: 1.6;">Módulos, aulas em vídeo e materiais organizados por curso.</p>
                </div>
                <div>
                    <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; margin-bottom: 8px;">Provas Interativas</h2>
                    <p style="font-size: 14px; color: var(--color-neutral-600); line-height: 1.6;">Avaliações automáticas e correção manual de dissertativas.</p>
                </div>
                <div>
                    <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; margin-bottom: 8px;">Certificados Oficiais</h2>
                    <p style="font-size: 14px; color: var(--color-neutral-600); line-height: 1.6;">Emissão automática e validação pública por hash único.</p>
                </div>
            </div>
        </section>

        <section style="max-width: 640px; margin: 0 auto; padding: 64px 24px; text-align: center;">
            <h2 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin-bottom: 12px;">Recebeu um convite?</h2>
            <p style="font-size: 14px; color: var(--color-neutral-600); margin-bottom: 24px; line-height: 1.6;">
                Acesse o link de convite enviado pela sua organização para se matricular em um curso.
            </p>
        </section>
    </main>

    <footer style="padding: 24px; text-align: center; font-size: 12px; color: var(--color-neutral-600); border-top: 1px solid var(--color-divider);">
        © {{ date('Y') }} {{ config('app.name', 'Plataforma EAD') }}. Todos os direitos reservados.
    </footer>


</body>
</html>
