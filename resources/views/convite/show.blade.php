@php
    /** @var \App\Models\StudentInvitation $invitation */
    /** @var \Illuminate\Support\Collection<int, string> $courseTitles */
    $student = $invitation->student;
    $firstName = \Illuminate\Support\Str::before($student?->name ?? '', ' ');
    $orgName = $invitation->organization?->name;
    $token = $invitation->token;
@endphp

@extends('layouts.guest')

@section('content')
    {{-- `level="h2"`: o `h1` da página é o do painel institucional do shell. --}}
    <x-layout.page-header
        kicker="Boas-vindas"
        :title="'Olá, '.$firstName.'!'"
        level="h2"
        subtitle="Tudo pronto para o seu começo — só falta escolher a sua senha."
    />

    @if($orgName)
        <p class="guest-hint mb-4">
            A <strong>{{ $orgName }}</strong> criou uma conta para você nesta
            plataforma. Nada de cadastro demorado: confirme quem você é,
            escolha uma senha e pronto — seus cursos já te esperam.
        </p>
    @endif

    @if($courseTitles->isNotEmpty())
        <div class="mb-4">
            <p class="fw-semibold mb-2">Você já tem acesso a:</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach($courseTitles as $courseTitle)
                    <x-ui.badge variant="success">{{ $courseTitle }}</x-ui.badge>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Identidade somente leitura: vem do token (único por aluno), nunca do
         POST — o campo desabilitado é visual; o backend sequer lê `email`. --}}
    <form method="POST"
          action="{{ route('invitation.store', $token) }}"
          dusk="invitation-form">
        @csrf

        <p class="fw-semibold mb-3">Confirme seus dados</p>

        <x-ui.input
            type="email"
            name="email_display"
            label="Seu e-mail de acesso"
            :value="$student?->email"
            readonly
            dusk="invitation-email"
        />
        <x-ui.input
            name="name_display"
            label="Seu nome"
            :value="$student?->name"
            readonly
            class="mt-3"
            dusk="invitation-name"
        />

        <p class="fw-semibold mt-4 mb-3">Agora escolha sua senha</p>

        <x-ui.input
            type="password"
            name="password"
            label="Sua nova senha"
            hint="Pelo menos 8 caracteres. Anote em um lugar seguro!"
            required
            autofocus
            dusk="invitation-password"
        />

        <x-ui.input
            type="password"
            name="password_confirmation"
            label="Digite a senha novamente"
            required
            class="mt-3"
            dusk="invitation-password-confirmation"
        />

        {{-- Interruptor obrigatório: o erro vem em contorno `.is-invalid` mais
             mensagem, nunca só a bolha nativa do navegador. --}}
        <div class="mb-4 mt-3">
            <x-ui.switch
                name="consent"
                value="1"
                required
                :label="$orgName
                    ? 'Concordo que a '.$orgName.' organize meus cursos e meus dados de estudo nesta plataforma.'
                    : 'Concordo que a organização responsável organize meus cursos e meus dados de estudo nesta plataforma.'"
                dusk="invitation-consent"
            />
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-100" dusk="invitation-submit">
            Salvar senha e começar
        </x-ui.button>
    </form>
@endsection
