# Tela — Landing

**Kicker/título:** *—*
**Views de origem:** `landing/show.blade.php`

## Objetivo
Hero em --blue-50 (raio 36px): badge, display 44px, lead, dois CTAs, três provas em número.

## Layout e componentes
Prova visual do produto são os próprios componentes (card de curso, certificado emitido, pergunta do fórum) — nunca fotografia. Seção "Como funciona" em 4 cards + faixa de números em --blue-50.

## Estados
Headline e proposta de valor preservados verbatim.

## Contrato `dusk`
CTAs do hero, links de navegação. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
