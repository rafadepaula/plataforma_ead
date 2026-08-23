# **24. Autoria de Provas, Regras e Construtor de Questões (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a tela de criação e edição de provas (`quizzes/edit.blade.php`, `partials/_question-form.blade.php`, `partials/_question-list.blade.php`) com o padrão Material Bootstrap: formulário de regras da avaliação, lista arrastável de questões, modais elevados pré-renderizados para autoria rápida, 4 tipos de questões (*Única escolha*, *Múltipla escolha*, *Verdadeiro ou Falso*, *Dissertativa*), clonagem dinâmica de opções via `<template>` com `__INDEX__` e garantia de mínimo de 2 opções.
* **Roles Cobertas:** `role:admin`, `role:gestor`.
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.5, `spec/new_ds/Criar a prova - Anatomia.dc.html`.

---

## **2. Estrutura de UI & Hierarquia de Componentes (`quizzes/edit.blade.php`)**

```text
quizzes/edit.blade.php
├── x-layout.page-header (Breadcrumb 4 níveis, Kicker "{Curso} / {Módulo} / {Aula}", Título "Editar prova")
├── x-ui.card (Formulário de Regras de Avaliação - max 640px)
│   └── form PUT quizzes.update [dusk="quiz-form"]
│       ├── Identificação: title, instructions
│       ├── Tentativas e Tempo: allow_retries (switch), max_attempts, time_limit_minutes
│       ├── Resultado: min_score_percentage, show_correct_answers (switch)
│       └── x-ui.form-actions: Salvar alterações [dusk="quiz-submit"] + Cancelar
│
├── Seção de Questões + Botão Primário "Nova questão" [dusk="new-question"]
├── _question-list.blade.php (Lista arrastável com ModuleReorder.js) [dusk="question-list"]
│   └── li[data-id] · alça grip-vertical · número · enunciado · chip de tipo · Editar · Remover
│
├── x-ui.modal #question-create-modal (Modal em branco, formSuffix="create")
└── @foreach x-ui.modal #question-edit-modal-{id} (Modais pré-renderizados, formSuffix="edit-{id}")
    └── _question-form.blade.php
        ├── Enunciado (question_text)
        ├── x-ui.select (type: single_choice, multiple_choice, true_false, essay)
        ├── x-ui.alert (data-essay-hint - aviso para tipo dissertativa)
        ├── div[data-options-container] (Lista de opções com dynamic cloning)
        └── Ações do Modal: Salvar questão [dusk="question-submit-{suffix}"] + Cancelar
```

---

## **3. Regras de Negócio e Tipos de Questão (`QuizBuilder.js`)**

### 3.1 Matriz de Comportamento por Tipo de Questão
1. **`single_choice` (Única escolha):** Exatamente 1 opção correta (`radio`), texto editável, botão de adicionar visível.
2. **`multiple_choice` (Múltipla escolha):** 1 ou mais opções corretas (`checkbox`), texto editável, botão de adicionar visível.
3. **`true_false` (Verdadeiro ou Falso):** Exatamente 1 correta (`radio`), 2 linhas fixas ("Verdadeiro" / "Falso") em modo `readonly`, botões de adicionar e remover ocultos.
4. **`essay` (Dissertativa / Correção manual):** Bloco de opções ocultado e desabilitado (para não enviar payload `options[]`), exibe alerta explicativo: *"Questões dissertativas não têm opções — a resposta do aluno é um texto livre corrigido manualmente pelo Gestor."*.

### 3.2 Manipulação Dinâmica de Opções & Templates
- Linha marcada como correta recebe fundo menta `--mint-100`, borda `--secondary` e classe `.is-correct`.
- Mínimo de 2 opções obrigatórias: tentativa de remover quando houver 2 emite toast *"Uma questão precisa de ao menos 2 opções."*.
- Adicionar opção clona `<template data-option-template>` substituindo `__INDEX__` pelo próximo índice no DOM.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="quiz-form"`: Formulário de regras da avaliação.
* `dusk="quiz-title-input"`: Input de título da prova.
* `dusk="quiz-allow-retries"`: Switch de permitir novas tentativas.
* `dusk="quiz-max-attempts"`: Input de máximo de tentativas.
* `dusk="quiz-time-limit"`: Input de limite de tempo (minutos).
* `dusk="quiz-show-correct-answers"`: Switch de exibir gabarito.
* `dusk="quiz-min-score"`: Input de nota mínima para aprovação (%).
* `dusk="quiz-submit"`: Botão salvar regras da avaliação.
* `dusk="new-question"`: Botão abrir modal de nova questão.
* `dusk="question-list"`: Elemento `<ul>` da lista de questões.
* `dusk="question-row-{id}"`: Item `<li>` da questão.
* `dusk="edit-question-{id}"`: Botão abrir modal de edição da questão.
* `dusk="delete-question-{id}"`: Botão de exclusão da questão.
* `dusk="question-form-{suffix}"`: Formulário da questão no modal.
* `dusk="question-text-{suffix}"`: Textarea do enunciado.
* `dusk="question-type-{suffix}"`: Select de tipo de questão.
* `dusk="question-submit-{suffix}"`: Botão submit da questão.
* `dusk="add-option-{suffix}"`: Botão adicionar nova opção.
* `dusk="option-correct-{suffix}-{i}"`: Input radio/checkbox de gabarito da opção.
* `dusk="option-text-{suffix}-{i}"`: Input de texto da opção.
* `dusk="remove-option-{suffix}-{i}"`: Botão remover opção.

---

## **5. Checklist de Implementação & Testes**

- [ ] View `resources/views/quizzes/edit.blade.php` e parciais refatorados no padrão Material Bootstrap.
- [ ] Modais de questão elevados (`.ds-card-elevated`, raio 28px, elev-5).
- [ ] Módulo JS `QuizBuilder.js` com suporte aos 4 tipos de questão e clonagem via template.
- [ ] Teste Feature: `MultiTenantQuizManagementTest.php` cobrindo regras, CRUD de questões e reordenação.
- [ ] Teste Dusk: `QuizAuthoringDuskTest.php` cobrindo adição dinâmica de opções, alternância de tipo e salvamento.
