@php
    /**
     * One option row of a question block in the lesson-embedded quiz
     * builder (see `_quiz-question.blade.php` for the payload contract).
     *
     * @var int|string $qIndex
     * @var int|string $oIndex  '__OI__' inside the option template.
     * @var array{id?: int|string|null, option_text?: string, is_correct?: bool} $option
     * @var bool $isTrueFalse   readonly text (Verdadeiro/Falso), no remove.
     * @var string $trueFalseText  pre-filled text for blank true_false rows.
     */
    $optionId = $option['id'] ?? '';
    $optionText = $option['option_text'] ?? ($trueFalseText ?? '');
    $isCorrect = (bool) ($option['is_correct'] ?? false);
@endphp

<div class="d-flex align-items-center gap-2 quiz-option-row{{ $isCorrect ? ' is-correct' : '' }}" data-option-row>
    @if($optionId !== '')
        <input type="hidden" name="questions[{{ $qIndex }}][options][{{ $oIndex }}][id]" value="{{ $optionId }}" />
    @endif
    <input type="checkbox" name="questions[{{ $qIndex }}][options][{{ $oIndex }}][is_correct]" value="1"
           @checked($isCorrect) data-correct-checkbox
           class="form-check-input flex-shrink-0 m-0" />
    <input type="text" name="questions[{{ $qIndex }}][options][{{ $oIndex }}][option_text]" value="{{ $optionText }}"
           {{ $isTrueFalse ? 'readonly' : '' }}
           placeholder="Texto da opção"
           class="form-control form-control-sm flex-fill" />
    @unless($isTrueFalse)
        <x-ui.button type="button" variant="ghost" data-remove-option dusk="remove-lesson-option">✕</x-ui.button>
    @endunless
</div>
