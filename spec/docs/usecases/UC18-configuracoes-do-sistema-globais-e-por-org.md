# **Especificação de Caso de Uso: UC18 — Configurações Gerais do Sistema Globais e por Organização**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC18
* **Nome:** Configurações Gerais do Sistema Globais e por Organização
* **Módulo:** Configurações do Sistema (`System Settings & Org Overrides`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF27** | Permitir o gerenciamento de configurações do sistema em `system_settings` com suporte a override por Organização. |
| **Regra de Negócio** | **RN12** | Resolução dinâmica do `SettingService` (Organização ativa -> Global). |
| **Regra de Negócio** | **RN14** | Auditoria de Alterações de Configuração (`setting.updated`). |

---

## **3. Visão Geral e Objetivo**

Permitir a parametrização do sistema (configurações de e-mail SMTP, chaves de integração, textos institucionais e assinaturas de certificados) na tabela `system_settings`. O Administrador Global define os valores padrões globais (`org_id IS NULL`), enquanto os Gestores podem definir sobreposições (*overrides*) específicas para sua Organização (`org_id = X`).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Operador autenticado com perfil `admin` ou `gestor`.

### **4.2. Pós-condições**
* Registros inseridos/atualizados na tabela `system_settings` com chave primária composta `(setting_key, org_id)`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Edição e Resolução de Configurações**

1. O gestor clica no menu **"Configurações"** (`GET /admin/settings`).
2. O `SystemSettingController::edit()` carrega a view `settings.edit`.
3. O `SettingService` resolve os valores atuais exibidos na tela:
   - Para cada chave (ex: `mail_from_name`, `mail_from_address`, `certificate_signature_title`), verifica se existe valor específico para a Organização do gestor (`org_id`). Se existir, preenche o campo. Se não, exibe o valor padrão global com o aviso: *"Herda valor global"*.
4. A tela apresenta o formulário com os blocos:
   - **Configurações de E-mail / SMTP** (Host, Porta, Usuário, Senha, Nome do Remetente).
   - **Personalização de Certificados** (Título da Assinatura, Cargo do Assinante).
5. O gestor altera os parâmetros desejados e clica em **"Salvar Configurações"** (`PUT /admin/settings`).
6. O `SystemSettingController::update()` grava as alterações em `system_settings` associando a chave ao `org_id` da Organização do gestor.
7. A aplicação passa a utilizar os novos parâmetros customizados nas operações subsequentes daquela Organização.

---

## **6. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /admin/settings`, `PUT /admin/settings`.
* **Middleware:** `auth`, `role:admin|gestor`.
* **Service:** `App\Services\SettingService`.
