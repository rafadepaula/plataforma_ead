# **25. Fila de Correção de Dissertativas e Painel de Avaliação (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a fila de pendências (`quizzes/attempts/pending.blade.php`) e a tela de correção manual de redações/dissertativas (`quizzes/attempts/show.blade.php`) com o padrão Material Bootstrap: ordenação estrita FIFO (`completed_at ASC`), coluna de leitura de 760px, resposta do aluno em superfície afundada (`--surface-sunken`), vereditos em pares de cartões rádio (*Correta* em menta e *Incorreta* em slate neutro, sem vermelho), barra dinâmica de progresso com chip *"Pronto para salvar"* e validação com foco na primeira pendência.
* **Roles Cobertas:** `role:admin`, `role:gestor`.
* **Referência de Design:** `DESIGN.md` §4.6, `_ds/Correcao de dissertativas - Anatomia.dc.html`.

---

## **2. Estrutura de UI & Telas do Módulo**

### 2.1 Fila de Pendências (`quizzes/attempts/pending.blade.php`)
- **Cabeçalho:** Kicker `"Avaliações"`, Título `"Correções pendentes"`, Subtítulo: *"Tentativas com questões dissertativas aguardando correção manual, da mais antiga para a mais recente."*, Chip no cabeçalho com contagem `"{N} na fila"`.
- **Tabela de Pendências (`.ds-table-wrap`):**
  1. `Aluno`: Avatar 36px com iniciais + Nome.
  2. `Curso / Prova`: Título do curso (peso 600) + título da prova em caption.
  3. `Enviado em`: Data `dd/mm/aaaa hh:mm` com `tabular-nums` + tempo relativo em caption (*"há 2 dias"*, *"ontem"*).
  4. `Ações`: Botão tonal sm *"Corrigir"* (`dusk="grade-attempt-{{ $attempt->id }}"`).
- **Estado Vazio (`dusk="pending-attempts-empty"`):** Ícone de check menta em círculo pastel, título *"Nenhuma correção pendente"*, descrição explicativa.

### 2.2 Painel de Correção (`quizzes/attempts/show.blade.php`)
- Coluna de leitura única centralizada (max 760px).
- **Cabeçalho:** Breadcrumb `Avaliações / Correções pendentes / {Aluno}`, Kicker `"Corrigir tentativa · {Curso}"`, Título `{Nome do Aluno}`, Subtítulo: *"Avalie cada questão dissertativa como correta ou incorreta antes de salvar a correção."*, Ação: Botão tonal *"Voltar à fila"* (`dusk="back-to-pending"`).
- **Barra Dinâmica de Progresso de Avaliação:** Exibe contagem de vereditos dados sobre o total de dissertativas (`"2 de 4 vereditos"`). Ao atingir 100%, exibe chip menta `.ds-chip-success` *"Pronto para salvar"*.
- **Blocos de Questão:**
  1. **Objetivas Já Corrigidas (Contexto de Leitura):** Caption *"Questão N"*, enunciado, texto *"Corrigida automaticamente"* + chip menta *"Correta"* ou neutro *"Incorreta"*.
  2. **Dissertativas:**
     - **Superfície de Resposta (`AnswerSurface`):** Fundo afundado `--surface-sunken`, borda suave `--divider`, padding 28px, `white-space: pre-wrap` preservando quebras, sem rolagem interna forçada, texto do aluno rigorosamente escapado (`{{ $answer->essay_answer }}`). Se vazio: *"O aluno não respondeu esta questão."* em itálico.
     - **Veredito (`VerdictChoice`):** Dois cartões rádio lado a lado (altura mínima 56px, `required`):
       - *"Correta"* (`value="1"`): selecionado ganha fundo menta `--mint-100` e borda `--secondary`.
       - *"Incorreta"* (`value="0"`): selecionado ganha fundo `--surface-alt` e borda `--grey-500` (**nunca vermelho**).
- **Barra de Salvamento:** Botão primário *"Salvar correção"* (`dusk="grade-attempt-submit"`). Se clicado com vereditos pendentes, emite alerta informativo no topo, move o foco para a primeira questão pendente e aplica contorno `--critical`.

---

## **3. Regras de Negócio & Fluxos AJAX**

- **Processamento Atômico de Vereditos:** Form submete array `grades[{i}][answer_id]` e `grades[{i}][is_correct]`.
- **Recálculo de Nota & Transição de Estado:** A action backend recalcula o `score_percentage` total da tentativa e transiciona o status de `awaiting_manual_grading` para `graded`, disparando verificação de aprovação e emissão de certificado se aplicável.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="pending-attempt-row-{attempt_id}"`: Linha da tentativa na fila.
* `dusk="grade-attempt-{attempt_id}"`: Botão "Corrigir" da tentativa.
* `dusk="pending-attempts-empty"`: Estado vazio da fila.
* `dusk="back-to-pending"`: Botão voltar para a fila.
* `dusk="grade-attempt-form"`: Formulário de submissão de vereditos.
* `dusk="essay-answer-{question_id}"`: Superfície da resposta do aluno.
* `dusk="grade-correct-{answer_id}"`: Radio de veredito "Correta".
* `dusk="grade-incorrect-{answer_id}"`: Radio de veredito "Incorreta".
* `dusk="grade-attempt-submit"`: Botão salvar correção.

---

## **5. Checklist de Implementação & Testes**

- [ ] Views `quizzes/attempts/pending.blade.php` e `show.blade.php` refatoradas no padrão Material Bootstrap.
- [ ] Superfície de resposta do aluno com formatação `pre-wrap` segura.
- [ ] Veredito rádio estilizado (menta/slate) e barra dinâmica de progresso de correção.
- [ ] Teste Feature: `EssayGradingTest.php` cobrindo fila FIFO, obrigatoriedade de vereditos e recálculo de nota.
- [ ] Teste Dusk: `EssayGradingUiTest.php` cobrindo visualização da resposta, seleção de vereditos e salvamento.
