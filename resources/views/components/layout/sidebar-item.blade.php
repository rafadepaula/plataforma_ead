{{--
    x-layout.sidebar-item — um item do menu lateral com seus subitens
    (children) sempre visíveis abaixo dele. Compartilhado entre o `<aside>`
    desktop (`.sidebar-item`) e o Offcanvas mobile (`.mobile-sidebar-item`):
    `:mobile` troca a classe base e o sufixo `-mobile` dos dusk selectors,
    garantindo que os dois renders nunca divergam.

    Props:
      - item   array  item resolvido por `NavigationService::resolve()`:
                       {key, label, url, active, badge, icon, section,
                       childrenOnly, children}, onde cada child é
                       {key, label, url, active, progress, is_course,
                       lessons_completed, lessons_total, forum_url,
                       certificate_url}. `childrenOnly` marca um item
                       puramente agrupador ("Meus Cursos"): a âncora do
                       pai nunca renderiza e os filhos são a seção toda.
                       Child de curso (`is_course`) renderiza o bloco
                       rico — barra + %, contagem de aulas, fórum e
                       certificado; child sintético ("Ver todos") fica no
                       markup simples de sempre (progress `null` = sem barra).
      - mobile bool   (false) render mobile — classe `.mobile-sidebar-item`
                       e dusk selectors com sufixo `-mobile`.
--}}
@props([
    'item',
    'mobile' => false,
])

@php
    $itemClass = $mobile ? 'mobile-sidebar-item' : 'sidebar-item';
    $duskSuffix = $mobile ? '-mobile' : '';
@endphp

@if(! ($item['childrenOnly'] ?? false))
    <a href="{{ $item['url'] }}"
       dusk="sidebar-{{ $item['key'] }}-link{{ $duskSuffix }}"
       @if($item['active']) aria-current="page" @endif
       class="{{ $itemClass }} {{ $item['active'] ? 'active' : '' }}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
        <span>{{ $item['label'] }}</span>
        @if($item['badge'] !== null)
            <span class="sidebar-badge">
                {{ $item['badge'] }}
            </span>
        @endif
    </a>
@endif

@if($item['children'] !== [])
    <div class="sidebar-children">
        @foreach($item['children'] as $child)
            @if($child['is_course'] ?? false)
                {{-- Bloco rico do curso : o cabeçalho clicável
                     (título + barra + % + contagem) leva à sala de aula;
                     fórum e certificado ficam FORA da âncora — conteúdo
                     interativo não aninha em `<a>`. --}}
                <div class="sidebar-course {{ $child['active'] ? 'active' : '' }}">
                    <a href="{{ $child['url'] }}"
                       dusk="sidebar-{{ $child['key'] }}-link{{ $duskSuffix }}"
                       @if($child['active']) aria-current="page" @endif
                       class="sidebar-course-link {{ $child['active'] ? 'active' : '' }}">
                        <span class="d-block text-truncate">{{ $child['label'] }}</span>
                        <span class="sidebar-course-progress">
                            <x-ui.progress :value="$child['progress']" :height="4"
                                           :label="'Progresso: '.$child['label']" />
                            <span class="ds-tabular-nums sidebar-course-progress-pct">
                                {{ $child['progress'] }}%
                            </span>
                        </span>
                        <span class="ds-caption text-body-secondary">
                            {{ $child['lessons_completed'] }}/{{ $child['lessons_total'] }} aulas concluídas
                        </span>
                    </a>

                    <div class="sidebar-course-actions">
                        <a href="{{ $child['forum_url'] }}"
                           dusk="sidebar-{{ $child['key'] }}-forum{{ $duskSuffix }}"
                           class="sidebar-course-forum-link"
                           aria-label="Fórum de dúvidas do curso {{ $child['label'] }}">
                            Fórum de dúvidas ↗
                        </a>

                        {{-- Só renderiza com certificado emitido e não
                             revogado : nenhuma linha de estado
                             "pendente" no menu. --}}
                        @if($child['certificate_url'] !== null)
                            <x-ui.button variant="tonal" size="sm" block icon="award"
                                         :href="$child['certificate_url']"
                                         dusk="sidebar-{{ $child['key'] }}-certificate{{ $duskSuffix }}"
                                         aria-label="Certificado do curso {{ $child['label'] }}">
                                Certificado
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            @else
                <a href="{{ $child['url'] }}"
                   dusk="sidebar-{{ $child['key'] }}-link{{ $duskSuffix }}"
                   @if($child['active']) aria-current="page" @endif
                   class="sidebar-child-item {{ $child['active'] ? 'active' : '' }}">
                    <span class="d-block text-truncate">{{ $child['label'] }}</span>
                    @if($child['progress'] !== null)
                        <x-ui.progress :value="$child['progress']" :height="4" :label="'Progresso: '.$child['label']" />
                    @endif
                </a>
            @endif
        @endforeach
    </div>
@endif
