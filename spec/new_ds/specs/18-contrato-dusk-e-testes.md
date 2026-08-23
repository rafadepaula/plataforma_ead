# 18 — Contrato `dusk` e testes

**316 seletores `dusk="..."`** em `resources/views/` precisam sobreviver
**verbatim** durante toda a migração: nenhum renomeado, nenhum removido,
nenhum movido para um elemento com semântica diferente. 76 vivem na camada de
componente (`spec/front_migration/02-component-inventory.md` §7 no repositório
de origem), o resto por tela (`03-screen-inventory.md` §1–18). 25 arquivos em
`tests/Browser/` dependem deles.

## Regras práticas
- O `dusk` vive no mesmo nó semântico de antes (o `<button>`, não o wrapper).
- Wrappers novos introduzidos pela linguagem visual (`.ds-field-shell`,
  `.ds-table-wrap`, `.ds-card-body`) **não** recebem `dusk`.
- Listas arrastáveis mantêm `data-reorder-url` e `data-id` — o JS de
  reordenação é compartilhado por módulos, lições e questões.
- Ao migrar um componente ou tela, primeiro liste os seletores tocados
  (consultar o inventário de origem), depois confirme que cada um ainda existe
  após a mudança, no mesmo nó.

## Gate por fase
Cada fase do plano (`17-plano-de-fases.md`) termina com `artisan dusk`
filtrado nas telas tocadas nessa fase. A fase 1 (shell) exige a suíte completa,
pois toda tela depende dela.
