# 06 — Telas de avaliação

Cobertura: 6 views, 50 seletores `dusk`. Onda de **risco alto** — `QuizBuilder.js`
e `QuizTimer.js` dependem do DOM.

---

## 6.1 Autoria de prova — `quizzes/create` (8), `quizzes/edit` (9)

Duas colunas.

**Esquerda — regras da prova** (`x-ui.field-stack`, card `outlined`):
título, instruções, máximo de tentativas, limite de tempo, nota mínima,
"Permitir novas tentativas" e "Exibir gabarito" como `x-ui.switch`.
Os **hints atuais são preservados palavra por palavra** — nenhum texto de ajuda
é reescrito nesta rodada.

**Direita — questões**: lista arrastável (`data-reorder-url`, `data-id`),
tipo em chip `outline`, alça `grip-vertical`, ação de editar e remover.
Botão "Adicionar questão" (primary, `plus`) abre o diálogo.

Fluxo preservado: criar a prova primeiro, gerenciar questões depois
(redirect atual mantido).

---

## 6.2 Diálogo de questão — `quizzes/partials/_question-form.blade.php` (11 dusk)

`x-ui.modal` de 28px sobre scrim de 42%:

- Enunciado (`x-ui.textarea`).
- Tipo — os **quatro** valores do backend, sem adição nem renomeação:
  única escolha, múltipla escolha, verdadeiro ou falso, dissertativa.
- Opções: linha com `x-ui.input` + `x-ui.checkbox` "Correta" + botão remover
  (só-ícone, `aria-label`). Botão "Adicionar opção" ghost com borda.
- **A opção correta é marcada por checkbox, não por cor** — cor nunca é o
  único sinal.
- Tipo dissertativa esconde o bloco de opções (comportamento atual do
  `QuizBuilder.js`); única escolha e verdadeiro/falso forçam uma só correta.

`QuizBuilder.js` só muda onde a casca alterou o seletor interno. Migrar CSS
primeiro, JS depois, com Dusk entre os dois passos.

---

## 6.3 Lista de questões — `quizzes/partials/_question-list.blade.php` (6 dusk)

Uma linha por questão em `--surface-sunken`, raio 14px: número em overline,
enunciado truncado em duas linhas, tipo em chip `outline`, contagem de opções
em caption, ações à direita.

---

## 6.4 Fila de correção — `quizzes/attempts/pending.blade.php` (3 dusk)

`x-ui.data-table`: aluno (avatar + e-mail), prova, curso, data
`dd/mm/aaaa hh:mm`, ação **Corrigir** (tonal).
Estado vazio: "Nenhuma redação aguardando correção" + "As dissertativas
enviadas pelos Alunos aparecem aqui."

Título da tela: *Acompanhamento / Redações pendentes*. Sem tom de urgência.

---

## 6.5 Correção — `quizzes/attempts/show.blade.php` (6 dusk)

Coluna de leitura de 760px.

- Cabeçalho: aluno, prova, data de envio, tentativa nº.
- Por dissertativa: enunciado em h4, **resposta do Aluno em
  `--surface-sunken`** com quebras de linha preservadas
  (`white-space: pre-wrap` via classe, não inline), e veredito em par de rádios
  **Correta / Incorreta** com alvo de 48px.
- Objetivas já corrigidas listadas abaixo, cada uma com chip de resultado.
- **Toda dissertativa da tentativa precisa de veredito antes de salvar** —
  validação existente, mensagem em `.invalid-feedback` com ícone.
- `x-ui.form-actions` fixo ao final: "Salvar correção" (primary).

---

## 6.6 Prova do Aluno — `student/quizzes/show.blade.php` (13 dusk)

Coluna de leitura de 760px, sem drawer competindo por atenção.

- **Cronômetro** em chip `info` ancorado na app bar, dirigido pelo
  `QuizTimer.js`. Ao entrar nos últimos minutos **não muda de cor para
  vermelho** (proibido) — muda para `--attention-container` e o texto explicita
  o tempo restante.
- Alerta "Antes de começar": instruções da prova + tentativa atual + máximo
  ("Você atingiu o número máximo de tentativas (3)." quando aplicável).
- Questão a questão: overline "Questão 1 de 3", tipo em chip `outline`,
  enunciado em h4.
- Opções como **alvos grandes**: `--surface-sunken`, borda 1,5px, raio 14px,
  rádio ou checkbox de 22px, área clicável cobrindo a linha inteira.
- Dissertativa: `x-ui.textarea` com o hint atual ("Mínimo de 200 caracteres.")
  e contador de caracteres em caption.
- Envio único, com `confirm-modal` ("Depois de enviar você não poderá alterar
  suas respostas.").
- Retorno em alerta menta com a nota preliminar; dissertativas pendentes
  aparecem como "Aguardando correção".

## Critérios de aceite

- Os 4 tipos de questão continuam sendo exatamente os do backend.
- Nenhum hint reescrito.
- Cronômetro nunca usa vermelho/laranja/amarelo.
- `StudentQuizAttemptTest` e `EssayGradingScreenTest` verdes.
- CSS e JS em commits separados, com Dusk entre eles.
