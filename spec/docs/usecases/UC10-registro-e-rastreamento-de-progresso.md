# **Especificação de Caso de Uso: UC10 — Registro e Rastreamento do Progresso do Aluno**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC10
* **Nome:** Registro e Rastreamento do Progresso do Aluno
* **Módulo:** Experiência do Aluno e Progresso (`Progress & Completion`)
* **Atores Principais:** Aluno Capacitando
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF15** | Registrar a conclusão das lições e recalcular o progresso global do curso em tempo real via AJAX. |
| **Regra de Negócio** | **RN01** | Liberação do Certificado (Gatilho automático ao atingir 100% dos pré-requisitos). |
| **Regra de Negócio** | **RN08** | Restrição de Matrícula Ativa (`student.enrolled`). |
| **Regra de Negócio** | **RN14** | Auditoria de Eventos e Progresso. |

---

## **3. Visão Geral e Objetivo**

Registrar o progresso pedagógico do aluno à medida que ele consome os conteúdos do curso. A conclusão de cada lição pode ocorrer por 3 origens (`completion_source`): clique manual do aluno, atingimento do tempo de visualização do vídeo (`video_threshold` - 90% do tempo) ou aprovação no questionário vinculado (`quiz_passed`). A cada lição concluída, o sistema recalcula dinamicamente a porcentagem em `course_user.progress_percentage` e avalia se os pré-requisitos do certificado foram satisfeitos.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Aluno autenticado e matriculado no curso (`student.enrolled`).
* Lição aberta na Sala de Aula.

### **4.2. Pós-condições**
* Linha criada/atualizada em `lesson_progress` com `is_completed = true`, `completed_at = now()` e `completion_source` registrado.
* Porcentagem `course_user.progress_percentage` atualizada.
* Se atingiu 100% e satisfez os pré-requisitos, dispara a `IssueCertificateAction`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Conclusão por Clique Manual**

1. Na Sala de Aula (`/lessons/{lesson}`), o aluno lê o conteúdo textual ou PDF.
2. No rodapé da aula, o aluno clica no botão **"Marcar como Concluída"**.
3. O JavaScript `LessonPlayer.js` envia uma requisição AJAX `POST /lessons/{lesson}/complete` com `{completion_source: 'manual_click'}`.
4. O `MarkLessonCompleteAction` grava/atualiza a linha em `lesson_progress`:
   - `is_completed => true`
   - `completion_source => 'manual_click'`
   - `completed_at => now()`
5. O `RecalculateCourseProgress` recalcula: `progress_percentage = (lições_concluídas / total_lições_publicadas) * 100`.
6. A resposta JSON retorna a nova porcentagem e o status. A interface atualiza o ícone da lição na sidebar para check verde (✔) e atualiza a barra de progresso em tempo real sem recarregar a página.

---

### **5.2. Fluxo Principal 2: Conclusão Automática via Threshold de Vídeo (90%)**

1. O aluno reproduz o vídeo do YouTube na Sala de Aula.
2. O script `LessonPlayer.js` monitora o tempo de reprodução através da API do YouTube Player em intervalos de 5 segundos via evento `onStateChange`.
3. Quando o tempo assistido atinge 90% da duração total do vídeo (`watched_seconds / duration >= 0.9`), o script dispara automaticamente um `POST /lessons/{lesson}/progress` contendo `{watched_seconds: N, completion_source: 'video_threshold'}`.
4. O backend registra a conclusão automática em `lesson_progress` (`completion_source => 'video_threshold'`), recalcula o progresso do curso e atualiza a interface visual do aluno com a notificação: *"Lição concluída automaticamente!"*.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Lição Já Concluída Anterimente**
* **Gatilho:** O aluno clica novamente em "Marcar como Concluída" em uma aula que já possui `is_completed = true`.
* **Comportamento:** O backend reconhece a conclusão idempotente (`firstOrCreate`/`update`), mantém a data de conclusão original e retorna HTTP 200 sem duplicar registros.

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `POST /lessons/{lesson}/complete`, `POST /lessons/{lesson}/progress`.
* **Middleware:** `auth`, `student.enrolled`.
* **Actions:** `App\Actions\MarkLessonCompleteAction`, `App\Actions\RecalculateCourseProgress`.
* **JS Asset:** `public/js/modules/LessonPlayer.js`.
