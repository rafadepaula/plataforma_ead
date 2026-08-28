@php
    $isSelected = in_array($option->id, (array) ($selected ?? []));
    $inputType = $question->type === 'multiple_choice' ? 'checkbox' : 'radio';
@endphp

<label
    @class([
        'quiz-option-card d-flex align-items-center gap-3 p-3 border rounded-3 cursor-pointer transition-base',
        'bg-body' => ! $isSelected,
        'bg-body-secondary border-primary border-2 shadow-sm' => $isSelected,
    ])
    data-quiz-option
>
    <input
        type="{{ $inputType }}"
        name="answers[{{ $question->id }}][selected_option_ids][]"
        value="{{ $option->id }}"
        @checked($isSelected)
        class="form-check-input flex-shrink-0 mt-0"
        dusk="quiz-option-{{ $question->id }}-{{ $option->id }}"
    />
    <span class="flex-grow-1 text-body {{ $isSelected ? 'fw-semibold' : '' }}">
        {{ $option->option_text }}
    </span>
</label>
