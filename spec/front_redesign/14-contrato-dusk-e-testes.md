# 14 — Contrato `dusk` e testes

**388 seletores `dusk="…"`** em `resources/views/` precisam sobreviver
**verbatim** durante todo o redesign: nenhum renomeado, nenhum removido,
nenhum movido para um elemento com semântica diferente.
**25 arquivos** em `tests/Browser/` dependem deles.

## Distribuição por camada

| Camada | Seletores |
|---|---:|
| Componentes (`components/**`) | 42 |
| Telas de gestão | 96 |
| Telas de avaliação | 50 |
| Telas do Aluno | 29 |
| Fórum | 50 |
| Administração e conta | 82 |
| Públicas e acesso | 34 |

## Telas com maior contrato (conferir uma a uma)

`admin/users/index` 20 · `forum/show` 17 · `dashboard/index` 14 ·
`student/quizzes/show` 13 · `audit-logs/index` 12 · `admin/users/show` 11 ·
`quizzes/partials/_question-form` 11.

## Regras práticas

- O `dusk` vive **no mesmo nó semântico** de antes — no `<button>`, não no
  wrapper que o envolve.
- Wrappers novos introduzidos pela linguagem visual (`.ds-field-shell`,
  `.ds-table-wrap`, `.ds-card-body`, `.app-main`) **não** recebem `dusk`.
- Listas arrastáveis mantêm `data-reorder-url` e `data-id` — o JS de
  reordenação é compartilhado por módulos, lições e questões.
- Em tabela que vira lista de cards no mobile, o `dusk` existe em **uma** das
  variantes por vez, nunca duplicado.
- Antes de tocar um arquivo: listar os seletores nele
  (`grep -o 'dusk="[^"]*"'`); depois da mudança: confirmar que a lista é
  idêntica.

## Gate por tarefa

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan dusk --filter=<TesteDaTelaTocada>
```

`npm run build` **antes** do Dusk, sempre. `public/build/` desatualizado é a
causa mais comum de teste que falha por motivo errado.

## Gate por fase

Cada fase de `15-plano-de-fases.md` termina com `artisan dusk` filtrado nas
telas tocadas. **A fase de fundação e a do shell exigem a suíte completa**,
porque toda tela depende delas.

## Testes novos exigidos por este redesign

- **Regressão de tokens**: teste que falha se o CSS compilado contiver matiz
  vermelha, laranja ou amarela, ou `border-radius: 0`.
- **Regressão de inline**: teste que falha se `resources/views/` contiver
  `style="` fora de `certificates/pdf.blade.php`.
- **Contrato de seletores**: teste que compara a contagem e o conjunto de
  `dusk=` contra um snapshot versionado.
- **Mobile**: Dusk com `resize(375, 812)` em Meus cursos, Sala de aula, Aula e
  Prova do Aluno.

Nenhum teste existente pode ser removido ou afrouxado para acomodar o redesign.
