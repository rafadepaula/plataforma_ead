# 08 — Fórum

Cobertura: 8 views, 50 seletores `dusk`. `ForumPolling.js` e
`ForumReportModal.js` dependem do DOM — risco alto.

---

## 8.1 Listagem — `forum/index.blade.php` (6 dusk), `partials/_topic` (5)

- Um `x-ui.card` `interactive` por tópico:
  `x-ui.avatar` por iniciais + título h4 + trecho de duas linhas + autor e
  tempo relativo em caption + contagem de respostas com ícone `message-square`.
- Badge **"Fixado"** em chip primary quando aplicável.
- **`x-ui.fab`** "Novo tópico" (56px, `plus`, canto inferior direito) abre
  `x-ui.modal` com título e conteúdo. Em desktop o FAB coexiste com o botão do
  PageHeader; em mobile só o FAB.
- Estado vazio: "Nenhum tópico por aqui" + "Abra o primeiro tópico e comece a
  conversa com a turma." + botão.

---

## 8.2 Tópico — `forum/show.blade.php` (17 dusk), `partials/_reply` (5)

- Post inicial em card `elevated`; respostas em lista com divisores.
- Cada post: avatar 44px, autor, papel em chip `outline`, tempo relativo,
  corpo, e ações — Responder, Editar, Denunciar, Remover (Gestor/Admin).
- **"editado" com link para o histórico** quando há edições: abre
  `x-ui.modal` (`partials/_edit-history-modal`, 3 dusk) listando cada versão
  com data. O histórico é público e não tem prazo.
- Denúncia: `ForumReportModal.js` continua dirigindo; o diálogo ganha a casca
  nova, com motivo obrigatório e texto calmo.
- **Polling** (`ForumPolling.js`): respostas novas entram sem recarregar. A
  entrada **não anima** (regra do §Movimento); um chip discreto
  "2 novas respostas" aparece e some ao clicar.
- Post removido mantém o lugar com "Esta resposta foi removida pela
  moderação." em `--surface-sunken` — nunca some silenciosamente.

---

## 8.3 Criar e editar — `forum/create.blade.php` (4), `forum/edit.blade.php` (4)

Coluna de 760px: título (`x-ui.input`) + conteúdo (`x-ui.textarea` alta) +
`x-ui.form-actions`. Aviso em caption de que o conteúdo é sanitizado no
servidor. Editar mostra alerta `info`: "Sua edição fica registrada no histórico
público deste tópico."

---

## 8.4 Moderação — `forum/moderation/index.blade.php` (6 dusk)

`x-ui.data-table` da fila de denúncias: conteúdo denunciado (trecho + link),
autor, quem denunciou, motivo, data. Ações: **Manter** (ghost com borda) e
**Remover** (`--critical`, via `confirm-modal`).
Título *Acompanhamento / Moderação do fórum*. Contagem em chip menta, não em
alerta de urgência.

## Critérios de aceite

- Os 50 `dusk` preservados; `ForumDuskTest` verde.
- Polling não anima conteúdo entrando.
- Histórico de edição continua público e sem prazo.
- CSS e JS em commits separados.
