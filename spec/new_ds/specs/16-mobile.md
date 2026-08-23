# Tela — Mobile

**Kicker/título:** *—*
**Views de origem:** `resources/views/components/layout/sidebar.blade.php`

## Objetivo
Drawer como painel 280px sobre scrim 42%, entrando em 320ms.

## Layout e componentes
Hoje o drawer nunca abre (C10, C13 — Alpine sem Alpine instalado); resolver isso é pré-requisito, não só trocar CSS. App bar reduzida a 64px (menu, marca curta, sino). Conteúdo em uma coluna, sem reduzir tamanho de texto. Alvo de toque ≥ 48px.

## Estados
Drawer fechado por padrão, abre ao toque no menu.

## Contrato `dusk`
Botão de menu, drawer, itens de navegação — mesmos seletores do drawer desktop. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
