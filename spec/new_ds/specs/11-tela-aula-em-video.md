# Tela — Aula em vídeo

**Kicker/título:** *Sala de aula / Aula*
**Views de origem:** `classroom/lesson.blade.php`, `partials/_video.blade.php`

## Objetivo
Player 16:9 sobre pastel wash.

## Layout e componentes
Barra "Tempo assistido" separada do progresso do curso — texto atual preservado: salvo a cada 5s, concluída após 90%. "Marcar como concluída" em menta; depois vira chip "Aula concluída". Materiais e próximas aulas na coluna lateral.

## Estados
Vídeo indisponível: "Vídeo indisponível — avise o responsável pelo curso." Nunca culpa o aluno.

## Contrato `dusk`
Player, barra de tempo assistido, botão de marcar concluída. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
