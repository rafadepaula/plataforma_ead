# Redesign de Frontend — Plataforma EAD

Especificação executável para redesenhar **todo** o frontend da Plataforma EAD
sobre o **Plataforma EAD Design System** (direção *Material Bootstrap*: azul e
menta pastéis, cantos suaves, elevação tonal, base 16px, espaçamento generoso).

Substitui integralmente o tema **Modernist** (cinza quente, `#ec3013`, raio 0,
Archivo 800). O comportamento, a informação e o vocabulário continuam vindo do
código-fonte; apenas a aparência é nova.

## Estado real medido do repositório (2026-08-19)

| Fato | Número |
|---|---:|
| Views Blade | 94 |
| Seletores `dusk="…"` | 388 |
| `style=` inline remanescentes | 11 |
| Componentes `x-ui.*` / `x-layout.*` / avulsos | 30 |
| Módulos JS (`resources/js/modules/`) | 13 |
| Arquivos de teste em `tests/Browser/` | 25 |
| Entrada SCSS | `resources/scss/app.scss` — apenas `@import "bootstrap/scss/bootstrap"` |

A migração para Bootstrap 5.3 (`spec/front_migration/`) **já aconteceu**: o
bundle JS do Bootstrap está carregado, `data-bs-*` funciona, o inline caiu de
634 para 11. O que **não** existe é camada de tema: hoje o produto renderiza
Bootstrap de fábrica. Este redesign é a camada que faltava, mais a recomposição
de cada tela.

## Fonte de verdade de design

`_ds/plataforma-ead-design-system/` — `styles.css` + `tokens/*.css`
(cores, tipografia, espaço, elevação, movimento, primitivas, fontes).
Nenhum valor de cor, raio, sombra ou espaço pode ser escrito fora dessa camada.

Projeto de design de origem:
`https://claude.ai/design/p/77541eab-4b41-4297-88f5-6f43c0ab86e0`.

## Documentos

### Fundação
- `01-direcao-visual-e-tokens.md` — a linguagem visual completa e os tokens.
- `02-camada-de-tema-e-build.md` — SCSS, mapeamento token → variável Bootstrap, Vite, fontes.
- `03-biblioteca-de-componentes.md` — os 30 componentes existentes (DE→PARA) + 5 adições.
- `04-shell-e-navegacao.md` — app bar, drawer, page header, footer, `NavigationRegistry`.

### Telas (cobertura de 100% das 94 views)
- `05-telas-gestao.md` — dashboard, cursos, módulos, lições, matrículas, convites, regras, certificados.
- `06-telas-avaliacao.md` — autoria de prova, correção de dissertativas, prova do aluno.
- `07-telas-aluno.md` — meus cursos, sala de aula, aula em vídeo/PDF/texto.
- `08-telas-forum.md` — listagem, tópico, criação/edição, moderação.
- `09-telas-admin.md` — organizações, usuários, importação, auditoria, configurações, perfil.
- `10-telas-publicas.md` — landing, login, recuperação de senha, convite, validação de certificado, PDF.

### Transversal
- `11-mobile-e-responsivo.md`
- `12-conteudo-e-tom-de-voz.md`
- `13-acessibilidade-e-estados.md`
- `14-contrato-dusk-e-testes.md`
- `15-plano-de-fases.md`

### Apêndice
- `A-cobertura-de-views.md` — as 94 views, contagem de `dusk` e documento responsável.

## Regras duras (valem em todo documento)

1. A camada de tokens é a fonte única de verdade de design.
2. Zero `style=` inline no estado final — os 11 restantes morrem.
3. Zero JS artesanal onde o Bootstrap já entrega (Modal, Toast, Dropdown, Collapse, Offcanvas).
4. 2+ ocorrências do mesmo markup = componente Blade.
5. Os **388** seletores `dusk=` sobrevivem verbatim, no mesmo nó semântico.
6. Toda tarefa termina com `vendor/bin/sail npm run build` + Dusk filtrado na tela tocada.
7. Vermelho, laranja e amarelo estão proibidos no sistema.
8. Sem emoji na interface.
