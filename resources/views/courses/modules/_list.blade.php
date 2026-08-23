@php
    /**
     * @var \App\Models\Course $course
     * @var \Illuminate\Support\Collection<int, \App\Models\Module> $modules
     */
    // `lessons_count` vem do `withCount` do ModuleController; o fallback
    // `$module->lessons` mantém o chip correto mesmo sem eager count.
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
                <x-ui.button variant="tonal" href="{{ route('modules.lessons.index', $module) }}" dusk="manage-lessons-{{ $module->id }}">Lições</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('modules.edit', $module) }}" dusk="edit-module-{{ $module->id }}">Editar</x-ui.button>

                <x-ui.button variant="ghost"
                             size="sm"
                             icon="trash"
                             data-bs-toggle="modal"
                             data-bs-target="#delete-module-modal-{{ $module->id }}"
                             aria-label="Remover módulo {{ $module->title }}" />
            </x-slot:actions>
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
