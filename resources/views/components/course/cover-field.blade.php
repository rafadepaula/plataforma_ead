{{--
    x-course.cover-field — capa do curso (upload único, 16:9).

    Renderizado somente na edição (`$course->exists`): a criação ainda não
    tem id para o caminho de armazenamento. A interação cliente vive em
    `CourseForm.js` via `[data-course-cover]`:

    - valida o tamanho contra `data-max-size` (bytes) antes do submit,
      marcando `.is-invalid` e escrevendo no `.invalid-feedback` existente
      (convenção de validação — nunca cria markup de erro novo);
    - atualiza a pré-visualização da capa (seletor course-cover-preview)
      ao vivo via object URL;
    - marcar "Remover capa atual" limpa o file input e a pré-visualização, e
      escolher um arquivo desmarca a remoção (o servidor decide o estado
      final a partir de `cover` / `remove_cover`).

    O `<input type="file">` fica `visually-hidden` dentro do label-zona (não
    `d-none`): o attach do WebDriver e o leitor de tela continuam alcançando
    o campo de verdade.
--}}
@props(['course'])

@php
    /** @var \App\Models\Course $course */
    $hasCover = (bool) ($course->cover_path ?? false);
    // URL montada no único lugar canônico: `Course::coverUrl()` (accessor `cover_url`).
    $coverUrl = $hasCover ? $course->cover_url : null;
    $hasError = isset($errors) && $errors->has('cover');
@endphp

<div {{ $attributes->merge(['class' => 'ds-field mb-3']) }} data-course-cover>
    <span class="form-label fw-semibold d-block">Capa do curso</span>

    <div data-course-cover-preview-wrap>
        @if ($hasCover)
            <img src="{{ $coverUrl }}"
                 alt="Capa atual do curso {{ $course->title }}"
                 class="img-fluid rounded border mb-2"
                 dusk="course-cover-preview"
                 data-course-cover-preview>
        @endif
    </div>

    <label for="course-cover-input"
           data-course-cover-zone
           class="ds-file-drop d-flex flex-column align-items-center justify-content-center text-center gap-2 p-4 border border-dashed rounded{{ $hasError ? ' is-invalid' : '' }}">
        <x-ui.icon name="upload" size="28" aria-hidden="true" class="text-body-secondary" />
        <span class="fw-semibold">Arraste a capa aqui</span>
        <span class="form-text mt-0">ou clique para selecionar</span>

        <input type="file"
               id="course-cover-input"
               name="cover"
               accept="image/jpeg,image/png,image/webp"
               data-max-size="2097152"
               aria-label="Capa do curso"
               @if ($hasError) aria-invalid="true" @endif
               dusk="course-cover-input"
               data-course-cover-input
               class="visually-hidden{{ $hasError ? ' is-invalid' : '' }}">
    </label>

    <div class="form-text">JPG, PNG ou WEBP · até 2 MB · 16:9 recomendado</div>

    @if ($hasCover)
        <div class="form-check mt-2">
            <input type="checkbox"
                   id="course-cover-remove"
                   name="remove_cover"
                   value="1"
                   dusk="course-cover-remove"
                   data-course-cover-remove
                   class="form-check-input">
            <label for="course-cover-remove" class="form-check-label">Remover capa atual</label>
        </div>
    @endif

    @error('cover')
        <div class="invalid-feedback d-flex align-items-center gap-1" dusk="error-cover">
            <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
            <span>{{ $message }}</span>
        </div>
    @enderror
</div>
