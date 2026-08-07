# Resumo do Loop de Cobertura E2E

> Gerado em: 2026-08-07 | 47 cenários da lista de lacunas do `coverage_report.md`

## Números

| Métrica | Qtd |
|---|---|
| Total processados | 47 |
| ✅ PASS | 33 |
| ❌ FAIL (funcionalidade ausente) | 12 |
| 🐛 Bug real no sistema | 0 |
| ❓ Incerto (3 tentativas esgotadas) | 0 |
| ⏭️ Skipped registrado no código | 1 |
| ⚠️ Parcial (metade coberta, metade ausente) | 1 |

Foram criados **34 métodos de teste Dusk novos** (33 executáveis + 1 `markTestSkipped`) distribuídos em 12 arquivos, sendo 2 arquivos novos.

Apenas 1 teste precisou de correção pelo agente implementador (cenário 42, 1 de 3 tentativas permitidas): o título do artigo é renderizado no cabeçalho do modal, fora do elemento `help-article-content`.

## Arquivos novos

- `tests/Browser/UserManagementTest.php` (UC04)
- `tests/Browser/LessonMultimediaTest.php` (UC08)

## Arquivos ampliados

`Auth/LoginTest.php`, `MultiOrgEnrollmentTest.php`, `CourseManagementTest.php`, `MultiTenantStudentImportTest.php`, `ImpersonateOrgTest.php`, `EssayGradingScreenTest.php`, `StudentQuizAttemptTest.php`, `ForumDuskTest.php`, `HelpCenterDuskTest.php`, `ExampleSmokeTest.php`, `DashboardDuskTest.php`, `MultiOrgStudentClassroomTest.php`.

## UCs fechados neste loop

UC01, UC03, UC04 (exceto inativação), UC05 (exceto validação de cabeçalho), UC06, UC07, UC08, UC09, UC10, UC11, UC15 (exceto edição via UI), UC16 (exceto placeholder de artigo), UC17, UC18.

## Lacunas de funcionalidade encontradas (12)

Detalhamento completo em `missing_functionalities.md`. Agrupadas por natureza:

**Funcionalidade inexistente (8)**
- UC02 — gestão de perfil do usuário: 5 cenários, zero código (sem rota, controller ou view).
- UC04 — inativação de usuário: backend pronto (`UpdateUserRequest` aceita `status`, auditoria `user.status_changed` implementada), UI não expõe o controle.
- UC13 — configuração de regras de conclusão: model e factory existem, sem rota/controller/view.
- UC13 — mensagem "Certificado indisponível. X%": não existe tela de aluno com o aviso.

**Validação ausente (2)**
- UC05 — `CsvImporter.js` não valida o cabeçalho do CSV de forma alguma; ambos os cenários de header inválido dependem disso.

**Divergência intencional entre spec e implementação (2)**

Estes dois merecem uma decisão explícita — o código documenta a escolha oposta ao que o relatório de cobertura pede:

- UC12 — `quiz-timer.js` **deliberadamente** nunca chama `submit()` ao zerar, para que rede lenta ou aba em segundo plano não descartem respostas; o enforcement é server-side accept-but-fail (SPEC-08 §1.3).
- UC16 — `help-button.blade.php` renderiza botão inerte/`disabled` quando não há artigo, explicitamente para evitar "modal quebrado"; não existe placeholder "Estamos preparando...".

**Parcial (1)**
- UC15 — a rota `forum.update` e `EditForumPostAction` existem, mas nenhuma view renderiza form de edição de tópico. O histórico de edições está implementado e foi coberto.

## Observações técnicas relevantes

- **Sem 422 literal em formulários HTML**: os cenários que pediam "HTTP 422" (11, 24, 33) são formulários Blade, não JSON. O Laravel redireciona de volta com os erros na sessão; os testes asseram a mensagem de validação exata renderizada inline por `components/ui/input.blade.php`. O 422 literal só ocorreria via requisição JSON.
- **Fluxo de reset de senha**: o ambiente Dusk (`.env.dusk.local`) não define `MAIL_MAILER`, então o e-mail vai para o log e o token não é alcançável pelo browser. O teste cobre o envio pela UI (mensagem de status + linha em `password_reset_tokens`) e gera o token com `Password::broker()->createToken()` para completar o reset e o login pela UI.
- **Convite inválido**: o handler em `bootstrap/app.php` devolve texto puro com HTTP 404, não uma view `invitations.invalid` como o relatório supunha. Os testes asseram a mensagem real.
- **403 sem mensagem customizada**: não existe `resources/views/errors/403.blade.php`; os cenários que pediam "403 com mensagem específica" asseram a página 403 padrão do Laravel.
