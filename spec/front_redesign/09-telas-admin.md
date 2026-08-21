# 09 — Telas de administração e conta

Cobertura: 17 views, 82 seletores `dusk`.

> O deck de referência **não desenhou** estas telas (`DESIGN.md` §4.15). Elas
> são compostas aqui a partir da biblioteca do documento 03. Nenhuma informação,
> campo ou permissão foi inventada — tudo vem do código e das specs de módulo
> em `spec/specs/`.

---

## 9.1 Organizações — `organizations/index` (9), `create` (2), `edit` (2), `_form` (1)

Admin global apenas.

- `x-ui.data-table`: organização (brand mark de 32px com as iniciais + nome +
  slug em caption), alunos, cursos, status em chip, ações
  Abrir / Editar / **Impersonar** (tonal, ícone `shield`).
- Impersonate Org ativo pinta o chip da topbar e adiciona "Sair do contexto".
- `_form`: nome, slug, contato, logo (upload com preview em círculo pastel),
  status como `x-ui.switch`.

---

## 9.2 Usuários da organização — `users/index` (8), `create` (2), `edit` (4), `import` (9)

- `x-ui.filter-bar`: busca + papel + status.
- `x-ui.data-table`: usuário (avatar + nome + e-mail), CPF, papel em chip,
  status em chip, último acesso `dd/mm/aaaa`, ações.
- **Importação CSV** (`users/import`): zona de upload tracejada 1,5px, ícone
  `upload` 28px em círculo pastel, template para baixar, e — durante o
  processamento em chunks — `x-ui.progress` com contagem
  ("120 de 400 linhas processadas"). Erros por linha em tabela com
  `--critical-container`, nunca em vermelho.
  `CsvImporter.js` mantém seu contrato de eventos.

---

## 9.3 Usuários globais — `admin/users/index` (20), `edit` (6), `show` (11)

Admin apenas. Mesma composição de 9.2, mais a coluna **Organização** e o filtro
por organização. `show` é uma ficha em duas colunas: dados do usuário à
esquerda, matrículas e certificados em cards à direita.

Vinte seletores na `index` — é a tela com mais contrato de teste do sistema.
Conferir um a um contra `AdminUserManagementTest` antes de fechar.

---

## 9.4 Auditoria — `audit-logs/index` (12), `partials/_diff-modal` (3)

- `x-ui.filter-bar`: período, ator, tipo de evento, organização.
- `x-ui.data-table` densa (botões `sm` 40px): data `dd/mm/aaaa hh:mm`, ator,
  evento em chip `outline`, entidade, IP em caption monoespaçado, ação
  **Ver diff**.
- Diff em `x-ui.modal` (`AuditLogDiffModal.js`): duas colunas *antes* / *depois*
  em `--surface-sunken`, valores alterados destacados por **peso e container
  pastel**, nunca por vermelho/verde.
- Exportação CSV em botão tonal.

---

## 9.5 Configurações — `settings/edit.blade.php` (2 dusk)

Formulário em coluna de 760px, dividido em cards `outlined` por assunto:
identidade da organização (logo, assinatura), SMTP, preferências.
Cada preferência de efeito imediato usa `x-ui.switch`.
Campo herdado do global mostra caption "Usando o valor global" até ser
sobrescrito.

---

## 9.6 Perfil — `profile/edit.blade.php` (4 dusk)

**Dois formulários independentes**, em cards separados — a separação é
requisito, não estética:

1. **Dados pessoais** — nome, e-mail, CPF. Organização e status aparecem como
   texto estático em chip: são **imutáveis** por este endpoint.
2. **Senha** — senha atual, nova, confirmação. Alerta `info`:
   "Ao alterar sua senha, você será desconectado dos outros dispositivos."

Validação de CPF pela regra compartilhada, com `.invalid-feedback` e ícone.

## Critérios de aceite

- Nenhum campo, papel ou permissão novo introduzido.
- Diff e erros de importação nunca usam vermelho/verde como único sinal.
- Os 82 `dusk` preservados; `AdminUserManagementTest`, `UserManagementTest`,
  `OrganizationCrudTest`, `MultiTenantStudentImportTest`, `AuditLogUiTest`,
  `ImpersonateOrgTest`, `ProfileTest` verdes.
