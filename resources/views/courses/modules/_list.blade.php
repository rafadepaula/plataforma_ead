@php
    /**
     * @var \App\Models\Course $course
     * @var \Illuminate\Support\Collection<int, \App\Models\Module> $modules
     */
@endphp

<ul data-reorder-url="{{ route('modules.reorder', $course) }}"
    dusk="module-list"
    class="list-group list-unstyled m-0 p-0 d-flex flex-column gap-2">
    @forelse($modules as $module)
        <li data-id="{{ $module->id }}"
            dusk="module-row-{{ $module->id }}"
            draggable="true"
            class="list-group-item sortable-item d-flex align-items-center justify-content-between gap-3">
            <span class="d-flex align-items-center gap-2">
                <span aria-hidden="true" class="drag-handle">⠿</span>
                {{ $module->title }}
            </span>

            <span class="d-flex gap-2">
                <x-ui.button variant="secondary" size="sm" href="{{ route('modules.lessons.index', $module) }}" dusk="manage-lessons-{{ $module->id }}">Lições</x-ui.button>
                <x-ui.button variant="secondary" size="sm" href="{{ route('modules.edit', $module) }}" dusk="edit-module-{{ $module->id }}">Editar</x-ui.button>

                <form method="POST" action="{{ route('modules.destroy', $module) }}" dusk="delete-module-form-{{ $module->id }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" size="sm" dusk="delete-module-{{ $module->id }}">Remover</x-ui.button>
                </form>
            </span>
        </li>
    @empty
        <li class="list-group-item border-dashed text-center text-body-secondary py-4">
            Nenhum Módulo cadastrado.
        </li>
    @endforelse
</ul>
