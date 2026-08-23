# Tela — Meus cursos

**Kicker/título:** *Aprendizado / Meus cursos*
**Views de origem:** `student/courses/index.blade.php`

## Objetivo
`Tabs` (Em andamento com contador / Concluídos / Todos).

## Layout e componentes
Card por matrícula: faixa pastel com chip de status, organização no overline, descrição, progresso com rótulo, ação que muda com o estado (Continuar / Começar / Baixar certificado), meta (aulas, carga, prazo).

## Estados
Vazio: "Quando sua organização matricular você em um curso, ele aparece nesta lista."

## Contrato `dusk`
Tabs (aria-selected), card por matrícula, botão de ação contextual. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
