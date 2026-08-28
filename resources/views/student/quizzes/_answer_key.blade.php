@php
    $answersByQuestionId = $latestAttempt ? $latestAttempt->answers->keyBy('question_id') : collect();
@endphp

<x-ui.card
    class="mb-4"
    dusk="quiz-answer-key"
    title="Gabarito"
    :subtitle="$latestAttempt && $latestAttempt->completed_at ? 'Referente à sua tentativa corrigida em ' . $latestAttempt->completed_at->format('d/m/Y') . '.' : null"
    surface="body"
>
    <div class="d-flex flex-column gap-4">
        @foreach($quiz->questions as $index => $question)
            @php
                $answer = $answersByQuestionId->get($question->id);
            @endphp
            <div dusk="answer-key-question-{{ $question->id }}">
                <h4 class="h6 fw-bold mb-2">
                    {{ $index + 1 }}. {{ $question->question_text }}
                </h4>

                @if($question->type === 'essay')
                    <div class="mt-2">
                        @if($answer?->is_correct === true)
                            <x-ui.badge variant="success" :dot="false">Correta</x-ui.badge>
                        @elseif($answer?->is_correct === false)
                            <x-ui.badge variant="neutral" :dot="false">Incorreta</x-ui.badge>
                        @else
                            <x-ui.badge variant="outline" :dot="false">Aguardando correção</x-ui.badge>
                        @endif

                        @if($answer?->essay_answer)
                            <div class="mt-2 p-3 bg-body-secondary rounded-3 text-body small text-prewrap">
                                <strong>Sua resposta:</strong> {{ $answer->essay_answer }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="d-flex flex-column gap-2 mt-2">
                        @foreach($question->options as $option)
                            <div
                                @class([
                                    'd-flex align-items-center gap-2',
                                    'quiz-correct-option' => $option->is_correct,
                                    'quiz-incorrect-option' => ! $option->is_correct,
                                ])
                                dusk="answer-key-option-{{ $option->id }}"
                            >
                                @if($option->is_correct)
                                    <x-ui.icon name="check" size="16" class="text-success flex-shrink-0" />
                                @endif

                                <span class="{{ $option->is_correct ? 'fw-bold' : '' }}">
                                    {{ $option->option_text }}
                                </span>

                                @if($option->is_correct)
                                    <span class="ms-auto ds-caption text-success fw-semibold">
                                        (resposta correta)
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-ui.card>
