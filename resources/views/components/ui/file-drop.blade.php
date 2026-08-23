{{--
    Zona de upload multi-arquivo (borda tracejada, ícone `upload` 28px) com
    seleção manual (clique/Enter no label) e drag-and-drop; a interação
    cliente vive em `LessonForm.js` via `[data-file-drop]`:

    - valida o tamanho por arquivo contra `data-max-size` (bytes) antes do
      submit, marcando `.is-invalid` na zona e escrevendo no
      `.invalid-feedback` existente (convenção de validação — nunca cria
      markup de erro novo);
    - lista os arquivos escolhidos (nome truncado, tamanho KB/MB, barra de
      progresso animada durante o POST, remoção individual).

    `attachments` recebe os anexos JÁ PERSISTIDOS (modelos `LessonMedia` ou
    arrays — o acesso é por índice, compatível com ambos): eles nascem no
    servidor com o botão de remoção `dusk="remove-file-{id}"`; a remoção só
    esconde a linha no cliente e soma um `removed_media[]` ao form, o
    servidor é quem apaga o registro e o arquivo.

    O `<input type="file">` fica `visually-hidden` dentro do label-zona (não
    `d-none`): o attach do WebDriver e o leitor de tela continuam alcançando
    o campo de verdade.
--}}
@props([
    'name',
    'label',
    'accept' => null,
    'maxSize' => null,
    'hint' => null,
    'attachments' => null,
])

@php
    $inputId = $attributes->get('id', str_replace(['[', ']'], '-', $name).'-input');
    $maxSizeBytes = $maxSize !== null ? (int) round(((float) $maxSize) * 1024) : null;
    $hasError = isset($errors) && collect($errors->keys())
        ->contains(fn (string $key): bool => str_starts_with($key, $name));
    $firstErrorKey = $hasError
        ? collect($errors->keys())->first(fn (string $key): bool => str_starts_with($key, $name))
        : null;

    $formatSize = static function (?int $bytes): ?string {
        if ($bytes === null) {
            return null;
        }

        return $bytes >= 1024 * 1024
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)).' MB'
            : str_replace('.', ',', (string) round($bytes / 1024, 1)).' KB';
    };
@endphp

<div class="ds-field mb-3" @if($maxSizeBytes !== null) data-max-size="{{ $maxSizeBytes }}" @endif data-file-drop>
    <span class="form-label fw-semibold d-block">{{ $label }}</span>

    <label for="{{ $inputId }}"
           data-file-drop-zone
           class="ds-file-drop d-flex flex-column align-items-center justify-content-center text-center gap-2 p-4x border border-dashed rounded{{ $hasError ? ' is-invalid' : '' }}">
        <x-ui.icon name="upload" size="28" aria-hidden="true" class="text-body-secondary" />
        <span class="fw-semibold">Arraste os arquivos aqui</span>
        <span class="form-text mt-0">
            ou clique para selecionar{{ $maxSize !== null ? ' — até '.$maxSize.' MB por arquivo' : '' }}{{ $hint ? ' · '.$hint : '' }}
        </span>

        <input type="file"
               id="{{ $inputId }}"
               name="{{ $name }}[]"
               multiple
               @if($accept) accept="{{ $accept }}" @endif
               @if($hasError) aria-invalid="true" @endif
               aria-label="{{ $label }}"
               {{ $attributes->merge(['class' => 'visually-hidden']) }} />
    </label>

    @if($hasError)
        <div class="invalid-feedback d-flex align-items-center gap-1" dusk="error-{{ $name }}">
            <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
            <span>{{ $errors->first($firstErrorKey) }}</span>
        </div>
    @endif

    <ul class="list-unstyled d-flex flex-column gap-2 mt-2 mb-0" data-file-list aria-label="Anexos de {{ $label }}">
        @forelse(($attachments ?? []) as $attachment)
            <li data-file-item
                data-attachment-id="{{ $attachment['id'] }}"
                class="d-flex align-items-center gap-2 border rounded p-2">
                <x-ui.icon :name="($attachment['kind'] ?? '') === 'pdf' ? 'file-text' : 'upload'" size="18" aria-hidden="true" class="flex-shrink-0" />
                <span class="text-truncate">{{ $attachment['original_name'] ?? basename((string) $attachment['path']) }}</span>
                @if($formatSize($attachment['size_bytes'] ?? null))
                    <span class="form-text mt-0 flex-shrink-0">{{ $formatSize($attachment['size_bytes']) }}</span>
                @endif

                <button type="button"
                        class="btn btn-ghost btn-sm ms-auto"
                        data-remove-file
                        dusk="remove-file-{{ $attachment['id'] }}"
                        aria-label="Remover anexo {{ $attachment['original_name'] ?? $attachment['path'] }}">
                    <x-ui.icon name="x" size="16" aria-hidden="true" />
                </button>
            </li>
        @empty
        @endforelse
    </ul>
</div>
