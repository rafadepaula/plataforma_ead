# **28. Player de Lições Unificado, Formatos Multimídia e Rastreamento AJAX (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Refatorar a tela de consumo da aula (`classroom/lesson.blade.php`) com o padrão Material Bootstrap: casca unificada com despacho estrito para 4 formatos de mídia (*Prova*, *Vídeo*, *PDF*, *Texto/Imagem*), rastreamento assíncrono de vídeo a cada 5 segundos com auto-conclusão a 90% via `LessonPlayer.js`, conclusão manual assíncrona para PDF e texto com transição suave para chip menta sem recarregar a tela, e estados degradados em tom neutro.
* **Roles Cobertas:** `role:aluno` (e preview por `role:admin|gestor`), middleware `student.enrolled`.
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.9, `spec/new_ds/Aula - Anatomia.dc.html`.

---

## **2. Hierarquia de Despacho & Casca da Lição**

### 2.1 Cabeçalho & Casca Única
- **Breadcrumb (3 níveis):** `Meus cursos` → `[Curso]` (link para `classroom.show`) → `[Aula]` (texto puro).
- **Kicker:** `[Curso] / [Módulo]` (concatenado, sem truncar).
- **Título (`h1`):** Título da aula verbatim.
- **Subtítulo:** `"Continue seus estudos e marque a lição como concluída ao terminar."`
- **Ação:** Botão tonal com ícone `chevron-left`: `"Voltar à sala de aula"` (`dusk="back-to-classroom"`).
- **Card Único de Conteúdo:** Fundo branco, raio 20px, elevação 1, padding 32px.

### 2.2 Ordem Estrita de Despacho Blade
A hierarquia no template Blade **deve ser rigorosamente mantida**:
```blade
@if($lesson->type === 'quiz')
    @include('classroom.partials._quiz-placeholder')
@elseif(! empty($lesson->youtube_url))
    @include('classroom.partials._video')
@elseif(! empty($lesson->pdf_path))
    @include('classroom.partials._pdf')
@else
    @include('classroom.partials._text-image')
@endif
```
*(Quiz vem primeiro para garantir que lições de prova nunca sejam concluídas via player de vídeo).*

---

## **3. Os 4 Formatos de Conteúdo e Dinâmica de Interface**

### 3.1 Formato 1: Vídeo do YouTube (`_video.blade.php`)
- **Proporção 16:9:** Aplicada no *wrapper externo* `.ds-ratio.ds-ratio-16x9`, fundo escuro `--grey-900`, raio 14px, `overflow: hidden`.
- **Seletor Único:** `dusk="video-player-{{ $lesson->id }}"` no container do player.
- **Barra de Conclusão de Vídeo:** Exibe selo e caption explicativo: *"O progresso é salvo automaticamente ao assistir o vídeo."* (não possui botão manual; vídeo conclui 100% de forma automática a 90% assistidos).
- **Estado Degradado (Vídeo Indisponível):** Alerta em tom neutro `--attention-container` com `dusk="video-unavailable-{{ $lesson->id }}"`, título *"Vídeo indisponível"* e texto explicativo calmo (sem culpar o aluno).

### 3.2 Formato 2: Visualizador de PDF (`_pdf.blade.php`)
- **Visor:** `<iframe>` 16:9 com borda sutil, raio 14px, `dusk="pdf-viewer-{{ $lesson->id }}"`.
- **Linha Inferior:** Link para download do documento (`dusk="pdf-download-{{ $lesson->id }}"`) + Controles de conclusão manual.

### 3.3 Formato 3: Texto e Imagem (`_text-image.blade.php`)
- Coluna centralizada de 760px. Imagem 16:9 (`dusk="lesson-image-{{ $lesson->id }}"`) + Texto com escape e `white-space: pre-wrap` (`dusk="lesson-content-{{ $lesson->id }}"`) + Controles de conclusão manual.

### 3.4 Formato 4: Encaminhamento de Prova (`_quiz-placeholder.blade.php`)
- Card estilo empty-state tracejado com `dusk="quiz-placeholder"`.
- *Prova Disponível:* Ícone `clipboard` (30px), título *"Esta aula é uma prova"*, resumo de questões/tempo e botão primário *"Iniciar prova"* (`dusk="start-quiz"`).
- *Prova em Preparação:* Ícone `info`, título *"Prova em preparação"* e texto explicativo (sem botão de ação).

---

## **4. Fluxos AJAX, JavaScript & A Regra Proibitiva do `[hidden]`**

### 4.1 Rastreamento Automático (`LessonPlayer.js`)
- Envio periódico (a cada 5 segundos) de `{ watched_seconds, duration_seconds }` para `route('lessons.progress', $lesson)`.
- Ao atingir 90%, backend executa `MarkLessonCompleteAction` e retorna `{ is_completed: true }`. O JavaScript oculta o botão e revela o chip menta `"Concluída"`.
- Hook público para testes: `window.LessonPlayer.reportProgress(lessonId, watched, duration)`.

### 4.2 Conclusão Manual (PDF e Texto)
- Clique em *"Marcar como concluída"* envia POST assíncrono para `route('lessons.complete', $lesson)`. Sucesso altera a DOM instantaneamente e emite notificação.

### 4.3 Regra Arquitetural Obrigatória (Proibição do Atributo `hidden`)
> **NUNCA use o atributo HTML `hidden` no botão ou selo de conclusão.** O reboot do Bootstrap possui `[hidden] { display: none !important; }`, anulando manipulações `style.display`.
> A visibilidade deve ser controlada **exclusivamente via classes CSS** (`.d-none` / `.ds-hidden`) manipuladas com `classList.add()` / `classList.remove()`.

---

## **5. Seletores Dusk & Contrato E2E**

* `dusk="back-to-classroom"`: Botão voltar à sala de aula.
* `dusk="video-player-{id}"`: Container do player de vídeo.
* `dusk="video-unavailable-{id}"`: Alerta de vídeo inválido.
* `dusk="pdf-viewer-{id}"`: Visor de PDF em iframe.
* `dusk="pdf-download-{id}"`: Link de download do arquivo PDF.
* `dusk="lesson-image-{id}"`: Imagem anexada à lição.
* `dusk="lesson-content-{id}"`: Conteúdo textual da lição.
* `dusk="quiz-placeholder"`: Bloco de handoff de avaliação.
* `dusk="start-quiz"`: Botão iniciar prova.
* `dusk="lesson-completed-badge"`: Chip menta indicativo de lição concluída.
* `dusk="mark-complete-button"`: Botão de marcação manual de conclusão.

---

## **6. Checklist de Implementação & Testes**

- [ ] View `resources/views/classroom/lesson.blade.php` e os 4 parciais refatorados no padrão Material Bootstrap.
- [ ] Módulo JS `LessonPlayer.js` refatorado com controle de visibilidade via classes CSS (`.d-none`).
- [ ] Rastreamento de vídeo com threshold de 90% e seam de teste `window.LessonPlayer.reportProgress()`.
- [ ] Teste Feature: `LessonProgressControllerTest.php` cobrindo conclusão automática e manual.
- [ ] Teste Dusk: `LessonPlayerDuskTest.php` cobrindo todos os 4 formatos de mídia e conclusão assíncrona.
