@php
    /**
     * @var \App\Models\Course $course
     * @var \Illuminate\Support\Collection<int, \App\Models\Module> $modules
     */
@endphp

<ul data-reorder-url="{{ route('modules.reorder', $course) }}"
    dusk="module-list"
    style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
    @forelse($modules as $module)
        <li data-id="{{ $module->id }}"
            dusk="module-row-{{ $module->id }}"
            draggable="true"
            style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); cursor: grab;">
            <span style="display: flex; align-items: center; gap: 10px;">
                <span aria-hidden="true" style="opacity: 0.5;">⠿</span>
                {{ $module->title }}
            </span>

            <span style="display: flex; gap: 8px;">
                <x-ui.button variant="secondary" size="sm" href="{{ route('modules.lessons.index', $module) }}" dusk="manage-lessons-{{ $module->id }}">Lições</x-ui.button>
                <x-ui.button variant="secondary" size="sm" href="{{ route('modules.edit', $module) }}" dusk="edit-module-{{ $module->id }}">Editar</x-ui.button>

                <form method="POST" action="{{ route('modules.destroy', $module) }}" dusk="delete-module-form-{{ $module->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost" dusk="delete-module-{{ $module->id }}">Remover</button>
                </form>
            </span>
        </li>
    @empty
        <li style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600); border: 1px dashed var(--color-divider);">
            Nenhum Módulo cadastrado.
        </li>
    @endforelse
</ul>
