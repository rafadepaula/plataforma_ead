# 15 — Plano de fases

| Fase | Escopo | Documento | Paralelismo | Risco |
|---|---|---|---|---|
| **0** | Tokens, ponte SCSS, Vite, fontes, remoção do Archivo | `01`, `02` | Serial | Médio — toca o build |
| **1** | Shell: app bar, drawer, page header, footer, guest | `04` | Serial (1 PR) | **Alto** — tudo depende |
| **2** | 30 componentes + 5 adições | `03` | **Alto** (1 arquivo por agente) | Médio |
| **3** | Telas de listagem somente leitura | `05`, `09` | **Alto** | Baixo |
| **4** | Telas de create/edit e formulários | `05`, `09` | Médio (conflito em `_form`) | Médio |
| **5** | Aluno e avaliação (JS-heavy) | `06`, `07` | Baixo | **Alto** |
| **6** | Fórum (JS-heavy) | `08` | Baixo | **Alto** |
| **7** | Públicas e acesso | `10` | Alto | Baixo |
| **8** | Mobile, acessibilidade, polimento | `11`, `13` | Médio | Médio |

## Princípios de execução

1. A camada de tokens é a fonte única de verdade de design.
2. Zero `style=` inline no estado final (os 11 restantes morrem).
3. Zero JS artesanal onde o Bootstrap já entrega.
4. 2+ ocorrências do mesmo markup = componente.
5. `dusk=` imutável — 388 seletores.
6. Toda tarefa termina com build + Dusk filtrado.
7. **Em tela JS-heavy, casca visual e comportamento nunca no mesmo commit**:
   CSS primeiro, Dusk, JS depois, Dusk de novo.

## Ordem de adoção visual

tokens → shell → componentes de dados → telas de listagem → formulários →
telas JS-heavy → públicas → mobile e polimento.

## Riscos concentrados

- **Fase 1** derruba a suíte inteira se o contrato do shell quebrar. Gate:
  suíte Dusk completa, não filtrada.
- **Fases 5 e 6**: `LessonPlayer`, `QuizTimer`, `QuizBuilder`, `ForumPolling`,
  `ForumReportModal` consultam o DOM. Cada um tem um passo de verificação
  próprio.
- **Fase 4**: `_form` compartilhado por create e edit em cursos, módulos,
  lições, organizações e usuários — serializar por família de tela.

## Critério de saída do projeto

- Nenhum literal de cor, raio ou sombra fora de `_bridge.scss` e `_ds/`.
- Nenhum `style=` inline fora de `certificates/pdf.blade.php`.
- 388 seletores `dusk` intactos; 25 arquivos de `tests/Browser/` verdes.
- Nenhuma tela sem kicker + h1 + lead.
- Nenhum estado vazio sem próximo passo.
- Nenhuma tela com scroll horizontal em 320px.
