{{--
    Painel institucional do shell de visitante (`layouts.guest`), à esquerda das
    quatro telas de acesso: login, recuperação, redefinição e convite.

    Props (contrato da biblioteca de componentes): `tenantName` (marca exibida;
    o padrão vem de `session('tenant_name')`, para o aluno ver a marca de quem o
    convidou, e só cai no nome do app quando não há tenant na sessão),
    `headline` (o `h1` da página — o formulário à direita usa `h2`) e `lead`
    (texto institucional; também aceito como slot).

    `brandOnly` renderiza somente a linha de marca (iniciais + nome), sem
    painel: abaixo de `lg` o painel não é renderizado e a marca sobe para o topo
    da coluna de formulário, para o aluno não perder a referência de quem
    convidou.
--}}
@props([
    'tenantName' => null,
    'headline' => 'Acesse a plataforma',
    'lead' => 'Capacitação técnica continuada, provas interativas e emissão de certificados oficiais.',
    'brandOnly' => false,
])

@php
    $brandName = $tenantName ?: session('tenant_name', config('app.name', 'Plataforma EAD'));

    $initials = collect(preg_split('/\s+/', trim((string) $brandName)))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr((string) $brandName, 0, 2));
    }
@endphp

@if ($brandOnly)
    <div {{ $attributes->class(['guest-panel-brand', 'd-flex', 'align-items-center', 'gap-3']) }}>
        <span class="brand-mark" aria-hidden="true">{{ $initials }}</span>
        <span class="fw-bold fs-5 lh-1">{{ $brandName }}</span>
    </div>
@else
    <div {{ $attributes->class(['guest-panel', 'd-none', 'd-lg-flex', 'flex-column', 'justify-content-between', 'bg-primary-subtle', 'text-body', 'p-5', 'min-vh-100']) }}>
        <div class="d-flex align-items-center gap-3">
            <span class="brand-mark" aria-hidden="true">{{ $initials }}</span>
            <span class="fw-bold fs-5 lh-1">{{ $brandName }}</span>
        </div>

        <div class="my-auto">
            <hr class="border-primary border-2 opacity-100 w-25 mb-4">
            <h1 class="fw-bolder mb-4 lh-base">
                {{ $headline }}
            </h1>
            <p class="ds-lead mb-0">
                {{ $slot->isNotEmpty() ? $slot : $lead }}
            </p>
        </div>

        <div class="ds-caption text-body-secondary">
            &copy; {{ date('Y') }} {{ config('app.name', 'Plataforma EAD') }}
        </div>
    </div>
@endif
