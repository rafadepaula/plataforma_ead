# Funcionalidades Ausentes — Bloqueadores de Teste E2E

> Atualizado em 2026-08-08. Das 12 lacunas originais, **11 foram implementadas**. Resta apenas o UC02, adiado por decisão do usuário.

---

## 1. Lacunas ainda abertas

| UC | Cenario | Arquivo de Teste | Endpoint/Classe Esperada | Situação | Prioridade |
|---|---|---|---|---|---|
| UC02 | Editar nome/email/CPF com sucesso | ProfileTest.php | `GET/PATCH /profile` · `ProfileController` | **Especificado, não implementado.** Ver [SPEC-18](../specs/18-user-profile-management.md). Codificação adiada por decisão explícita do usuário. | 🔴 CRÍTICO |
| UC02 | Atualizar senha com sucesso | ProfileTest.php | `PUT /profile/password` · `PasswordController` | Idem — SPEC-18 §3 e §5 | 🔴 CRÍTICO |
| UC02 | E-mail/CPF duplicado rejeitado | ProfileTest.php | `ProfileUpdateRequest` + `App\Rules\Cpf` | Idem — SPEC-18 §3 | 🔴 CRÍTICO |
| UC02 | Senha atual incorreta retorna erro | ProfileTest.php | `PasswordUpdateRequest` (`current_password`) | Idem — SPEC-18 §3 | 🔴 CRÍTICO |
| UC02 | Não-autenticado retorna redirect /login | ProfileTest.php | middleware `auth` em `/profile` | Idem — SPEC-18 §2 | 🔴 CRÍTICO |

---

## 2. Lacunas fechadas

| UC | Cenario | O que foi feito | Commit |
|---|---|---|---|
| UC04 | Inativar usuário via UI | Select de `status` + campo `reason` em `users/edit.blade.php`, badge de status em `users/index.blade.php`. O backend (`UpdateUserRequest`, auditoria `user.status_changed`) já existia e não foi tocado. O `markTestSkipped` virou teste real, mais um provando que o usuário inativado não loga. | `f1e3f31` |
| UC05 | Cabeçalho inválido dispara alerta imediato | `CsvImporter.js` valida o header **antes** de qualquer POST, abortando sem enviar nada. | `8f91809` |
| UC05 | Colunas obrigatórias ausentes exibem "Cabeçalho inválido" | Mesma mudança. A mensagem nomeia as colunas faltantes e as encontradas. | `8f91809` |
| UC12 | Cronômetro expirado | **Comportamento mantido de propósito** — ver seção 3. Só foi adicionada a cobertura do estado "Tempo esgotado", que não existia. | `520695c` |
| UC13 | Regras de conclusão via UI | CRUD `index`/`store`/`destroy` em `/courses/{course}/completion-rules`, aninhado no curso como `courses.enrollments`, autorizado por `CoursePolicy::update`. O Form Request valida a integridade que o schema não garante: `target_id` obrigatório para `min_quiz_score`/`specific_module`, proibido para `all_lessons`, e sempre pertencente ao curso. | `fc7fe64` |
| UC13 | "Certificado indisponível. X%" | Aviso na sala de aula com o percentual de `course_user.progress_percentage`, virando link de download quando o certificado existe. | `fc7fe64` |
| UC15 | Edição de tópico via UI | Tela `forum.edit` espelhando `forum.create`, ligando na rota `forum.update` e no `EditForumPostAction` que já existiam. O histórico agora é alimentado pelo fluxo real, não por linha semeada à mão. | `e3ed430` |
| UC16 | Chave sem artigo | **Decisão revista** — ver seção 3. O botão inerte virou um modal de placeholder "Estamos preparando". | `3a2c52d` |

---

## 3. Divergências entre relatório e implementação — como foram resolvidas

Dois cenários do relatório de cobertura contradiziam decisões que o código documentava explicitamente. Cada um foi decidido em separado:

### UC12 — cronômetro do quiz: relatório perdeu, código venceu

O relatório pedia submissão automática ao zerar. O docblock de `quiz-timer.js` declara o oposto de propósito: o módulo nunca chama `submit()`, apenas troca para o estado visual "Tempo esgotado", **para que rede lenta ou aba em segundo plano nunca descartem silenciosamente as respostas do aluno**. O enforcement real é server-side em `SubmitQuizAttemptAction` (accept-but-fail, SPEC-08 §1.3).

Decidido manter o comportamento. Auto-submit por timer de cliente reintroduziria exatamente o risco que a decisão original evitava. O que faltava era cobertura, não comportamento — o estado "Tempo esgotado" nunca tinha sido testado, e agora é.

### UC16 — ajuda sem artigo: relatório venceu, código cedeu

O relatório pedia um modal "Estamos preparando...". O componente renderizava um botão `disabled` inerte, justificado no docblock como "never a broken modal, never a 500".

Decidido implementar o placeholder. A justificativa original continua válida, mas um botão morto não dá feedback nenhum — o usuário clica e nada acontece. Um modal de placeholder bem definido satisfaz o "never a broken modal" melhor que um controle inerte.

---

## 4. Nota sobre "HTTP 422" nos cenários do relatório

Vários cenários do relatório original falavam em "retorna HTTP 422". Isso só vale para requisição JSON. Todos esses fluxos são formulários Blade: o Laravel redireciona de volta com os erros na sessão, renderizados inline por `resources/views/components/ui/input.blade.php`. Os testes asserem a mensagem, não o status — asserir 422 ali seria asserir algo que a aplicação corretamente não faz.
