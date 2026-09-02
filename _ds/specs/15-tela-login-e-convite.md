# Tela — Login e convite

**Caption/título:** *Acesso / Entrar na plataforma*
**Views de origem:** `auth/login.blade.php`, `layouts/guest.blade.php`, `convite/show.blade.php`

## Objetivo
Login: painel de marca em `--primary-surface` (`#EFF6FF`) + coluna de formulário.

## Layout e componentes
Login: e-mail, senha, manter conectado, esqueci minha senha, CTA em bloco (`#2563EB`). Convite: nome, e-mail (aviso de vínculo a conta existente), CPF, consentimento em `Switch`, "Confirmar matrícula" ao lado do resumo do curso.

## Estados
Erro de validação: `.is-invalid` + mensagem em `--error` abaixo do campo, nunca só a borda.

## Contrato `dusk`
Campos de login, botão de submit, campos do convite. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Labels sempre visíveis e associadas via ARIA; zero caixa-alta.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
