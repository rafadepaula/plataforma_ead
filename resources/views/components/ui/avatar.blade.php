{{--
    x-ui.avatar — wrapper Blade sobre `.ds-avatar`/`.ds-avatar-lg`/`.ds-avatar-xl`,
    definidas em `resources/scss/components/_avatar.scss` (Fase 1) e NÃO
    duplicadas aqui. Aquelas classes já são consumidas diretamente por
    `x-layout.topbar` (app bar) e pelo drawer mobile — este componente é o
    caminho para qualquer outro lugar que precise do mesmo círculo de
    foto/iniciais (listas de usuários, cards de perfil), sem criar um segundo
    visual divergente.

    Props:
      - size     string ('md')  sm|md|lg -> `.ds-avatar`|`.ds-avatar-lg`|`.ds-avatar-xl`.
                                 "sm" e "md" resolvem para a mesma `.ds-avatar`
                                 base (32px) porque o design system só define
                                 3 tamanhos reais (base/lg/xl); "sm" existe como
                                 alias semântico para quem já pensa em 3 portes.
      - initials string (null)  iniciais exibidas quando não há imagem.
      - src      string (null)  atalho para renderizar `<img src="$src">`.
      - alt      string ('')    texto alternativo da imagem.

    Uso:
      <x-ui.avatar initials="AB" />
      <x-ui.avatar size="lg" :src="$user->avatar_url" :alt="$user->name" />
      <x-ui.avatar size="xl"><img src="{{ $user->avatar_url }}" alt=""></x-ui.avatar>
--}}
@props([
    'size' => 'md',
    'initials' => null,
    'src' => null,
    'alt' => '',
])

@php
    $sizeClass = match ($size) {
        'lg' => 'ds-avatar-lg',
        'xl' => 'ds-avatar-xl',
        default => null,
    };

    $classes = collect(['ds-avatar', $sizeClass])->filter()->implode(' ');
    $hasSlot = trim($slot) !== '';
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($hasSlot)
        {{ $slot }}
    @elseif ($src)
        <img src="{{ $src }}" alt="{{ $alt }}">
    @else
        {{ $initials }}
    @endif
</span>
