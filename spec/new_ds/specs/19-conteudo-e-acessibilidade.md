# 19 — Conteúdo e acessibilidade

## Conteúdo e tom de voz
- Português do Brasil em tudo: interface, validações, estados vazios, ajuda.
- Substantivos do domínio com inicial maiúscula quando nomeiam a entidade:
  Organização, Curso, Módulo, Aula, Prova, Certificado, Matrícula, Aluno, Gestor.
- Segunda pessoa, voz institucional e direta: "Você atingiu o número máximo de
  tentativas (3).", "Continue de onde parou." O sistema não fala "nós".
- Rótulos são substantivos; botões são verbos no infinitivo: "Redações
  pendentes"; "Enviar prova", "Salvar alterações", "Confirmar matrícula".
- Sentence case em tudo, inclusive chips e badges. Caixa-alta só no overline.
- Toda tela abre com kicker + título; subtítulo explica o propósito em uma
  frase, 19px cinza-azulado.
- Estado vazio sempre tem próximo passo; estado degradado não culpa o usuário;
  texto destrutivo é calmo e explícito.
- Tom de urgência não existe — pendência é trabalho a fazer.
- Números em pt-BR: vírgula decimal, datas `dd/mm/aaaa`, "4 horas" (ou "4h" em
  tabelas densas). Sem emoji.

## Acessibilidade e estados
- Foco: anel sólido 3px `--focus-ring`, offset 2px; campos ganham anel 4px a
  28%. Nunca `outline: none` sem substituto.
- Camada de estado: `currentColor` 6% hover / 12% press. Botão preenchido sobe
  1px no hover; card `interactive` sobe 3px e vai a elev-3; linha de tabela
  recebe tint azul 6%.
- Desabilitado: opacidade .45 e `pointer-events: none`.
- Alvo de toque ≥ 48px em todo controle; item de drawer 52px; botão `sm` 40px
  só dentro de linha de tabela.
- Cor nunca é o único sinal: status tem rótulo em texto; ação destrutiva tem
  ícone + palavra; opção correta na autoria é checkbox, não cor.
- Semântica: `role="alert"`, `role="progressbar"` com `aria-valuenow`,
  `aria-current="page"` no item ativo do drawer, `aria-selected` nas tabs,
  `aria-pressed` nos chips de filtro, `aria-label` em todo botão só-ícone.
- Validação Laravel: `.is-invalid` no campo + mensagem em `--critical` abaixo,
  com ícone — nunca só a borda.
- `prefers-reduced-motion` afeta diálogo e drawer (nada mais anima na entrada
  de conteúdo).
