# Tela — Fórum

**Caption/título:** *Aprendizado / Fórum*
**Views de origem:** `forum/index.blade.php`, `partials/_topic.blade.php`

## Objetivo
Card interativo por tópico + `Fab` "Novo tópico".

## Layout e componentes
Card: avatar por iniciais, badge "Fixado", título em `headline-lg`, trecho, autor + tempo relativo, contagem de respostas com ícone. Diálogo do Fab: título e conteúdo.

## Estados
Vazio: "Nenhum tópico por aqui" + "Abra o primeiro tópico e comece a conversa com a turma." + botão.

## Contrato `dusk`
Card de tópico, Fab, diálogo de novo tópico. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Chips sempre com par container / on-container; zero caixa-alta.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
