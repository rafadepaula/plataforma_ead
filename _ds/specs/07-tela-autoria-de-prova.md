# Tela — Autoria de prova

**Caption/título:** *Gestão / Autoria de prova*
**Views de origem:** `quizzes/edit.blade.php`, `partials/_question-form.blade.php`, `_question-list.blade.php`

## Objetivo
Coluna esquerda: regras da prova. Coluna direita: lista de questões arrastável.

## Layout e componentes
Regras: título, instruções, máximo de tentativas, limite de tempo, nota mínima, permitir novas tentativas, exibir gabarito — hints preservados palavra por palavra. Questões: tipo em chip outline; diálogo de questão com enunciado, tipo (única escolha / múltipla escolha / verdadeiro ou falso / dissertativa) e opções com checkbox de correta, adicionar/remover linha.

## Estados
Nenhum valor de regra pode ficar sem hint explicativo — copiar do Blade original.

## Contrato `dusk`
Itens da lista de questões, diálogo de edição, checkboxes de opção correta. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Chips sempre com par container / on-container; zero caixa-alta.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
