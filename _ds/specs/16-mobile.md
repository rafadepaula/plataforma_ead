# Tela — Mobile

**Caption/título:** *—*
**Views de origem:** `resources/views/components/layout/sidebar.blade.php`

## Objetivo
Drawer como painel 280px sobre scrim, entrando em 200ms.

## Layout e componentes
Hoje o drawer nunca abre (C10, C13 — Alpine sem Alpine instalado); resolver isso é pré-requisito, não só trocar CSS. App bar reduzida (menu, marca curta, sino). Conteúdo em uma coluna, sem reduzir tamanho de texto (mínimo `body-sm` 14px). Alvo de toque ≥ 48px. Margens laterais `--margin-mobile` (24px).

## Estados
Drawer fechado por padrão, abre ao toque no menu.

## Contrato `dusk`
Botão de menu, drawer, itens de navegação — mesmos seletores do drawer desktop. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Todo valor visual via token (raiz `DESIGN.md` §6); zero caixa-alta.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
