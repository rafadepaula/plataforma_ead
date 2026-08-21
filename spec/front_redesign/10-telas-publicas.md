# 10 — Telas públicas e de acesso

Cobertura: 7 views, 34 seletores `dusk`. Shell: `layouts/guest.blade.php` +
`components/layout/public.blade.php`.

---

## 10.1 Landing — `landing/show.blade.php` (3 dusk)

**Headline e proposta de valor atuais são preservadas** — nenhum texto de
marketing novo é escrito nesta rodada.

- **Hero** em `--blue-50`, raio 36px, padding 80px: badge, título display 44px,
  lead 19px, dois CTAs (primary + tonal) e três provas em números.
- A prova visual do produto são os **próprios componentes** — um card de curso,
  um certificado emitido, uma pergunta do fórum — não fotografia de banco.
- **"Como funciona"** em quatro cards `outlined` numerados, ícone em círculo
  pastel.
- Faixa de números em `--blue-50`, métricas 40px/800.
- Rodapé público com links institucionais.

---

## 10.2 Login — `auth/login.blade.php` (6 dusk)

`x-layout.guest-panel` (46%, `--blue-100`, brand mark + frase institucional)
+ coluna de 440px:
kicker *Acesso* / h1 "Entrar na plataforma" / e-mail / senha (com botão
`eye`/`eye-off` e `aria-label`) / "Manter-me conectado" (checkbox) /
"Esqueci minha senha" (link) / CTA em bloco (`block`, primary, 60px).

Erro de credencial: `x-ui.alert` `danger` em `--critical-container`, texto
calmo, sem revelar qual dos dois campos falhou.
Conta inativa: mensagem explicando que o acesso depende da Organização.

---

## 10.3 Recuperação de senha — `auth/forgot-password` (4), `auth/reset-password` (5)

Mesmo shell. `forgot-password`: e-mail + "Enviar link de recuperação".
Confirmação em alerta menta, sem revelar se o e-mail existe.
`reset-password`: nova senha + confirmação + regras de senha em caption acima
do campo, não só depois do erro. O link é de uso único — o alerta de link
expirado explica o próximo passo.

---

## 10.4 Convite de matrícula — `convite/show.blade.php` (8 dusk)

Duas colunas: resumo do Curso à esquerda (card com faixa em *pastel wash*,
título, Organização em overline, carga horária, descrição) e formulário de
440px à direita:
nome, e-mail (**com o aviso atual sobre vínculo a conta existente,
preservado palavra por palavra**), CPF, consentimento em `x-ui.switch`, e
"Confirmar matrícula" (primary, bloco).

Convite expirado ou esgotado: `x-ui.empty-state` com ícone `lock` e o próximo
passo ("Peça um novo link ao responsável pelo curso.").

---

## 10.5 Validação pública de certificado — `public/certificates/show.blade.php` (8 dusk)

Sem autenticação, sem drawer. Coluna de leitura de 760px.

- Kicker *Validação pública* / h1 "Certificado nº {hash}".
- Veredito em card grande: **Válido** com ícone `check` em círculo menta, ou
  **Revogado** com ícone `shield` em círculo `--critical-container` e o motivo.
  Nunca 404 para um hash revogado.
- Ficha: aluno, curso, Organização, carga horária, data de emissão
  `dd/mm/aaaa`, hash em caption monoespaçado.
- Botão "Baixar PDF" (tonal).

---

## 10.6 PDF do certificado — `certificates/pdf.blade.php` — **exceção**

**Não migra.** dompdf não suporta custom properties, flexbox nem grid.
Mantém o CSS próprio, em tabela, com valores literais. Isolar sob
`x-layout.print` para que a exceção fique explícita e ninguém a "corrija".
Qualquer alteração aqui exige verificação visual do PDF gerado, não Dusk.

## Critérios de aceite

- Textos institucionais e avisos legais preservados verbatim.
- Mensagem de erro de login não revela existência de conta.
- Hash revogado nunca resulta em 404.
- `CertificateVerificationTest` verde; PDF renderiza sem página em branco.
