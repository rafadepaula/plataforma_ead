# **06. Sistema Multitenant de Convites Inteligentes e Gestão de Matrículas**

---

## **1. Visão Geral Multitenant & Requisitos**

* **RF03:** Auto-cadastro e Matrícula por Link de Convite Inteligente `/convite/{token}`.
* **RF21:** Gestão manual e revogação de matrículas via Painel do Gestor (`role:gestor`).
* **RN09 (Fluxo Adaptativo Multi-Org):** Convite vinculado ao curso/Org. Permite que um aluno já cadastrado em outra Org se matricule no curso da Org atual sem duplicar sua conta de usuário.

---

## **2. Modelo do Banco de Dados**

- **`invitation_links`**: `id`, `org_id` (FK -> `organizations.id`), `token` (64 char unique), `course_id`, `max_uses`, `current_uses`, `expires_at`, `created_by`.
- **`course_user`**: Pivô de matrícula (`user_id`, `course_id`, `enrolled_at`, `status`).

---

## **3. Architecture do Convite Adaptativo & Multi-Org**

1. Acesso à rota pública `/convite/{token}`.
2. Consulta assíncrona AJAX `/convite/check-email` averigua se o e-mail já existe na plataforma EAD.
3. Se o e-mail existir: Form jQuery oculta dados cadastrais e solicita apenas a senha do aluno.
4. `ProcessSmartInvitationAction` executa em transação com `lockForUpdate`, insere a matrícula em `course_user` associada ao curso/Org e realiza auto-login.

---

## **4. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `invitation_links` com `org_id`
- [ ] Model `InvitationLink` utilizando `OrgScope`
- [ ] Action `ProcessSmartInvitationAction` com suporte a multi-org (RN09)
- [ ] Harness: Criar/atualizar as 3 skills (`invitations-architecture`, `invitations-conventions`, `invitations-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `SmartInvitationTest.php`, `MultiOrgEnrollmentTest.php` aprovados com 100%.
