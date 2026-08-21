# 05 — Telas de gestão (Gestor)

Cobertura: 20 views, 96 seletores `dusk`.

---

## 5.1 Dashboard — `dashboard/index.blade.php` (14 dusk)

Kicker **Painel** + saudação ("Bom dia, {nome}"). Lead: uma frase sobre o
recorte da organização ativa.

- **Filtro de período em `x-ui.chip`**: 7 dias / 30 dias / este ano, com
  `aria-pressed`. Substitui o select atual.
- **Quatro `x-ui.stat-card`**: alunos ativos, certificados emitidos, taxa de
  conclusão, cursos publicados. Ícone em container pastel de 52px, métrica
  40px/800, delta em chip menta, número em pt-BR ("+4,2%").
- **Grade 2fr / 1fr**:
  - Esquerda — tabela "Matrículas recentes": aluno (`x-ui.avatar` + nome +
    e-mail em caption), curso, progresso em `x-ui.progress` de 8px, status em chip.
  - Direita — card "Precisa da sua atenção" (redações pendentes, denúncias do
    fórum, certificados prontos, cada linha com ícone em círculo pastel e
    contagem) + card "Cursos mais concluídos" em barras menta.
- **Ações do header**: "Exportar CSV" (tonal, ícone `upload`), "Novo curso"
  (primary, ícone `plus`).
- **Admin global**: a tabela "Resumo das Organizações" mantém os seletores
  `organizations-summary-table`, `organization-summary-row-{id}`,
  `org-summary-{metric}-{id}` e o gate `$isGlobalAdminView`.

Tom: pendência é trabalho a fazer, nunca urgência.

---

## 5.2 Cursos — `courses/index.blade.php` (7), `create` (2), `edit` (2), `_form`

- `x-ui.filter-bar` como card `outlined`: busca + status.
- `x-ui.data-table`: curso (título h6 + "2 módulos · 13 aulas" em caption),
  carga horária ("4 horas"), alunos, status em chip, ações agrupadas —
  **Abrir** (tonal) / **Editar** (ghost com borda) / **Remover** (`--critical`).
- `x-ui.pagination` em pílulas.
- Remover **sempre** passa por `x-ui.confirm-modal`; curso com matrícula ativa
  é protegido e a mensagem explica o porquê, sem culpar quem clicou.
- `_form` compartilhado por create/edit: `x-ui.field-stack` em coluna de 440px
  quando o formulário é simples, 760px quando tem descrição longa.
  "Publicado" vira `x-ui.switch`.

Estado vazio: "Nenhum curso cadastrado" + "Crie o primeiro curso para começar
a matricular Alunos." + botão.

---

## 5.3 Módulos — `courses/modules/index` (2), `_list` (6), `create` (2), `edit` (2), `_form`

- Lista **arrastável**: preservar `data-reorder-url` e `data-id` — contrato do
  `ModuleReorder.js`, compartilhado com lições e questões.
- Alça: ícone Lucide `grip-vertical` 20px, substituindo o caractere `⠿`.
- Linha em `--surface-sunken`, raio 14px, `--elev-1`, altura mínima 64px,
  com título, contagem de aulas em caption e ações.
- Feedback de reordenação por `bootstrap.Toast` (via `NotificationService`),
  nunca por `alert()`.

---

## 5.4 Lições — `modules/lessons/index` (6), `_form` (7), `create` (2), `edit` (2)

Formulário na ordem: título, tipo de conteúdo (`x-ui.select`), URL do YouTube
com pré-visualização em *pastel wash* 16:9, upload de imagem, upload de PDF,
"Publicado" como `x-ui.switch`.

**Hints preservados palavra por palavra**, incluindo o aviso de que o servidor
revalida o link no envio. Os tipos de conteúdo continuam sendo os mesmos quatro
do backend — nada é adicionado nem renomeado aqui.

Upload: zona com borda tracejada 1,5px, ícone `upload` 28px em círculo pastel,
nome do arquivo atual quando já existe.

---

## 5.5 Matrículas — `courses/enrollments/index.blade.php` (6 dusk)

`x-ui.data-table`: aluno (avatar + e-mail), data de matrícula `dd/mm/aaaa`,
progresso em barra, status em chip, ação de remoção via `confirm-modal`.
`x-ui.filter-bar` com busca por nome/e-mail. Paginação.

---

## 5.6 Links de convite — `courses/invitation-links/index` (4), `create` (2)

- Tabela: link (truncado, com botão só-ícone "copiar" e `aria-label`),
  validade `dd/mm/aaaa`, usos / limite, status em chip.
- Criação: formulário de 440px com validade e limite de usos.
- `SmartInvitationForm.js` continua dirigindo o comportamento; o redesign não
  muda os `id`/`name` que ele consulta.

---

## 5.7 Regras de conclusão — `courses/completion-rules/index.blade.php` (8 dusk)

Um card `outlined` por `rule_type`, com `x-ui.switch` para ativar e campo de
limiar. Alerta informativo (`info`) explicando que **todas** as regras ativas
precisam ser satisfeitas (AND) para o Certificado ser emitido.

---

## 5.8 Certificados do Gestor — `certificates/index.blade.php` (8 dusk)

> Não desenhado no deck de referência. Composto aqui a partir da biblioteca —
> nenhuma informação nova foi inventada.

`x-ui.data-table`: aluno, curso, número do certificado (`hash`, em caption
monoespaçado), data de emissão, status em chip (**Válido** menta /
**Revogado** `--critical-container`).
Ações: **Ver** (tonal, abre a página pública), **Baixar PDF** (ghost com borda),
**Revogar** (`--critical`, abre modal que pede motivo com "Mínimo de 10
caracteres.").
Revogação é terminal: a linha revogada nunca some da lista.

## Critérios de aceite (todas as telas deste documento)

- Kicker + h1 + lead em cada uma.
- Todo estado vazio tem próximo passo.
- Toda remoção passa por `confirm-modal`.
- Os 96 `dusk` preservados; `ModuleReorderTest`, `CourseManagementTest`,
  `CourseCompletionRuleTest`, `DashboardDuskTest`, `MultiOrgEnrollmentTest`,
  `CertificateRevocationTest`, `LessonMultimediaTest` verdes.
