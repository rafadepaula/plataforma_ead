# Tela — Correção de dissertativas

**Kicker/título:** *Gestão / Redações pendentes*
**Views de origem:** `quizzes/attempts/pending.blade.php`, `attempts/show.blade.php`

## Objetivo
Fila (aluno, prova, data `dd/mm/aaaa hh:mm`, ação Corrigir) + tela de correção.

## Layout e componentes
Resposta do aluno em superfície afundada com texto preservado. Veredito em par de rádios Correta/Incorreta. Objetivas já corrigidas listadas com chip de resultado.

## Estados
Toda dissertativa da tentativa precisa de veredito antes de salvar — validação bloqueante, não só visual.

## Contrato `dusk`
Linha da fila, botão Corrigir, rádios de veredito, botão Salvar. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
