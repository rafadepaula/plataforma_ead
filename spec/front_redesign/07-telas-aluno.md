# 07 — Telas do Aluno

Cobertura: 7 views, 29 seletores `dusk`.

---

## 7.1 Meus cursos — `student/courses/index.blade.php` (5 dusk)

Kicker *Aprendizado* / h1 "Meus cursos" / lead: "Suas matrículas em todas as
organizações, com progresso em tempo real."

- **`x-ui.tabs`** em pílulas segmentadas: **Em andamento** (com contador) /
  **Concluídos** / **Todos**, com `aria-selected`. Substitui o filtro atual.
- Um `x-ui.card` `interactive` por matrícula, em grade responsiva:
  - Faixa de mídia de 168px em *pastel wash* com chip de status sobreposto.
  - Overline: nome da **Organização** (o Aluno pode ter matrícula em várias).
  - Título h4 + descrição em duas linhas.
  - `x-ui.progress` menta com rótulo ("8 de 13 aulas concluídas").
  - **Ação que muda com o estado**: Continuar / Começar / Baixar certificado.
  - Meta ancorada acima do divisor: aulas, carga horária, prazo.
- Estado vazio: "Nenhum curso por aqui" + "Quando sua organização matricular
  você em um curso, ele aparece nesta lista." Sem botão — o Aluno não se
  matricula sozinho.

---

## 7.2 Sala de aula — `classroom/show.blade.php` (9 dusk)

Breadcrumb + kicker *Sala de aula* + título do curso.

**Coluna principal** — um `x-ui.card` por módulo. Cada aula é uma linha com:
- Ícone em **círculo de 44px**: menta com `check` quando concluída; azul com
  `play` / `file-text` / `clipboard` conforme o tipo quando pendente.
- Título da aula + meta em caption ("Vídeo · 14 min").
- Chip "Prova" quando a aula tem avaliação.
- `chevron-right` 18px à direita; a linha inteira é o alvo.

**Coluna lateral** (sticky):
- Card de progresso: `x-ui.progress` + "8 de 13 aulas concluídas".
- `x-ui.alert` `info` explicando quando o Certificado é liberado.
- Instrutor: `x-ui.avatar` 64px + nome + papel.

---

## 7.3 Aula — `classroom/lesson.blade.php` (1 dusk) e parciais

`_video` (3), `_pdf` (4), `_text-image` (4), `_quiz-placeholder` (3).

**Comum a todos os tipos:**
- Coluna principal de 760px + coluna lateral com "Materiais da aula" e
  "Próximas aulas".
- Barra **"Tempo assistido"**, visualmente separada do progresso do curso.
  Texto atual preservado: salvo a cada 5 segundos, aula concluída após 90%.
- Botão "Marcar como concluída" em menta; depois vira chip estático
  "Aula concluída" — o botão não fica desabilitado, some.
- Navegação anterior / próxima ao pé, em botões ghost com borda.

**`_video`** — player em 16:9 sobre *pastel wash*, raio 20px, `--elev-2`.
`LessonPlayer.js` mantém o contrato de eventos e o intervalo de 5s.
Vídeo indisponível: `x-ui.empty-state` com "Vídeo indisponível — avise o
responsável pelo curso." **Nunca culpa o Aluno.**

**`_pdf`** — visualizador embutido em 4:3 com raio 20px + botão "Baixar PDF"
(tonal, ícone `file-text`) para o caso de o embed falhar.

**`_text-image`** — coluna de leitura de 760px, tipografia base 16px /
entrelinha 1,6, imagens com raio 14px e `--image-treatment`.

**`_quiz-placeholder`** — card `outlined` com ícone `clipboard` em círculo
pastel, nome da prova, tentativas restantes e CTA "Iniciar prova" (primary).

## Critérios de aceite

- Progresso do curso e tempo assistido nunca compartilham a mesma barra.
- Estados degradados não culpam o usuário.
- `MultiOrgStudentClassroomTest`, `StudentVideoLessonEmbedTest`,
  `VideoThresholdCompletionTest`, `LessonMultimediaTest` verdes.
