# 19 — Conteúdo e acessibilidade

## Conteúdo e tom de voz
- Português do Brasil em tudo: interface, validações, estados vazios, ajuda.
- Substantivos do domínio com inicial maiúscula quando nomeiam a entidade:
  Organização, Curso, Módulo, Aula, Prova, Certificado, Matrícula, Aluno, Gestor.
- Segunda pessoa, voz institucional e direta: "Você atingiu o número máximo de
  tentativas (3).", "Continue de onde parou." O sistema não fala "nós".
- Rótulos são substantivos; botões são verbos no infinitivo: "Redações
  pendentes"; "Enviar prova", "Salvar alterações", "Confirmar matrícula".
- Sentence case em tudo, inclusive chips e badges.
- Toda tela abre com caption + título; subtítulo explica o propósito em uma
  frase, em `--on-surface-variant`.
- Estado vazio sempre tem próximo passo; estado degradado não culpa o usuário;
  texto destrutivo é calmo e explícito.
- `--warning #F59E0B` sinaliza prazo e aviso, não pânico — pendência é
  trabalho a fazer.
- Números em pt-BR: vírgula decimal, datas `dd/mm/aaaa`, "4 horas" (ou "4h" em
  tabelas densas). Sem emoji.

## Acessibilidade e estados
- Foco: anel sólido de 2px `#2563EB` com offset de 2px, em todo controle.
  Nunca `outline: none` sem substituto.
- Camada de estado: hover de linha com `--primary-surface`; botão primary
  escurece para `#1D4ED8` no hover e volta no press; card `interactive`
  eleva a sombra. Transições de 200ms.
- Desabilitado: opacidade .45 e `pointer-events: none`.
- Alvo de toque ≥ 48px em todo controle; botão `sm` 40px
  só dentro de linha de tabela.
- Cor nunca é o único sinal: status tem rótulo em texto; ação destrutiva tem
  ícone + palavra; opção correta na autoria é checkbox, não cor.
- Semântica: `role="alert"`, `role="progressbar"` com `aria-valuenow`,
  `aria-current="page"` no item ativo do drawer, `aria-selected` nas tabs,
  `aria-pressed` nos chips de filtro, `aria-label` em todo botão só-ícone.
- Validação Laravel: `.is-invalid` no campo + mensagem em `--error` abaixo,
  com ícone — nunca só a borda.
- Contraste WCAG AA (mínimo 4,5:1) em todo texto; chip sempre com par
  container / on-container.
- `prefers-reduced-motion` afeta diálogo e drawer (nada mais anima na entrada
  de conteúdo).
