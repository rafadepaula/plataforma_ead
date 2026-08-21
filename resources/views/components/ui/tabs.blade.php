{{--
    x-ui.tabs — pílulas segmentadas sobre `.nav-pills`/`.tab-content` do
    Bootstrap 5.3. Não reimplementa a troca de painel: usa `data-bs-toggle="pill"`
    nativo (bootstrap.Tab), só fornece o markup e a temática do design system.

    Props:
      - items  array  cada item: ['id' => string, 'label' => string,
                       'content' => string|Illuminate\Support\HtmlString (HTML
                       confiável já renderizado pelo chamador — impresso com
                       `{!! !!}`, não escapado; nunca passar dado de usuário
                       cru aqui), 'active' => bool (opcional)].
                       Sem `active` explícito, a primeira aba é a ativa.
      - id     string (null)  prefixo dos ids de aba/painel; gerado se omitido.

    Uso:
      <x-ui.tabs :items="[
          ['id' => 'overview', 'label' => 'Visão geral', 'content' => view('...')->render()],
          ['id' => 'details', 'label' => 'Detalhes', 'content' => view('...')->render()],
      ]" />
--}}
@props([
    'items' => [],
    'id' => null,
])

@php
    $groupId = $id ?? 'tabs-'.uniqid();
    $hasActive = collect($items)->contains(fn ($item) => $item['active'] ?? false);
@endphp

<div>
    <ul {{ $attributes->merge(['class' => 'nav nav-pills ds-tabs']) }} role="tablist">
        @foreach ($items as $index => $item)
            @php
                $tabId = $groupId.'-'.$item['id'];
                $isActive = $item['active'] ?? (! $hasActive && $index === 0);
            @endphp
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link {{ $isActive ? 'active' : '' }}"
                    id="{{ $tabId }}-tab"
                    data-bs-toggle="pill"
                    data-bs-target="#{{ $tabId }}-pane"
                    type="button"
                    role="tab"
                    aria-controls="{{ $tabId }}-pane"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                >
                    {{ $item['label'] }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content mt-3">
        @foreach ($items as $index => $item)
            @php
                $tabId = $groupId.'-'.$item['id'];
                $isActive = $item['active'] ?? (! $hasActive && $index === 0);
            @endphp
            <div
                class="tab-pane fade {{ $isActive ? 'show active' : '' }}"
                id="{{ $tabId }}-pane"
                role="tabpanel"
                aria-labelledby="{{ $tabId }}-tab"
                tabindex="0"
            >
                {!! $item['content'] ?? '' !!}
            </div>
        @endforeach
    </div>
</div>
