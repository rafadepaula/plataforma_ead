# **Especificação de Caso de Uso: UC09 — Consumo de Aulas, Sala de Aula Virtual e Players Responsivos**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC09
* **Nome:** Consumo de Aulas, Sala de Aula Virtual e Players Responsivos
* **Módulo:** Experiência do Aluno e Sala de Aula (`Student Classroom`)
* **Atores Principais:** Aluno Capacitando
* **Atores Secundários:** Administrador, Gestor (Modo Visualização)
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF13** | Renderizar player do YouTube incorporado com parâmetros restritivos na área de estudos. |
| **Requisito Funcional** | **RF14** | Exibir PDFs diretamente na página da aula com leitor HTML/iframe incorporado e botão de download restrito. |
| **Requisito Funcional** | **RF20** | Garantir que o aluno visualize e acesse **apenas** conteúdos e cursos nos quais possui matrícula ativa na Organização (`EnsureStudentIsEnrolled`). |
| **Regra de Negócio** | **RN08** | **Restrição Estrita de Matrícula e Org:** Alunos sem registro em `course_user` para o curso solicitado recebem erro HTTP 403 (Forbidden) ao tentar acessar a Sala de Aula. |

---

## **3. Visão Geral e Objetivo**

Prover o ambiente imersivo da Sala de Aula Virtual (Player EAD) onde o aluno assiste aos vídeos sanitizados do YouTube, realiza a leitura de textos e visualiza documentos PDF diretamente na tela. O acesso é estritamente controlado pelo Middleware `EnsureStudentIsEnrolled` para garantir que o aluno só acesse cursos em que esteja regularmente matriculado com status ativo (RN08).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O aluno deve estar autenticado.
* O aluno deve possuir matrícula ativa (`status = 'active'`) na tabela `course_user` para o curso solicitado.

### **4.2. Pós-condições**
* A página da Sala de Aula é renderizada com a sidebar de progresso e o player de conteúdo ativo.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Navegação na Sala de Aula e Player Responsivo**

1. O aluno navega até a área **"Meus Cursos"** (`GET /meus-cursos` - `StudentCourseController@index`).
2. O sistema exibe os cards dos cursos matriculados. O aluno clica no botão **"Acessar Sala de Aula"** em um curso específico.
3. A requisição `GET /courses/{course}/classroom` é disparada.
4. O Middleware `EnsureStudentIsEnrolled` intercepta a requisição:
   - Verifica se `$user` é Admin (liberado) ou se possui registro em `course_user` para aquele `course_id` com `status === 'active'`.
   - Se validado, permite a passagem para o `ClassroomController::show()`.
5. A interface Blade da Sala de Aula (`classroom.show`) é renderizada com layout em duas colunas:
   - **Coluna Principal (Área de Conteúdo / Player):**
     - Se a lição selecionada possuir `youtube_url`: Renderiza o iframe do player do YouTube sanitizado (`?modestbranding=1&rel=0&controls=1&disablekb=1`).
     - Se possuir `pdf_path`: Renderiza o leitor de PDF incorporado via `<iframe>` ou leitor HTML5 com botão de download.
     - Se possuir `content_text`: Exibe o texto formatado da aula.
     - Botão **"Marcar como Concluída"** ou indicador de conclusão.
   - **Sidebar Lateral (Estrutura do Curso):**
     - Exibe a barra de progresso percentual global do curso.
     - Lista todos os Módulos e Lições ordenados por `order_index`.
     - Exibe ícones de check verde (✔) ao lado das lições já concluídas em `lesson_progress`.
6. O aluno assiste ao vídeo, lê o material e clica em uma nova lição na sidebar (`GET /lessons/{lesson}`), que atualiza a área de conteúdo via AJAX ou recarregamento fluido.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Tentativa de Acesso Não Matriculado (RN08)**
* **Gatilho:** Aluno tenta acessar diretamente a URL `/courses/99/classroom` sem possuir registro em `course_user` para aquele curso.
* **Comportamento do Sistema:**
  1. O Middleware `EnsureStudentIsEnrolled` intercepta a requisição.
  2. Verifica a ausência do vínculo ativo em `course_user`.
  3. O Middleware bloqueia a requisição e nega o acesso com o código **HTTP 403 (Forbidden)**.
  4. Redireciona o aluno para `/meus-cursos` exibindo o alerta de erro: *"Acesso negado. Você não possui matrícula ativa neste curso."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /meus-cursos`, `GET /courses/{course}/classroom`, `GET /lessons/{lesson}`.
* **Middleware:** `auth`, `student.enrolled` (`App\Http\Middleware\EnsureStudentIsEnrolled`).
* **Controllers:** `StudentCourseController`, `ClassroomController`.
