@php
    /** @var \App\Models\Module $module */
@endphp

<div class="max-w-560">
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
