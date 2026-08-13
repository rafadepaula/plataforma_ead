@php
    /** @var \App\Models\Course $course */
@endphp

<div class="max-w-560">
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

    <div class="form-check mb-3">
        <input type="checkbox" id="is_published" name="is_published" value="1"
               @checked(old('is_published', $course->is_published))
               class="form-check-input" />
        <label for="is_published" class="form-check-label fw-semibold">Publicado</label>
    </div>
</div>
