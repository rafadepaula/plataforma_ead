@php
    /**
     * @var \App\Models\Course $course
     * @var \Illuminate\Support\Collection<int, \App\Models\Module> $modules
     */
    // `lessons_count` vem do `withCount` do ModuleController; o fallback
    // `$module->lessons` mantém o chip correto mesmo sem eager count. As
    // lições vêm eager-loadadas ordenadas por `order_index` (mesmo
    // controller), então os sub-itens não custam N+1.
    $lessonCount = static fn (\App\Models\Module $module): int => (int) ($module->lessons_count ?? $module->lessons->count());
@endphp

<x-ui.sortable-list :reorder-url="route('modules.reorder', $course)" dusk="module-list">
    @forelse($modules as $module)
        <x-ui.sortable-row :id="$module->id" :title="$module->title" dusk="module-row-{{ $module->id }}">
            <x-slot:chips>
                <span class="ds-chip ds-chip-outline ds-chip-plain">
                    {{ $lessonCount($module) === 1 ? '1 lição' : $lessonCount($module).' lições' }}
                </span>
            </x-slot:chips>

            <x-slot:actions>
                <x-ui.button variant="tonal" href="{{ route('modules.lessons.create', $module) }}" dusk="create-lesson-{{ $module->id }}">Cadastrar lição</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('modules.edit', $module) }}" dusk="edit-module-{{ $module->id }}">Editar</x-ui.button>

                <x-ui.button variant="ghost"
                             size="sm"
                             icon="trash"
                             data-bs-toggle="modal"
                             data-bs-target="#delete-module-modal-{{ $module->id }}"
                             aria-label="Remover módulo {{ $module->title }}" />
            </x-slot:actions>

            {{-- Sub-itens: sem `data-id` (contrato do ModuleReorder.js) e dentro do `<li>` para o drag carregar tudo. --}}
            <div id="module-lessons-{{ $module->id }}" dusk="module-lessons-{{ $module->id }}">
                <ul class="list-unstyled m-0 ps-4 d-flex flex-column gap-2 border-top pt-3">
                    <li>
                        <a href="{{ route('modules.lessons.index', $module) }}" dusk="manage-lessons-{{ $module->id }}" class="small text-decoration-none">
                            Gerenciar lições
                        </a>
                    </li>
                    @forelse($module->lessons as $lesson)
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1" dusk="lesson-subitem-{{ $lesson->id }}">
                            <span class="d-flex align-items-center gap-2 min-w-0">
                                <span class="text-truncate @if(! $lesson->is_published) text-body-secondary @endif">{{ $lesson->title }}</span>

                                @if($lesson->type === 'quiz')
                                    <span class="ds-chip ds-chip-primary ds-chip-plain">Quiz</span>
                                @else
                                    <span class="ds-chip ds-chip-outline ds-chip-plain">Conteúdo</span>
                                @endif

                                @unless($lesson->is_published)
                                    <span class="ds-chip ds-chip-plain ds-tone-neutral">Não publicada</span>
                                @endunless
                            </span>

                            <x-ui.button variant="ghost" size="sm" href="{{ route('lessons.edit', $lesson) }}" dusk="edit-lesson-{{ $lesson->id }}">Editar</x-ui.button>
                        </li>
                    @empty
                        <li class="small text-body-secondary py-1">
                            Nenhuma lição neste módulo ainda.
                        </li>
                    @endforelse
                </ul>
            </div>
        </x-ui.sortable-row>
    @empty
        <li class="list-group-item border-dashed text-center text-body-secondary py-4">
            Nenhum Módulo cadastrado.
        </li>
    @endforelse
</x-ui.sortable-list>

{{-- Modais fora da lista: arrastar um `<li>` não pode carregar o backdrop junto. --}}
@foreach($modules as $module)
    @php
        $count = $lessonCount($module);
        $cascadeMessage = match (true) {
            $count === 0 => 'Este módulo não tem lições. Esta ação não poderá ser desfeita.',
            $count === 1 => 'A 1 lição deste módulo também será removida. Esta ação não poderá ser desfeita.',
            default => 'As '.$count.' lições deste módulo também serão removidas. Esta ação não poderá ser desfeita.',
        };
    @endphp

    <x-ui.confirm-modal id="delete-module-modal-{{ $module->id }}"
                        title="Remover módulo"
                        :action="route('modules.destroy', $module)"
                        method="DELETE"
                        confirm-label="Remover módulo"
                        :message="$cascadeMessage"
                        form-dusk="delete-module-form-{{ $module->id }}"
                        confirm-dusk="delete-module-{{ $module->id }}" />
@endforeach
