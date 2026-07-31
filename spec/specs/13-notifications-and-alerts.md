# **13. Notificações Multitenant (E-mail + In-App)**

---

## **1. Visão Geral & Requisitos**

* **RF28:** Central de Notificações In-App (sino no topbar) por usuário.
* **RF29:** Envio de E-mail transacional nos 4 gatilhos definidos em §2.
* **RN12:** Notificação in-app respeita `OrgScope` implicitamente (nunca vaza dado de outra Org, pois `notifiable` é sempre o `User` autenticado).

Utiliza o canal nativo `Notification` do Laravel (`Illuminate\Notifications\Notifiable`) com dois canais simultâneos: `mail` (fila) e `database` (tabela `notifications`, definida em SPEC-00 §2.1 item 22). Sem dependência de serviço externo (Pusher/Ably) — o sino in-app é populado por polling, mesma técnica AJAX já usada no fórum (SPEC-10 §2), não WebSockets.

---

## **2. Gatilhos (Escopo Fechado — Apenas Estes 4)**

| # | Evento | Classe de Notificação | Destinatário | Canais |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Convite enviado (`invitation_links` criado) | `InvitationSentNotification` | E-mail do convidado (usuário ainda pode não existir na base — enviado por e-mail direto via `Notification::route('mail', $email)`, sem canal `database` pois não há `User` ainda) | `mail` apenas |
| 2 | Certificado emitido (`IssueCertificateAction` concluída, SPEC-09 §1.1) | `CertificateIssuedNotification` | Aluno (`user_id` do certificado) | `mail` + `database` |
| 3 | Nova resposta em tópico de fórum (`forum_replies` criado) | `NewForumReplyNotification` | Autor do tópico + demais autores de replies anteriores no mesmo tópico, **exceto** quem respondeu agora | `mail` + `database` |
| 4 | Matrícula confirmada (`course_user` criado/`status` -> `active`) | `EnrollmentConfirmedNotification` | Aluno matriculado | `mail` + `database` |

Qualquer gatilho fora desta lista (ex.: denúncia de fórum, revogação de certificado) está **fora de escopo** desta versão — mencionado explicitamente como exclusão nas specs 09 e 10 para evitar ambiguidade.

---

## **3. Entrega em Fila (`QUEUE_CONNECTION=sync` por padrão)**

- Todas as `Notification` implementam `ShouldQueue`. Em hospedagem compartilhada (SPEC-00 §1.2), a fila roda `sync` por padrão — a notificação é processada na própria requisição; se o ambiente configurar `QUEUE_CONNECTION=database` + Cron minuto a minuto, passa a ser assíncrona sem mudança de código.
- Falha de envio de e-mail (SMTP indisponível) **não** deve reverter a transação de negócio (ex.: matrícula confirmada não é desfeita se o e-mail falhar) — notificação é `try/catch` isolado com log em `storage/logs/laravel.log`, canal `database` sempre grava independente do resultado do `mail`.

---

## **4. Central In-App (Sino no Topbar)**

- Componente Blade `<x-notifications-bell />` (catálogo do Design System, SPEC-02) integrado ao layout master, visível para `role:gestor` e `role:aluno` (Admin não recebe notificações de negócio de Org específica).
- Badge com contagem de `notifications.read_at IS NULL` do usuário autenticado, atualizado via polling AJAX a cada 30s (`GET /notifications/unread-count`).
- Dropdown lista as 10 notificações mais recentes (`ORDER BY created_at DESC`), link "marcar todas como lidas" (`PATCH /notifications/read-all`) e clique individual marca `read_at` e redireciona para o recurso (`data.action_url` armazenado no JSON).

---

## **5. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `notifications` (formato padrão Laravel, `php artisan notifications:table`)
- [ ] Classes `InvitationSentNotification`, `CertificateIssuedNotification`, `NewForumReplyNotification`, `EnrollmentConfirmedNotification`
- [ ] Listeners conectando os 4 eventos de origem (SPEC-06, SPEC-09, SPEC-10, SPEC-06) às classes de notificação acima
- [ ] Componente Blade `<x-notifications-bell />` + endpoints `GET /notifications/unread-count`, `PATCH /notifications/read-all`
- [ ] Harness: Criar/atualizar as 3 skills (`notifications-architecture`, `notifications-conventions`, `notifications-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `NotificationTriggersTest.php`, `NotificationBellTest.php` aprovados com 100%.
