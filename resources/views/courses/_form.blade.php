@php
    /** @var \App\Models\Course $course */
@endphp

<x-ui.field-stack class="max-w-560">
    <x-ui.input
        name="title"
        label="Título"
        required
        value="{{ $course->title }}"
    />

    <x-ui.input
        type="textarea"
        name="description"
        label="Descrição"
        value="{{ $course->description }}"
    />

    <x-ui.input
        type="number"
        name="workload_hours"
        label="Carga Horária (horas)"
        required
        value="{{ $course->workload_hours }}"
    />

    <x-ui.switch
        name="is_published"
        label="Publicado"
        :checked="$course->is_published"
    />
</x-ui.field-stack>
