@php
    /** @var \App\Models\Module $module */
@endphp

<x-ui.field-stack class="max-w-560">
    <x-ui.input
        name="title"
        label="Título"
        required
        value="{{ $module->title }}"
    />

    <x-ui.input
        type="textarea"
        name="description"
        label="Descrição"
        value="{{ $module->description }}"
    />
</x-ui.field-stack>
