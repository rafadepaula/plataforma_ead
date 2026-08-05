@php
    /** @var \App\Models\Course $course */
@endphp

<div style="display: flex; flex-direction: column; gap: 20px; max-width: 560px;">
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

    <div class="field" style="display: flex; align-items: center; gap: 8px;">
        <input type="checkbox" id="is_published" name="is_published" value="1"
               @checked(old('is_published', $course->is_published))
               style="width: 16px; height: 16px;" />
        <label for="is_published" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Publicado</label>
    </div>
</div>
