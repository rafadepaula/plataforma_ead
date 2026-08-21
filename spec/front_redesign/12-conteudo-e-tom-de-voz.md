# 12 — Conteúdo e tom de voz

- **Português do Brasil em tudo**: interface, validações, estados vazios, ajuda
  contextual, e-mails.
- Substantivos do domínio com **inicial maiúscula** quando nomeiam a entidade:
  Organização, Curso, Módulo, Aula, Prova, Certificado, Matrícula, Aluno, Gestor.
- **Segunda pessoa, voz institucional e direta.** O usuário é *você*:
  "Você atingiu o número máximo de tentativas (3).", "Continue de onde parou.",
  "Sua nota preliminar é 80%." O sistema **não fala "nós"**.
- **Rótulos são substantivos; botões são verbos no infinitivo.**
  Navegação: "Cursos e módulos", "Redações pendentes", "Moderação do fórum",
  "Auditoria". Botões: "Novo curso", "Enviar prova", "Confirmar matrícula",
  "Baixar certificado", "Salvar alterações", "Sair do contexto".
- **Sentence case em tudo**, inclusive chips e badges: "Publicado",
  "Em andamento", "Concluído". A **única** caixa-alta é o `overline`.
- **Toda tela abre com kicker + título**; o subtítulo explica o propósito em
  uma frase, em 19px cinza-azulado.
- **Estado vazio sempre tem próximo passo** — nunca só a constatação seca.
  Exceção: quando o usuário não tem poder de agir (Aluno sem matrícula), a
  frase explica quem age, sem botão.
- **Estado degradado não culpa o usuário**: "Estamos preparando o conteúdo de
  ajuda desta tela.", "Vídeo indisponível — avise o responsável pelo curso."
- **Texto destrutivo é calmo e explícito**: "Esta ação não poderá ser desfeita.
  Deseja continuar?" A revogação de Certificado pede motivo, com "Mínimo de 10
  caracteres."
- **Tom de urgência não existe.** Nada é "urgente", "atenção!", "erro crítico".
  Pendência é trabalho a fazer: "Precisa da sua atenção",
  "4 redações aguardando correção".
- **Números em pt-BR**: vírgula decimal ("+4,2%"), datas `dd/mm/aaaa`, carga
  horária como "4 horas" (ou "4h" em tabelas densas).
- **Sem emoji na interface.**

## O que não muda nesta rodada

Hints de formulário, avisos legais, textos de convite e a proposta de valor da
landing são **preservados palavra por palavra**. O redesign é visual; reescrever
conteúdo é outra decisão, com outro dono.

## Critério de aceite

Nenhuma string de interface alterada sem que a mudança esteja listada
explicitamente no documento de tela correspondente.
