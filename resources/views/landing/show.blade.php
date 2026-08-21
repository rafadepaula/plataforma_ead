<x-layout.public
    :title="config('app.name', 'Plataforma EAD').' — Capacitação técnica continuada'"
    :container="false"
    surface="white"
    class="landing-page"
>
    <header class="landing-header border-bottom">
        <div class="landing-header-inner d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <span class="brand-mark" aria-hidden="true">PE</span>
                <span class="landing-brand-name">{{ config('app.name', 'Plataforma EAD') }}</span>
            </div>

            <div class="d-flex align-items-center gap-3 gap-md-4">
                <x-help-button key="landing" />

                @if(Route::has('login'))
                    <x-ui.button variant="primary" size="sm" href="{{ route('login') }}" dusk="landing-login-link">
                        Entrar
                    </x-ui.button>
                @endif
            </div>
        </div>
    </header>

    <section class="landing-band landing-hero-band">
        <div class="landing-container">
            <div class="landing-hero text-center">
                <x-ui.badge size="lg">Educação a Distância</x-ui.badge>

                <h1 dusk="landing-headline" class="landing-headline">
                    Capacitação técnica continuada, do jeito certo
                </h1>

                <p class="landing-lead">
                    Cursos, provas interativas e certificados oficiais em uma única plataforma,
                    pensada para organizações que levam a formação de suas equipes a sério.
                </p>

                @if(Route::has('login'))
                    <div class="d-flex justify-content-center mt-7x">
                        <x-ui.button variant="primary" size="lg" href="{{ route('login') }}" dusk="landing-cta-login">
                            Acessar plataforma
                        </x-ui.button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="landing-band">
        <div class="landing-container landing-grid landing-grid-3">
            <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                <h2 class="landing-card-title">Cursos e Trilhas</h2>
                <p class="landing-card-copy">Módulos, aulas em vídeo e materiais organizados por curso.</p>
            </x-ui.card>

            <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                <h2 class="landing-card-title">Provas Interativas</h2>
                <p class="landing-card-copy">Avaliações automáticas e correção manual de dissertativas.</p>
            </x-ui.card>

            <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                <h2 class="landing-card-title">Certificados Oficiais</h2>
                <p class="landing-card-copy">Emissão automática e validação pública por hash único.</p>
            </x-ui.card>
        </div>
    </section>

    <section class="landing-band landing-process-band">
        <div class="landing-container">
            <div class="landing-section-heading text-center">
                <div class="ds-overline text-primary">Do convite ao certificado</div>
                <h2>Como funciona</h2>
            </div>

            <div class="landing-grid landing-grid-4">
                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                    <span class="landing-step" aria-hidden="true">1</span>
                    <h3 class="landing-step-title">A Organização publica</h3>
                    <p class="landing-card-copy">O Gestor cria os Cursos, organiza Módulos e Aulas e define as Provas.</p>
                </x-ui.card>

                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                    <span class="landing-step" aria-hidden="true">2</span>
                    <h3 class="landing-step-title">Você recebe o convite</h3>
                    <p class="landing-card-copy">O link enviado pela sua Organização confirma a Matrícula em poucos campos.</p>
                </x-ui.card>

                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                    <span class="landing-step" aria-hidden="true">3</span>
                    <h3 class="landing-step-title">Você estuda e é avaliado</h3>
                    <p class="landing-card-copy">Assista às Aulas na sala de aula, tire dúvidas no fórum e faça as Provas.</p>
                </x-ui.card>

                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card h-100">
                    <span class="landing-step" aria-hidden="true">4</span>
                    <h3 class="landing-step-title">O Certificado sai na hora</h3>
                    <p class="landing-card-copy">Ao concluir, o Certificado é emitido e qualquer pessoa pode validá-lo pelo hash.</p>
                </x-ui.card>
            </div>
        </div>
    </section>

    <section class="landing-band landing-showcase-band">
        <div class="landing-container">
            <div class="landing-section-heading text-center">
                <div class="ds-overline text-primary">Por dentro da plataforma</div>
                <h2>As telas que você vai usar</h2>
                <p class="landing-lead">Sem montagem: são os mesmos componentes que aparecem depois do login.</p>
            </div>

            <div class="landing-grid landing-grid-3">
                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card landing-showcase-card h-100">
                    <x-slot:image>
                        <div class="landing-course-status">
                            <x-ui.badge variant="success">Em andamento</x-ui.badge>
                        </div>
                    </x-slot:image>

                    <div class="ds-overline text-primary">Curso</div>
                    <h3 class="landing-card-title">Segurança do trabalho — NR 35</h3>
                    <p class="landing-card-copy">4 Módulos · 18 Aulas · 4 horas</p>

                    <div class="mt-1">
                        <div class="d-flex justify-content-between ds-caption mb-2">
                            <span>Progresso</span>
                            <span>62%</span>
                        </div>
                        <x-ui.progress :value="62" variant="success" :height="8" label="Progresso do curso" />
                    </div>

                    <x-slot:metaSlot>Continue de onde parou · Aula 12 de 18</x-slot:metaSlot>
                </x-ui.card>

                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card landing-showcase-card h-100">
                    <div class="d-flex align-items-center gap-4">
                        <span class="landing-certificate-icon" aria-hidden="true">
                            <x-ui.icon name="award" size="24" />
                        </span>
                        <div>
                            <div class="ds-overline text-primary">Certificado emitido</div>
                            <h3 class="landing-card-title">nº 9f2b7c41</h3>
                        </div>
                    </div>

                    <p class="landing-card-copy">Emitido em 12/03/2026 · 4 horas · Segurança do trabalho — NR 35</p>

                    <div class="d-flex gap-3 flex-wrap mt-1">
                        <x-ui.badge variant="success">Válido</x-ui.badge>
                        <x-ui.badge variant="neutral" :dot="false">Validação pública</x-ui.badge>
                    </div>

                    <x-slot:metaSlot>
                        <x-ui.button variant="ghost" size="sm" icon="upload" href="#">
                            Baixar certificado
                        </x-ui.button>
                    </x-slot:metaSlot>
                </x-ui.card>

                <x-ui.card :border="false" elevation="sm" surface="white" class="landing-card landing-showcase-card h-100">
                    <div class="d-flex align-items-center gap-3">
                        <x-ui.avatar size="lg" initials="JR" />
                        <div>
                            <div class="fw-bold landing-body-small">Joana Ribeiro</div>
                            <div class="ds-caption">Fórum · há 2 horas</div>
                        </div>
                    </div>

                    <h3 class="landing-card-title mt-1">Como registrar o ponto de ancoragem na prática?</h3>
                    <p class="landing-card-copy">Na Aula 9 o instrutor cita a inspeção prévia, mas queria entender o registro em campo.</p>

                    <x-slot:metaSlot>
                        <span class="d-inline-flex align-items-center gap-2">
                            <x-ui.icon name="message-square" size="16" />
                            7 respostas
                        </span>
                    </x-slot:metaSlot>
                </x-ui.card>
            </div>
        </div>
    </section>

    <section id="contato" class="landing-band landing-contact-band text-center">
        <div class="landing-reading-width d-flex flex-column align-items-center gap-4">
            <h2>Deseja utilizar esta plataforma em sua organização?</h2>
            <x-ui.button variant="tonal" href="#">Fale conosco</x-ui.button>
        </div>
    </section>

    <x-slot:footer>
        <div class="landing-footer-inner d-flex flex-wrap align-items-center gap-3">
            <span>&copy; {{ date('Y') }} <strong>{{ config('app.name', 'Plataforma EAD') }}</strong>. Todos os direitos reservados.</span>
            <nav class="d-flex flex-wrap justify-content-center gap-4" aria-label="Links institucionais">
                <a href="#" class="text-body-secondary text-decoration-none">Validar certificado</a>
                <a href="#" class="text-body-secondary text-decoration-none">Termos de uso</a>
                <a href="#" class="text-body-secondary text-decoration-none">Privacidade</a>
                <a href="#contato" class="text-body-secondary text-decoration-none">Suporte</a>
            </nav>
        </div>
    </x-slot:footer>
</x-layout.public>
