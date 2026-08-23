# **29. Realização de Provas pelo Aluno, Cronômetro ao Vivo e Avaliação Atômica (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a tela de realização de avaliação pelo aluno (`student/quizzes/show.blade.php`) com o padrão Material Bootstrap: coluna de leitura focada de 760px, cronômetro em tempo real `QuizTimer.js` no cabeçalho, faixa de avisos e gabarito, questões com cartões de clique amplo (rádio/checkbox de 22px), textarea dissertativa, diálogo de confirmação com aviso de questões não respondidas e submissão atômica única em `POST student.quizzes.submit`.
* **Roles Cobertas:** `role:aluno`, middleware `student.enrolled`.
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.10, `spec/new_ds/Fazer a prova - Anatomia.dc.html`.

---

## **2. Estrutura de UI & Hierarquia de Componentes (`student.quizzes.show`)**

```text
student/quizzes/show.blade.php (Coluna centrada max 760px)
├── x-layout.page-header
│   └── slot:actions -> span[data-quiz-timer] (Cronômetro ao vivo em cápsula tabular)
│
├── Faixa de Avisos (Ordem Estrita)
│   ├── Alert: Melhor nota anterior (se existir) [dusk="quiz-best-score"]
│   ├── Alert: Aguardando correção manual [dusk="quiz-pending-grading"]
│   ├── Card: Gabarito da tentativa anterior (se show_correct_answers = true) [dusk="quiz-answer-key"]
│   │
│   ├── SE NÃO pode tentar (! $canAttempt):
│   │   ├── Alert: Motivo do bloqueio / Tentativas esgotadas [dusk="quiz-cannot-attempt"]
│   │   └── Botão: "Voltar para a aula" [dusk="back-to-lesson"]
│   │
│   └── SE pode tentar ($canAttempt):
│       ├── Alert: Instruções do gestor ("Antes de começar")
│       └── form POST student.quizzes.submit [dusk="quiz-attempt-form"]
│           ├── @foreach questão [dusk="quiz-question-{id}"]
│           │   ├── Overline "Questão N de M" + Chip de tipo
│           │   ├── Enunciado (h3, 20px)
│           │   ├── [Objetivas] Opções em labels largos de 56px [dusk="quiz-option-{q}-{o}"]
│           │   └── [Dissertativa] Textarea 5 linhas [dusk="quiz-essay-{id}"]
│           └── x-ui.confirm-modal: Finalizar prova [dusk="quiz-attempt-submit"]
```

---

## **3. Regras de Negócio e Contratos de Tempo (`QuizTimer.js`)**

### 3.1 Contrato Estrito do Cronômetro
- **Elemento Único sem Filhos:** `QuizTimer.js` sobrescreve diretamente `container.textContent` a cada segundo. Ícones devem ser **irmãos** fora do `<span>`.
- **Atributos no Nó:** `data-quiz-timer`, `data-started-at="{{ now()->toIso8601String() }}"`, `data-time-limit-minutes="{{ $quiz->time_limit_minutes }}"`, `dusk="quiz-timer"`.
- **Comportamento ao Expirar:** Exibe texto *"Tempo esgotado"* e classe `.ds-tone-attention`. O formulário **não** é bloqueado no cliente (o aluno pode submeter normalmente; o backend aceita e força `is_passed = false` por estouro de tempo).

### 3.2 Opções de Resposta & Submissão Atômica
- **Alvos Amplos:** Cada opção é um card/label clicável com altura mínima de 56px, raio 14px, anel de foco azul e controle rádio/checkbox de 22px. Name: `answers[{question_id}][selected_option_ids][]`.
- **Dissertativa:** Textarea de 5 linhas com hint explicativo de correção manual. Name: `answers[{question_id}][essay_answer]`.
- **Modal de Confirmação:** O acionamento de *"Finalizar prova"* abre `ConfirmModal` informando quantas questões ficaram sem resposta antes de confirmar o envio irreversível.
- **Submissão Única:** A prova inteira é enviada num único `POST`. Não existe persistência de rascunho intermediário.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="quiz-timer"`: Span do cronômetro em contagem regressiva.
* `dusk="quiz-best-score"`: Alerta com a melhor nota obtida.
* `dusk="quiz-pending-grading"`: Alerta indicando prova aguardando correção manual.
* `dusk="quiz-cannot-attempt"`: Alerta indicando bloqueio/tentativas esgotadas.
* `dusk="back-to-lesson"`: Botão retornar para a aula.
* `dusk="quiz-attempt-form"`: Formulário da tentativa.
* `dusk="quiz-question-{id}"`: Bloco da questão.
* `dusk="quiz-option-{q}-{o}"`: Input da opção {o} na questão {q}.
* `dusk="quiz-essay-{id}"`: Textarea da questão dissertativa.
* `dusk="quiz-attempt-submit"`: Botão de finalizar/enviar prova.
* `dusk="quiz-answer-key"`: Card de exibição do gabarito.

---

## **5. Checklist de Implementação & Testes**

- [ ] View `resources/views/student/quizzes/show.blade.php` refatorada no padrão Material Bootstrap.
- [ ] Módulo JS `QuizTimer.js` com contagem regressiva e aviso de tempo esgotado.
- [ ] Card de opções de clique amplo e textarea dissertativa.
- [ ] Diálogo de confirmação com contagem de pendências.
- [ ] Teste Feature: `StudentQuizControllerTest.php` e `SubmitQuizAttemptActionTest.php`.
- [ ] Teste Dusk: `StudentQuizTakingDuskTest.php` cobrindo timer, preenchimento, confirmação e gabarito.
