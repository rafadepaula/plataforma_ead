# **Especificação de Caso de Uso: UC12 — Realização de Questionários pelo Aluno e Exibição de Gabarito**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC12
* **Nome:** Realização de Questionários pelo Aluno e Exibição de Gabarito
* **Módulo:** Experiência do Aluno e Provas (`Student Quiz Execution`)
* **Atores Principais:** Aluno Capacitando
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF09** | Exibir o questionário para o aluno, calcular a nota percentual de acertos e apresentar o resultado e gabarito. |
| **Regra de Negócio** | **RN02** | **Cálculo da Nota:** Porcentagem calculada dividindo acertos pelo total de questões. Provas com questões dissertativas ficam como `awaiting_manual_grading`. |
| **Regra de Negócio** | **RN03** | **Repetição de Provas:** Respeitar `allow_retries` e o limite máximo `max_attempts`. |
| **Regra de Negócio** | **RN04** | **Exibição de Gabarito:** Gabarito com respostas corretas exibido apenas se `show_correct_answers = true`. |
| **Regra de Negócio** | **RN08** | Restrição de Matrícula Ativa (`student.enrolled`). |

---

## **3. Visão Geral e Objetivo**

Permitir que o aluno matriculado responda a uma prova/questionário vinculado a uma lição da Sala de Aula, dentro do tempo limite estabelecido e respeitando as regras de tentativas máximas, obtendo o cálculo imediato da nota para questões objetivas ou o encaminhamento para correção manual de questões dissertativas.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Aluno autenticado e matriculado no curso (`student.enrolled`).
* Lição do tipo `quiz` contendo questões cadastradas.
* Aluno ter número de tentativas realizadas menor que `max_attempts` (ou `max_attempts` ser nulo).

### **4.2. Pós-condições**
* Linha criada em `quiz_attempts` com o status apropriado (`graded` ou `awaiting_manual_grading`).
* Respostas gravadas em `quiz_answers`.
* Se aprovado e sem questões dissertativas pendentes, a lição é concluída em `lesson_progress` (`completion_source => 'quiz_passed'`).

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Execução da Prova pelo Aluno**

1. Na Sala de Aula (`/lessons/{lesson}`), o aluno clica em uma lição do tipo `quiz`.
2. A rota `GET /lessons/{lesson}/quiz` (`StudentQuizController::show()`) exibe a tela de abertura da prova:
   - Título e Instruções do Questionário.
   - Nota Mínima para Aprovação (ex: 70%).
   - Limite de Tempo (ex: 30 minutos ou "Sem limite").
   - Tentativas Realizadas / Restantes (ex: Tentativa 1 de 3).
   - Botão **"Iniciar Prova"**.
3. O aluno clica em **"Iniciar Prova"**.
4. O backend cria a tentativa em `quiz_attempts` (`status => 'in_progress'`, `started_at => now()`) e a interface renderiza o formulário da prova:
   - Se houver `time_limit_minutes`, o script `QuizTimer.js` exibe o cronômetro regressivo no topo da tela.
   - Lista de Questões com seus respectivos inputs:
     - `single_choice` / `true_false`: Radio buttons (`name="answers[{question_id}]"`).
     - `multiple_choice`: Checkboxes (`name="answers[{question_id}][]"`).
     - `essay`: Textarea com contador de caracteres (`name="essay_answers[{question_id}]"`).
   - Botão **"Finalizar e Submeter Prova"**.
5. O aluno responde às questões e clica em **"Finalizar e Submeter Prova"** (`POST /lessons/{lesson}/quiz/submit`).
6. O backend processa no `StudentQuizController::submit()`:
   - Interrompe o cronômetro (`completed_at => now()`).
   - Salva cada resposta em `quiz_answers`.
   - Se a prova contiver **apenas questões objetivas/V-F**:
     - Calcula a nota: `score_percentage = (respostas_corretas / total_questoes) * 100`.
     - Define `is_passed = (score_percentage >= min_score_percentage)`.
     - Define `status = 'graded'`.
     - Se aprovado, conclui a lição em `lesson_progress` (`completion_source => 'quiz_passed'`).
   - Se a prova contiver **alguma questão dissertativa (`essay`)**:
     - Define `status = 'awaiting_manual_grading'`.
     - Deixa `score_percentage = null` e `is_passed = null`.
7. O sistema redireciona para a tela de resultado da tentativa.

---

### **5.2. Fluxo Principal 2: Exibição de Resultado e Gabarito (RN04)**

1. Na tela final pós-submissão:
   - Se `status = 'graded'`: Exibe o card com a Pontuação Obtida (ex: `80.00%`), mensagem de Aprovado (verde) ou Reprovado (vermelho) e o botão "Tentar Novamente" (se permitido).
   - Se `status = 'awaiting_manual_grading'`: Exibe aviso amarelado: *"Sua prova contém questões dissertativas e está aguardando correção pelo professor. Você será notificado assim que a avaliação for concluída."*
2. **Exibição do Gabarito (RN04):** Se `show_correct_answers === true`, a tela lista todas as questões objetivas exibindo a opção assinalada pelo aluno e destacando a resposta correta em verde. Se `false`, o gabarito é ocultado.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Esgotamento do Tempo Limite (Cronômetro)**
* **Gatilho:** O tempo do `QuizTimer.js` chega a `00:00`.
* **Comportamento:** O JavaScript submete automaticamente o formulário via AJAX com as respostas preenchidas até o momento, exibindo o alerta: *"O tempo limite expirou! Sua prova foi submetida automaticamente."*

### **6.2. Fluxo de Exceção 2: Limite de Tentativas Excedido (RN03)**
* **Gatilho:** Aluno reprovado tenta iniciar nova prova tendo atingido `max_attempts`.
* **Comportamento:** O botão "Iniciar Prova" é desabilitado e a tela exibe o aviso: *"Você atingiu o limite máximo de tentativas permitidas para este questionário."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /lessons/{lesson}/quiz`, `POST /lessons/{lesson}/quiz/submit`.
* **Middleware:** `auth`, `student.enrolled`.
* **Controller:** `StudentQuizController`.
* **JS Asset:** `public/js/modules/QuizTimer.js`.
