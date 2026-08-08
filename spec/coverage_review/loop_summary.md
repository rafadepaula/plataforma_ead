# Resumo do Loop de Cobertura E2E

> Atualizado em 2026-08-08. Duas fases: primeiro o loop de cobertura sobre os 47 cenários faltantes, depois a implementação das lacunas de funcionalidade que ele revelou.

---

## Fase 1 — Loop de cobertura (47 cenários)

| Métrica | Qtd |
|---|---|
| Total processados | 47 |
| ✅ PASS | 33 |
| ❌ FAIL (funcionalidade ausente) | 12 |
| 🐛 Bug real no sistema | 0 |
| ❓ Incerto (3 tentativas esgotadas) | 0 |

34 métodos Dusk novos em 12 arquivos, sendo 2 arquivos novos (`UserManagementTest.php`, `LessonMultimediaTest.php`). Apenas 1 teste precisou de correção pelo agente implementador (cenário 42, 1 de 3 tentativas permitidas).

---

## Fase 2 — Implementação das lacunas

Das 12 lacunas, **11 foram fechadas**. A 12ª (UC02) foi especificada e teve a codificação adiada por decisão do usuário.

| UC | Lacuna | Commit |
|---|---|---|
| UC15 | Tela de edição de tópico do fórum | `e3ed430` |
| UC04 | Controle de status + badge na listagem | `f1e3f31` |
| UC05 | Validação de cabeçalho do CSV (2 cenários) | `8f91809` |
| UC16 | Modal de placeholder da ajuda | `3a2c52d` |
| UC12 | Cobertura do estado "Tempo esgotado" (sem mudar comportamento) | `520695c` |
| UC13 | CRUD de regras de conclusão + aviso "Certificado indisponível. X%" | `fc7fe64` |
| UC02 | **Adiado** — [SPEC-18](../specs/18-user-profile-management.md) escrita, zero linhas de código | `6ee0231` (só a spec) |

### Estado final da cobertura por UC

Todos os UCs do relatório original estão agora integralmente cobertos, **exceto o UC02**, que não tem implementação.

---

## Decisões de projeto tomadas no caminho

Registradas em detalhe em `missing_functionalities.md` §3. Em resumo:

* **UC12** — auto-submit do cronômetro **não** foi implementado. O código documentava a decisão oposta de propósito (submit por timer de cliente descarta respostas em rede lenta ou aba em segundo plano); o enforcement é server-side accept-but-fail. Faltava cobertura, não comportamento.
* **UC16** — o botão de ajuda inerte **foi** substituído por modal de placeholder. Um controle morto não dá feedback; o placeholder atende melhor à intenção original de "never a broken modal".
* **UC13** — regras de conclusão ficaram em rota aninhada no curso, reaproveitando `CoursePolicy::update`, sem Policy nova.
* **UC13** — para o aluno baixar o próprio certificado, `certificates.download` saiu de `role:admin|gestor` para `auth`, e o controller passou a autorizar **dono-ou-staff**. Aluno não-dono continua bloqueado; a checagem de org do Gestor não mudou.
* **UC02** — CPF com dígito verificador aplicado uniformemente, e-mail sem re-verificação, troca de senha invalidando outras sessões. Tudo especificado, nada codificado.

---

## Bugs de teste encontrados e corrigidos

Dois falsos verdes / falsos vermelhos que valem registro, porque ambos se repetem com facilidade neste projeto:

1. **`test_an_existing_user_with_a_wrong_password_is_not_enrolled`** passou isolado na primeira execução e depois falhou 2 de 3. Causa real: `SmartInvitationForm` registra handler de `blur` **e** um de `input` com debounce de 400ms. Um segundo `checkEmail` fica pendente quando o formulário termina de colapsar; submeter antes disso deixa o `toggleFields` atrasado restaurar o `required` do `password_confirmation` oculto, o que bloqueia o submit silenciosamente. Corrigido com `pause(700)`; 4/4 verdes depois.

2. **`test_gestor_can_deactivate_a_user_via_the_ui`** falhava asserindo "Inativo" dentro do badge, mesmo com o banco já em `inactive`. Causa: `<x-ui.badge>` aplica `text-transform: uppercase`, e o `getText()` do Selenium retorna o texto **renderizado** — o badge lê "INATIVO". Bug de teste, não de produto.

---

## Restrições de ambiente que custaram tempo

* **Banco `testing` único e compartilhado.** Todo processo Dusk roda `DatabaseMigrations` contra o mesmo MySQL. Duas execuções simultâneas produzem `table already exists` / `doesn't exist` / deadlocks que **parecem** bugs de aplicação. Verificação Dusk é serial por natureza — não paralelizar, e não delegar a agentes concorrentes.
* **Execução Dusk interrompida deixa o `.env` trocado** para `APP_ENV=dusk`/`DB_DATABASE=testing`, com um `.env.backup` órfão. Todo comando não-Dusk passa a mirar o banco de testes silenciosamente. Conferir antes de começar.
* **Suíte Dusk completa leva ~18 min.** Rodar em background.

---

## Pendências conhecidas

* **UC02 / SPEC-18** — implementar quando liberado.
* **`DashboardDuskTest::test_gestor_persists_a_settings_override_via_the_edit_screen`** — flake pré-existente, não introduzido por este trabalho: falha na suíte completa com "Waited 5 seconds for page reload", passa isolado. Não investigado.
