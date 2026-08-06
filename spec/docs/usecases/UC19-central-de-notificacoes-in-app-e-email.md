# **Especificação de Caso de Uso: UC19 — Central de Notificações Multitenant In-App e por E-mail**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC19
* **Nome:** Central de Notificações Multitenant In-App e por E-mail
* **Módulo:** Notificações e Alertas (`Notifications & Alerts`)
* **Atores Principais:** Aluno Capacitando, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF25** | Enviar notificações in-app e por e-mail para 4 gatilhos do sistema; sino no topbar com contador de não lidas e polling. |
| **Regra de Negócio** | **RN13** | **Isolamento de Falha de E-mail:** O envio por e-mail é envolvido em bloco `try/catch` para garantir que falhas no SMTP jamais causem rollback na transação principal do banco. |
| **Regra de Negócio** | **RN14** | Auditoria de Notificações. |

---

## **3. Visão Geral e Objetivo**

Gerenciar o disparo e a exibição de notificações multitenant do sistema para os 4 gatilhos fechados: Convite de Matrícula Recebido, Certificado Emitido, Nova Resposta no Fórum e Matrícula Confirmada. O sistema armazena a notificação in-app na tabela `notifications` e realiza o envio por e-mail via SMTP de forma isolada e segura.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Ocorrência de um evento do sistema que dispara uma notificação (ex: nova resposta em um tópico do fórum).

### **4.2. Pós-condições**
* Registro criado na tabela `notifications` (`id` UUID, `notifiable_type`, `notifiable_id`, `data` JSON, `read_at = NULL`).
* E-mail transmitido ao destinatário (se SMTP ativo).

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Disparo e Notificação In-App**

1. Ocorre um evento notificável no sistema (ex: um gestor publica uma resposta em um tópico criado por um aluno no fórum).
2. O ouvinte de evento dispara `$user->notify(new ForumReplyNotification($reply))`.
3. A classe `Notification` executa o método `via()` retornando `['database', 'mail']`.
4. **Gravando In-App:** O Laravel insere uma linha na tabela `notifications` com os dados do alerta (Título, Mensagem, URL de destino e ícone).
5. **Gravando E-mail (RN13):** O backend dispara o e-mail envolvido em um bloco `try/catch`:
   ```php
   try {
       Mail::to($user->email)->send(new ForumReplyMail($reply));
   } catch (\Throwable $e) {
       Log::error("Falha ao enviar e-mail de notificação: " . $e->getMessage());
   }
   ```
6. Mesmo que o SMTP falhe, a transação do fórum não é revertida e a notificação in-app permanece criada no banco de dados.

---

### **5.2. Fluxo Principal 2: Consumo no Topbar e Polling AJAX**

1. O usuário logado visualiza o ícone de **Sino** no cabeçalho superior (`<x-layout.topbar>`).
2. O script `NotificationBell.js` executa uma requisição AJAX periódica `GET /notifications/unread-count`.
3. O `NotificationController::unreadCount()` retorna o número de notificações não lidas (`read_at IS NULL`) pertencentes àquele usuário.
4. Se o contador for maior que zero, o sino exibe um **Badge Vermelho com o Número** (ex: `3`).
5. O usuário clica no ícone de Sino.
6. A interface abre o dropdown exibindo as últimas notificações não lidas com título, horário e link.
7. Ao clicar em uma notificação específica, o JavaScript dispara `PATCH /notifications/{id}/read` (marcando `read_at = now()`) e redireciona o usuário diretamente para a tela de destino (ex: o tópico do fórum ou a página do certificado).
8. O usuário também pode clicar em **"Marcar Todas como Lidas"** (`PATCH /notifications/read-all`).

---

## **6. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /notifications/unread-count`, `PATCH /notifications/read-all`, `PATCH /notifications/{notification}/read`.
* **Middleware:** `auth`.
* **Controller:** `NotificationController`.
* **JS Asset:** `public/js/modules/NotificationBell.js`.
