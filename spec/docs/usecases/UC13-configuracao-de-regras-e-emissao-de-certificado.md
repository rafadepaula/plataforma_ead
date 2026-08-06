# **Especificação de Caso de Uso: UC13 — Configuração de Regras e Emissão Automatizada de Certificado PDF**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC13
* **Nome:** Configuração de Regras e Emissão Automatizada de Certificado PDF
* **Módulo:** Certificação e Conclusão (`Certificates & Completion Rules`)
* **Atores Principais:** Aluno Capacitando, Gestor de Organização, Administrador Global
* **Atores Secundários:** Gerador de PDF (`barryvdh/laravel-dompdf`), Gerador de QR Code
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF10** | Permitir ao Admin/Gestor configurar pré-requisitos em `course_completion_rules` (`all_lessons`, `min_quiz_score`, `specific_module`). |
| **Requisito Funcional** | **RF16** | Gerar arquivo PDF do certificado quando todas as regras do curso forem cumpridas. |
| **Requisito Funcional** | **RF25** | Enviar notificação por e-mail e in-app informando a emissão do certificado. |
| **Regra de Negócio** | **RN01** | **Liberação do Certificado:** O certificado só é emitido se 100% dos pré-requisitos configurados forem atingidos. |
| **Regra de Negócio** | **RN07** | **Imutabilidade e Hash SHA-256:** O Hash SHA-256 é calculado via `hash('sha256', "cert_{user_id}_{course_id}_{issued_at_ISO}_{APP_KEY}")`. |
| **Regra de Negócio** | **RN13** | Envio de e-mail de notificação em bloco `try/catch`. |
| **Regra de Negócio** | **RN14** | Registro do evento `certificate.issued` em `audit_logs`. |

---

## **3. Visão Geral e Objetivo**

Gerenciar as regras de conclusão necessárias para a liberação de certificados por curso e disparar automaticamente a emissão do certificado em PDF (com branding da Organização, Hash SHA-256 imutável e QR Code de verificação) assim que o aluno satisfaz 100% dos pré-requisitos exigidos.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Curso cadastrado com pré-requisitos definidos na tabela `course_completion_rules`.
* Aluno com matrícula ativa que acabou de atingir a condição de conclusão (ex: concluiu a última aula ou passou no quiz final).

### **4.2. Pós-condições**
* Registro persistido na tabela `certificates` com `validation_hash` único de 64 caracteres de extensão SHA-256 e `issued_at = now()`.
* Matrícula em `course_user` atualizada para `status = 'completed'`, `completed_at = now()`.
* Notificação enviada ao aluno.
* Arquivo PDF disponibilizado para download.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Configuração das Regras pelo Gestor**

1. O gestor acessa as configurações do curso (`GET /courses/{course}/edit`).
2. Navega até a aba/seção **"Regras de Conclusão / Certificado"**.
3. O gestor adiciona regras na tabela `course_completion_rules`:
   - `rule_type`: Select (`all_lessons` [100% das aulas assistidas], `min_quiz_score` [Nota mínima em prova], `specific_module` [Módulo obrigatório]).
   - `required_percentage`: Número (default 100).
4. O gestor clica em **"Salvar Regras de Conclusão"**.

---

### **5.2. Fluxo Principal 2: Emissão Automatizada e Download do PDF pelo Aluno**

1. O aluno assiste à última aula ou é aprovado no quiz final do curso.
2. O ouvinte do evento `LessonMarkedAsCompleted` ou `QuizAttemptGraded` aciona o `RecalculateCourseProgress`.
3. O `RecalculateCourseProgress` invoca a classe de serviço `IssueCertificateAction::execute($user, $course)`:
   - A `IssueCertificateAction` avalia todas as regras cadastradas na tabela `course_completion_rules` para o curso.
   - Confirma que 100% das regras foram atingidas.
4. A `IssueCertificateAction` verifica se já existe registro em `certificates` para aquele `user_id` e `course_id` (garantia de idempotência):
   - Se não existir, gera o `issued_at` no formato ISO 8601 (`Carbon::now()->toISOString()`).
   - Calcula o Hash SHA-256 imutável (RN07):
     `$hash = hash('sha256', "cert_{$user->id}_{$course->id}_{$issuedAtISO}_" . config('app.key'));`
   - Inseri a linha na tabela `certificates` (`user_id`, `course_id`, `validation_hash => $hash`, `issued_at`).
   - Atualiza `course_user.status = 'completed'`.
5. O sistema dispara a notificação `CertificateIssuedNotification` por e-mail e in-app.
6. A `AuditService` grava o evento `certificate.issued` em `audit_logs`.
7. Na Sala de Aula ou em "Meus Cursos", surge o botão destacado **"Baixar Certificado (PDF)"** (`GET /certificates/{certificate}/download` ou via dashboard do aluno).
8. Ao clicar em **"Baixar Certificado"**, o `CertificatePdfService` utiliza o `barryvdh/laravel-dompdf`:
   - Carrega o template Blade `certificates.pdf`.
   - Injeta a Logo, Nome, CNPJ e Cores (`primary_color`) da Organização do curso.
   - Injeta Nome do Aluno, Nome do Curso, Carga Horária, Data de Emissão, Hash SHA-256 e a imagem do QR Code gerado apontando para a URL pública: `https://dominio.com/validar-certificado/{hash}`.
9. O servidor entrega o download do arquivo PDF: `certificado_{slug_curso}_{user_id}.pdf`.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Pré-requisitos Incompletos**
* **Gatilho:** Aluno clica em "Gerar Certificado" tendo concluído 90% das aulas quando a regra exige 100%.
* **Comportamento:** A `IssueCertificateAction` recusa a emissão e a interface exibe: *"Certificado indisponível. Você concluiu 90% do curso. É necessário atingir 100% dos pré-requisitos para liberar seu certificado."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /certificates/{certificate}/download` (`CertificateController@download`).
* **Services & Actions:** `App\Actions\IssueCertificateAction`, `App\Services\CertificatePdfService`.
* **Template PDF:** `resources/views/certificates/pdf.blade.php`.
