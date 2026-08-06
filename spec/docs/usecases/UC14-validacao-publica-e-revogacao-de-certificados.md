# **Especificação de Caso de Uso: UC14 — Validação Pública e Revogação Lógica de Certificados**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC14
* **Nome:** Validação Pública e Revogação Lógica de Certificados
* **Módulo:** Certificados e Autenticidade (`Public Certificate Verification & Revocation`)
* **Atores Principais:** Público Geral / Verificador, Gestor de Organização, Administrador Global
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF17** | Consultar autenticidade pública do certificado em `/validar-certificado/{hash}` por Hash SHA-256 ou QR Code; permitir revogação lógica imutável por Admin/Gestor com motivo. |
| **Regra de Negócio** | **RN07** | **Imutabilidade e Hash SHA-256:** A consulta em `/validar-certificado/{hash}` é 100% pública e cross-tenant. Certificados revogados possuem revogação lógica imutável (`revoked_at`, `revoked_by`, `revoke_reason`) e JAMAIS retornam erro 404. |
| **Regra de Negócio** | **RN14** | Auditoria de Revogação (`certificate.revoked`). |

---

## **3. Visão Geral e Objetivo**

Disponibilizar uma página pública unificada (sem necessidade de login ou autenticação) para validação de autenticidade de certificados através do código Hash SHA-256 ou leitura do QR Code impresso no documento, informando a validade do certificado e dados da Organização emissora. Adicionalmente, permitir que Gestores e Admins efetuem a revogação lógica do certificado em caso de fraude ou irregularidade, registrando o motivo e mantendo a transparência pública.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Para validação pública: Possuir o código hash de 64 caracteres ou escanear o QR Code.
* Para revogação: Operador autenticado com perfil `admin` ou `gestor` da Organização emissora.

### **4.2. Pós-condições**
* **Consulta Pública:** Exibição da página contendo os dados do aluno, curso, carga horária, data de emissão e status do certificado (`VÁLIDO` em verde ou `REVOGADO` em vermelho).
* **Revogação Lógica:** Colunas `revoked_at = now()`, `revoked_by = auth()->id()` e `revoke_reason` atualizadas na tabela `certificates`. Registro do evento `certificate.revoked` em `audit_logs`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Consulta Pública de Autenticidade (Via Hash ou QR Code)**

1. Qualquer pessoa (empregador, conselho, público geral) acessa a URL pública `/validar-certificado/{hash}` (digitando o código ou escaneando o QR Code do PDF).
2. A rota `GET /validar-certificado/{hash}` (`PublicCertificateController::show()`) processa a requisição **sem exigir autenticação/login**:
   - Resolve o hash na tabela `certificates` ignorando qualquer escopo tenant (`withoutGlobalScopes()`).
3. **Cenário A: Certificado Válido (`revoked_at IS NULL`)**
   - A view `certificates.verify` é renderizada com um **Banner de Autenticidade Verde**: *"Certificado Autêntico e Válido"*.
   - Exibe a marca e nome da Organização emissora (`organizations.name`, `logo_path`).
   - Exibe os dados do Aluno (Nome Completo, CPF parcial `***.123.456-**`).
   - Exibe os dados do Curso (Título, Carga Horária, Data de Emissão).
   - Exibe o Código Hash SHA-256 e confirmação de validação no sistema.
4. **Cenário B: Certificado Revogado (`revoked_at IS NOT NULL`) (RN07)**
   - O sistema **NÃO retorna erro 404**. A página é exibida contendo um **Banner de Alerta Vermelho**: *"CERTIFICADO REVOGADO"*.
   - Exibe a Data da Revogação (`revoked_at`) e o **Motivo da Revogação** (`revoke_reason`).
   - Exibe os dados originais de quando foi emitido para fins de transparência histórica.

---

### **5.2. Fluxo Principal 2: Revogação Lógica pelo Gestor/Admin**

1. O gestor acessa a lista de certificados emitidos de um curso (`GET /courses/{course}/certificates`).
2. O `CertificateController::index()` lista os certificados com: Nome do Aluno, Data de Emissão, Hash SHA-256, Status (`Válido`/`Revogado`) e Botão **"Revogar Certificado"**.
3. O gestor clica no botão **"Revogar Certificado"** de um aluno.
4. O modal Blade `certificates.revoke-modal` é exibido solicitando obrigatoriamente:
   - **Motivo da Revogação** (`name="revoke_reason"`, textarea, obrigatorio, min 10 caracteres).
5. O gestor preenche o motivo e confirma em **"Confirmar Revogação"** (`PUT /certificates/{certificate}/revoke`).
6. O `CertificateController::revoke()` valida a permissão via `CertificatePolicy::revoke()`:
   - Grava `revoked_at = now()`.
   - Grava `revoked_by = Auth::id()`.
   - Grava `revoke_reason = $request->revoke_reason`.
7. O `AuditService` grava o evento `certificate.revoked` em `audit_logs` contendo `certificate_id`, `hash` e `revoke_reason`.
8. A interface atualiza o badge do certificado para `Revogado` com a mensagem: *"Certificado revogado com sucesso."*

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Hash Inexistente na Base**
* **Gatilho:** Acesso a `/validar-certificado/hash_inexistente_ou_digitado_errado`.
* **Comportamento:** O sistema retorna a página `certificates.not-found` (HTTP 404) com o aviso: *"Nenhum certificado foi encontrado com o código Hash fornecido. Verifique se o código foi digitado corretamente."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /validar-certificado/{hash}` (`name: certificates.verify`), `GET /courses/{course}/certificates`, `PUT /certificates/{certificate}/revoke`.
* **Middleware:** Sem middleware para a validação pública; `auth`, `role:admin|gestor` para a revogação.
* **Controllers:** `PublicCertificateController`, `CertificateController`.
