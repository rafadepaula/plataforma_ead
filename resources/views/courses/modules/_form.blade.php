@php
    /** @var \App\Models\Module $module */
@endphp

<div style="display: flex; flex-direction: column; gap: 20px; max-width: 560px;">
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
</div>
