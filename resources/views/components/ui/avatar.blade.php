{{--
    x-ui.avatar — wrapper Blade sobre `.ds-avatar`/`.ds-avatar-lg`/`.ds-avatar-xl`,
    definidas em `resources/scss/components/_avatar.scss` (Fase 1) e NÃO
    duplicadas aqui. Aquelas classes já são consumidas diretamente por
    `x-layout.topbar` (app bar) e pelo drawer mobile — este componente é o
    caminho para qualquer outro lugar que precise do mesmo círculo de
    foto/iniciais (listas de usuários, cards de perfil), sem criar um segundo
    visual divergente.

    Props:
      - size     string ('md')  sm|md|lg|xl -> `.ds-avatar`|`.ds-avatar`|`.ds-avatar-lg`|`.ds-avatar-xl`.
                                 Mapa de tamanhos deliberado (NÃO renomear sem
                                 migrar todos os call sites): "sm"/"md" caem na
                                 `.ds-avatar` base (32px), "lg" em 44px e "xl"
                                 em 64px. Os três portes do design system são
                                 32/44/64; `.ds-avatar` base é usada crua pela
                                 app bar e pelo drawer mobile, então os valores
                                 em px não podem ser remapeados aqui — quem
                                 precisa do círculo de 44px (ex.: autor de
                                 tópico/resposta no fórum) pede `size="lg"`.
      - name     string (null)  nome completo; as iniciais (até 2 letras,
                                 maiúsculas) são derivadas no servidor a partir
                                 das duas primeiras palavras. Ignorado quando
                                 `initials` é passada.
                                 Para um `User` prefira SEMPRE
                                 `:initials="$user->initials"`: aquele accessor
                                 é a fonte única das iniciais e é o mesmo valor
                                 que o endpoint de polling do fórum devolve —
                                 derivar de novo aqui abriria espaço para as
                                 duas implementações divergirem. Esta prop
                                 existe para nomes que não vêm de um `User`
                                 (texto fixo, agregações, dados de relatório).
      - initials string (null)  iniciais exibidas quando não há imagem.
      - src      string (null)  atalho para renderizar `<img src="$src">`.
      - alt      string ('')    texto alternativo da imagem.

    Uso:
      <x-ui.avatar initials="AB" />
      <x-ui.avatar :initials="$user->initials" size="lg" />
      <x-ui.avatar size="lg" :src="$user->avatar_url" :alt="$user->name" />
      <x-ui.avatar size="xl"><img src="{{ $user->avatar_url }}" alt=""></x-ui.avatar>
--}}
@props([
    'size' => 'md',
    'name' => null,
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

    $resolvedInitials = $initials;

    if ($resolvedInitials === null && is_string($name) && trim($name) !== '') {
        $nameParts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        $resolvedInitials = mb_strtoupper(
            collect($nameParts)->take(2)->map(fn (string $part): string => mb_substr($part, 0, 1))->implode('')
        );
    }
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($hasSlot)
        {{ $slot }}
    @elseif ($src)
        <img src="{{ $src }}" alt="{{ $alt }}">
    @else
        {{ $resolvedInitials }}
    @endif
</span>
