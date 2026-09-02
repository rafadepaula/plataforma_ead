# 17 — Plano de fases

| Fase | Onda | Escopo | Risco |
|---|---|---|---|
| 0 | Fundação | Pipeline de build, SCSS, fontes, paginador (`01-tokens-e-build.md`) | Baixo |
| 1 | A | Shell (`03-shell.md`) + 16 componentes existentes (`02-inventario-de-componentes.md`) | Médio |
| 2 | B | Parciais compartilhados e formulários reutilizáveis (FieldStack, FormActions, FilterBar) | Baixo |
| 3 | C | Telas index somente leitura (~15): Dashboard, Cursos e módulos, Meus cursos | Baixo |
| 4 | D | Telas create/edit (~15): Módulos e lições, Autoria de prova, Correção de redações | Médio |
| 5 | E | Sala de aula, Aula em vídeo, Prova do aluno, Fórum — JS-heavy | **Alto** |
| 6 | F | Landing, Login e convite | Baixo |
| 7 | Polish | Componentes novos (Fab, Chip, Switch, Tabs, Avatar) + verificação final | Médio |

## Princípios de execução
1. A camada de tokens (`DESIGN.md` na **raiz do repositório**, §6) é a fonte única de verdade de design.
2. Zero `style=` inline no estado final.
3. Zero JS artesanal onde a biblioteca já entrega (Modal, Toast, Dropdown).
4. 2+ ocorrências = componente.
5. `dusk=` imutável.
6. Toda tarefa termina com build + Dusk filtrado.

## Ordem sugerida de adoção visual
tokens → shell → componentes de dados → telas de listagem → formulários →
telas JS-heavy → públicas.

## Riscos concentrados na onda E
Sala de aula, prova e fórum têm JS artesanal (player de vídeo, timer de prova,
diálogo do Fab). Não trocar casca visual e comportamento no mesmo commit —
migrar CSS primeiro, JS depois, com Dusk entre os dois passos.
