# **09. Certificados Multitenant e Validação Pública Global**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF10:** Configuração de Regras de Certificado pelo Gestor (`role:gestor`).
* **RF16:** Emissão de PDF com QR Code e identidade visual da Organização emissora.
* **RF17:** Validação Pública de Certificados na rota global `/validar-certificado` por Hash SHA-256.
* **RN01 / RN07:** Liberação sob 100% de elegibilidade e hash criptográfico imutável `sha256(user_id + course_id + formatted_issued_at + APP_KEY)`.
* **RF25 (nova):** Revogação de certificado pelo Gestor/Admin.

---

## **1.1. Gatilho de Emissão (`IssueCertificateAction`)**

- Listener do evento `CourseCompletedByStudent` (disparado em SPEC-07 §1.2 quando `course_user.status` transiciona para `completed`).
- Avalia `course_completion_rules` do curso (SPEC-00 §2.1 item 15):
  - `rule_type = all_lessons`: elegível quando `course_user.progress_percentage >= required_percentage` (padrão 100).
  - `rule_type = min_quiz_score`: elegível quando a melhor nota do `quiz_attempts` do quiz alvo (`target_id`) atinge `required_percentage`.
  - `rule_type = specific_module`: elegível quando todas as lições do módulo alvo (`target_id`) estão com `is_completed=true`.
  - Se o curso tiver múltiplas regras cadastradas, **todas** devem ser satisfeitas (AND).
- Execução síncrona no mesmo request que fecha o critério (`QUEUE_CONNECTION=sync` padrão, SPEC-00 §1.2), evitando job assíncrono; idempotente via `UNIQUE(user_id, course_id)` em `certificates` (não reemite se já existe linha não revogada).
- Se um certificado revogado existir para o par `user_id`/`course_id` e o aluno voltar a cumprir a regra (ex.: refez curso após reabertura), a reemissão cria uma **nova linha** apenas se a política de negócio permitir — fora de escopo desta versão; hoje `UNIQUE` bloqueia reemissão, então revogação é tratada como estado terminal por matrícula.

## **1.2. Revogação (`RevokeCertificateAction`)**

- Autorização: `role:gestor` (restrito à própria Org) ou `role:admin`.
- Grava `revoked_at = now()`, `revoked_by = auth()->id()`, `revoke_reason` (obrigatório, min 10 caracteres — motivo textual livre, ex.: fraude na prova, matrícula cancelada retroativamente).
- **Não é soft-delete/hard-delete** da linha `certificates` — o registro e o hash permanecem, ver §2.

---

## **2. Geração PDF & Validação Global**

- Hash SHA-256 criptográfico unívoco.
- QR Code impresso no PDF apontando para a rota pública global `/validar-certificado/{hash}`.
- O PDF é personalizado com o nome, CNPJ e logo da Organização do curso.
- **Página pública `/validar-certificado/{hash}`:**
  - Certificado válido (`revoked_at = null`): exibe nome do aluno, curso, Organização, carga horária, data de emissão — status "Válido".
  - Certificado revogado (`revoked_at != null`): página **continua respondendo 200** (não 404 — auditabilidade pública é intencional), exibe banner destacado "Certificado Revogado em {revoked_at}" e `revoke_reason`, sem esconder os dados originais do certificado.

---

## **3. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migrations `course_completion_rules` e `certificates` (com `revoked_at`/`revoked_by`/`revoke_reason`)
- [ ] Listener `IssueCertificateAction` no evento `CourseCompletedByStudent`, cobrindo os 3 `rule_type`
- [ ] Action `RevokeCertificateAction` + UI de revogação para Gestor/Admin
- [ ] `CertificatePdfService` renderizando marca e logo da Organização emissora
- [ ] Validação pública global de certificados de qualquer Organização, incluindo estado "Revogado"
- [ ] Harness: Criar/atualizar as 3 skills (`certificates-architecture`, `certificates-conventions`, `certificates-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `CertificateEligibilityTest.php`, `PublicVerificationTest.php`, `CertificateRevocationTest.php` aprovados com 100%.
