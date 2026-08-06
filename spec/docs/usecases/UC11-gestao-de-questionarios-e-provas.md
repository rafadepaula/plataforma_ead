# **Especificação de Caso de Uso: UC11 — Gestão de Questionários, Provas e Correção Manual**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC11
* **Nome:** Gestão de Questionários, Provas e Correção Manual
* **Módulo:** Motor de Avaliações (`Quizzes & Evaluation Engine`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF08** | Permitir criar questionários contendo enunciado, opções, tipo (única, múltipla escolha, V/F, dissertativa), gabarito, limite de tempo e tentativas. |
| **Requisito Funcional** | **RF28** | Disponibilizar interface para Gestor/Admin avaliar e dar nota a respostas dissertativas pendentes de correção. |
| **Regra de Negócio** | **RN02** | **Cálculo da Nota do Questionário:** Questões dissertativas deixam o resultado travado em `awaiting_manual_grading` até a correção manual. |
| **Regra de Negócio** | **RN03** | Repetição e Limite de Tentativas em Questionários (`allow_retries` e `max_attempts`). |
| **Regra de Negócio** | **RN04** | Exibição do Gabarito (`show_correct_answers`). |
| **Regra de Negócio** | **RN14** | Auditoria de Correção Manual (`essay.graded`). |

---

## **3. Visão Geral e Objetivo**

Permitir que gestores e administradores configurem provas e questionários vinculados às lições do curso (definição de nota mínima, limite de tempo, tentativas máximas, gabarito e criação de questões objetivas e dissertativas) e realizem a avaliação/correção manual das respostas dissertativas enviadas pelos alunos na fila de correções pendentes.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Operador autenticado com perfil `admin` ou `gestor`.
* Lição do tipo `quiz` cadastrada na estrutura do curso.

### **4.2. Pós-condições**
* Questionário, questões e opções salvos nas tabelas `quizzes`, `quiz_questions` e `quiz_options`.
* Correção manual gravada em `quiz_answers` com nota atualizada e recálculo da nota final em `quiz_attempts`. Evento `essay.graded` auditado.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Configuração do Questionário e Questões**

1. O gestor acessa as lições de um módulo e clica em **"Criar Questionário"** na lição do tipo quiz (`GET /lessons/{lesson}/quiz/create`).
2. A tela `quizzes.create` apresenta os parâmetros gerais:
   - **Título do Questionário** (`name="title"`, obrigatorio).
   - **Instruções** (`name="instructions"`, textarea).
   - **Nota Mínima para Aprovação (%)** (`name="min_score_percentage"`, default 70).
   - **Permitir Novas Tentativas?** Checkbox (`name="allow_retries"`, default true).
   - **Limite de Tentativas** (`name="max_attempts"`, numero ou nulo para ilimitado).
   - **Tempo Limite em Minutos** (`name="time_limit_minutes"`, numero ou nulo para sem limite).
   - **Exibir Gabarito ao Final?** Checkbox (`name="show_correct_answers"`).
3. O gestor salva o formulário (`POST /lessons/{lesson}/quiz`).
4. O sistema redireciona para a tela de edição do questionário (`GET /quizzes/{quiz}/edit`), onde o gestor adiciona as questões através do construtor em modal ou inline:
   - **Enunciado da Questão** (`question_text`, obrigatorio).
   - **Tipo da Questão** (`type`, select: `single_choice`, `multiple_choice`, `true_false`, `essay`).
   - Se for objetiva ou V/F: Adiciona as opções de resposta em `quiz_options` e marca a(s) opção(ões) correta(s) (`is_correct = true`).
   - Se for dissertativa (`essay`): O painel de opções é ocultado, exigindo apenas o enunciado.
5. O gestor salva a questão (`POST /quizzes/{quiz}/quiz-questions`).

---

### **5.2. Fluxo Principal 2: Fila de Correção Manual de Questões Dissertativas (RF28)**

1. O gestor navega até a opção **"Correções Pendentes"** no menu do painel (`GET /quiz-attempts/pending`).
2. O `EssayGradingController::pending()` exibe a lista de tentativas de prova com `status = 'awaiting_manual_grading'` escopadas pela Organização.
3. A tabela exibe: Nome do Aluno, Nome do Curso/Quiz, Data da Submissão e Botão **"Avaliar Prova"**.
4. O gestor clica em **"Avaliar Prova"** (`GET /quiz-attempts/{quizAttempt}`).
5. A tela `quiz-attempts.show` apresenta:
   - Respostas objetivas do aluno com a correção automática.
   - A questão dissertativa destacada contendo a **Resposta do Aluno** (`essay_answer`).
   - Campo **Resultado da Questão** (Select: `Correta` [100% dos pontos da questão] ou `Incorreta` [0%]).
   - Campo **Feedback/Comentário do Professor** (Textarea opcional).
   - Botão **"Finalizar Avaliação"**.
6. O gestor seleciona o resultado e clica em **"Finalizar Avaliação"** (`POST /quiz-attempts/{quizAttempt}/grade`).
7. O backend:
   - Atualiza `quiz_answers.is_correct` e grava `graded_by` e `graded_at`.
   - Recalcula a nota percentual final em `quiz_attempts.score_percentage`.
   - Atualiza o status da tentativa para `graded` e define `is_passed` (se `score_percentage >= min_score_percentage`).
   - Dispara o `AuditService::log('essay.graded')`.
8. Se aprovado, avalia e aciona a recalculagem do progresso e emissão do certificado.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Questão Objetiva sem Opção Correta Marcada**
* **Gatilho:** Salvar uma questão do tipo `single_choice` ou `true_false` sem selecionar nenhuma opção como correta.
* **Comportamento:** O `QuizQuestionController` bloqueia o salvamento e exibe erro de validação HTTP 422: *"Marque ao menos uma opção como correta para questões objetivas."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /lessons/{lesson}/quiz/create`, `POST /lessons/{lesson}/quiz`, `GET /quizzes/{quiz}/edit`, `POST /quizzes/{quiz}/quiz-questions`, `GET /quiz-attempts/pending`, `GET /quiz-attempts/{quizAttempt}`, `POST /quiz-attempts/{quizAttempt}/grade`.
* **Middleware:** `auth`, `role:admin|gestor`.
* **Controllers:** `QuizController`, `QuizQuestionController`, `EssayGradingController`.
