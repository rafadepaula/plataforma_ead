<x-ui.input
    type="textarea"
    name="answers[{{ $question->id }}][essay_answer]"
    label="Sua resposta"
    rows="5"
    :value="$essayValue ?? ''"
    hint="Esta questão é corrigida pelo responsável do curso, depois do envio."
    dusk="quiz-essay-{{ $question->id }}"
/>
