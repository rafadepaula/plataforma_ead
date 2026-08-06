# BUG-002: YouTube embed quebrado na visualização da lesson pelo aluno (`www.youtube.com refused to connect`)

## 1. Executive Summary & Impact
- **ID:** BUG-002
- **Severity:** High
- **Affected Role(s):** Aluno (visualização do player de vídeo na classroom)
- **Tenant Context:** Cross-tenant (qualquer `org_id`) — afeta qualquer aluno matriculado em curso cuja lesson tenha `youtube_url` persistida no formato `watch?v=` em vez do formato `embed/{id}` canônico.
- **Summary:** Ao abrir `/lessons/{id}` como aluno, o `<iframe>` do YouTube é renderizado com `src` malformado porque o `ClassroomController@showLesson` / `resources/views/classroom/partials/_video.blade.php` assumem que `lessons.youtube_url` já está persistida na forma sanitizada `https://www.youtube.com/embed/{id}`. A origem dos dados (`CourseSeeder` e qualquer seed/factory que grave direto no banco) bypassa `YoutubeSanitizerService` e grava URLs no formato bruto `https://www.youtube.com/watch?v={id}`. O YouTube recusa a conexão (cara triste com `www.youtube.com refused to connect.`). A tela de edição admin (`LessonController@edit` → `_form.blade.php`) funciona porque seu JS extrai o video ID via regex e reconstrói `embed/{id}` client-side, mascarando a inconsistência. Impacto: lesson de vídeo 100% indisponível para o aluno; progresso de vídeo (`LessonPlayer` polling → `lessons.progress`) também nunca dispara, pois `data-video-id` é calculado como `"watch"` (não o ID real), impedindo a auto-conclusão aos 90%.

## 2. Step-by-Step Reproduction Guide
### Pre-conditions:
1. Existe no banco uma `Lesson` com `is_published = true` e `youtube_url` no formato bruto `watch?v=...` (não-sanitizado). Atualmente é o caso de **todas as lessons de vídeo do `CourseSeeder`** (lessons 1 e a do Course 2 — "Docker e Ambiente Laravel Sail").
2. Existe um `User` com role `ALUNO` matriculado no curso dessa lesson (`course_user`).

### Reproduction Steps:
1. Como admin/gestor, rodar `vendor/bin/sail artisan db:seed --class=CourseSeeder` (estado em que o bug se manifesta — ou simplesmente verificar `lessons.id = 1` no banco).
2. Log in como um `Aluno` matriculado no curso dessa lesson.
3. Navegar para `http://localhost/lessons/1` (rota nomeada `classroom.lesson` → `ClassroomController@showLesson`).
4. Observar a área do player de vídeo.

### Expected Behavior (Happy Path):
- O `<iframe>` em `resources/views/classroom/partials/_video.blade.php` carrega `https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1&controls=1` e o vídeo é reproduzido normalmente.
- O `LessonPlayer.js` (`resources/js/modules/LessonPlayer.js`) instancia o YouTube IFrame API com `videoId = "dQw4w9WgXcQ"` (extraído corretamente de `data-video-id`), faz polling de `getCurrentTime()` e POSTa progresso a cada 5s para `lessons.progress`. Ao atingir 90%, a lesson é marcada como concluída automaticamente.

### Actual Behavior (Bug):
- O navegador exibe a "carinha triste" do YouTube com a mensagem `www.youtube.com refused to connect.`
- Causa HTML: o `<iframe>` recebe `src="https://www.youtube.com/watch?v=dQw4w9WgXcQ?rel=0&modestbranding=1&controls=1"` — uma URL `watch?v=...` **não-embeddable**. O YouTube bloqueia embedding de qualquer URL que não seja `youtube.com/embed/{id}` via header `X-Frame-Options: SAMEORIGIN` / CSP `frame-ancestors`.
- Causa colateral no JS: `_video.blade.php` calcula `$videoId = basename(parse_url($lesson->youtube_url, PHP_URL_PATH))`. Para uma URL `watch?v=...`, `parse_url(..., PHP_URL_PATH)` retorna apenas `/watch`, então `basename()` retorna `"watch"`. Logo `data-video-id="watch"` é injetado no container, e o `LessonPlayer.createPlayer()` instanciaria `new YT.Player(..., { videoId: "watch" })` — player inválido, progresso nunca flui.
- **Por que funciona na edição admin:** `resources/views/modules/lessons/_form.blade.php` (linhas 99–117) extrai o ID via regex JS `YOUTUBE_PATTERN` sobre o valor cru do input e reconstrói `https://www.youtube.com/embed/${id}` (linha 111). Esse caminho **mascara** a inconsistência e por isso o bug só é visível ao aluno.

## 3. Codebase & Architectural Mapping
- **Route Name / URL:** `classroom.lesson` (`GET /lessons/{lesson}`) — visão do aluno (`routes/web.php:186`).
- **Route Name / URL (admin edit, funciona):** `lessons.edit` (`GET /lessons/{lesson}/edit`) — `routes/web.php:86` (resource `modules.lessons` shallow).
- **Controller / Action:** `App\Http\Controllers\ClassroomController@showLesson` ([ClassroomController.php:56-86](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/ClassroomController.php#L56-L86)) — repassa `$lesson` cru para a view, sem sanitizar/normalizar `youtube_url`.
- **Blade View (aluno — falha):** `resources/views/classroom/partials/_video.blade.php` ([_video.blade.php:14-35](file:///home/rafael/projects/cursos/plataforma_ead/resources/views/classroom/partials/_video.blade.php#L14-L35)).
  - Linha 15: `$videoId = basename(parse_url($lesson->youtube_url, PHP_URL_PATH));` — só funciona quando `youtube_url` já está em forma `embed/{id}`; para `watch?v=...` resulta em `"watch"`.
  - Linha 30: `<iframe src="{{ $lesson->youtube_url }}?rel=0&modestbranding=1&controls=1" ...>` — usa a URL stored cru como `src`, com query string duplicada (`watch?v=...?rel=0`).
- **Blade View (admin — funciona):** `resources/views/modules/lessons/_form.blade.php` ([_form.blade.php:99-117](file:///home/rafael/projects/cursos/plataforma_ead/resources/views/modules/lessons/_form.blade.php#L99-L117)) — regex JS extrai o ID e reconstrói `embed/{id}` na pré-visualização (linha 111).
- **Model / Database Table:** `App\Models\Lesson` (`lessons` table; coluna `youtube_url` nullable, definida em `database/migrations/2026_08_01_000006_create_lessons_table.php:23`).
- **Service que DEVERIA ter sido usado (SOURCE OF TRUTH):** `App\Services\YoutubeSanitizerService::sanitize()` ([YoutubeSanitizerService.php:27-36](file:///home/rafael/projects/cursos/plataforma_ead/app/Services/YoutubeSanitizerService.php#L27-L36)) — reescreve qualquer entrada válida (`watch?v=`, `embed/`, `youtu.be/`) para a forma canônica `https://www.youtube.com/embed/{id}`.
- **Origem do dado inconsistente (ROOT CAUSE):** `database/seeders/CourseSeeder.php` ([CourseSeeder.php:52](file:///home/rafael/projects/cursos/plataforma_ead/database/seeders/CourseSeeder.php#L52) e [CourseSeeder.php:120](file:///home/rafael/projects/cursos/plataforma_ead/database/seeders/CourseSeeder.php#L120)) — grava `'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'` direto na tabela, **bypassando** `YoutubeSanitizerService`. (Por contraste, `database/factories/LessonFactory.php:89` já persiste no formato `embed/` correto.)
- **JS (aluno — colateral):** `resources/js/modules/LessonPlayer.js::createPlayer()` ([LessonPlayer.js:95-114](file:///home/rafael/projects/cursos/plataforma_ead/resources/js/modules/LessonPlayer.js#L95-L114)) — lê `data-video-id` do mesmo container; recebe `"watch"` quando o dado não está sanitizado.
- **Caminhos de gravação que SÓ passam pelo sanitizador (não afetados):** `LessonController@store` ([LessonController.php:175-176](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/LessonController.php#L175-L176)) e os FormRequests `StoreLessonRequest` / `UpdateLessonRequest` (`app/Http/Requests/Store*.php`, `UpdateLessonRequest.php:50`). Esses sim normalizam — bug exclusivo da via de seed.

## 4. Root Cause Technical Analysis
- **Failure Branch:** O contrato implícito entre a camada de persistência e a view do aluno é: **"todo `lessons.youtube_url` não-nulo já está sanitizado para `https://www.youtube.com/embed/{id}`"**. Esse contrato é honrado por `StoreLessonRequest`/`UpdateLessonRequest`/`LessonController` (via `YoutubeSanitizerService`), e explicitamente documentado no comentário do `_video.blade.php` (linhas 1–12): *"`youtube_url` is already persisted in sanitized embed form by `YoutubeSanitizerService`"*. **Mas `CourseSeeder` (e qualquer gravação direta no banco) não passa pelo sanitizador**, violando o contrato e persistindo o formato `watch?v=...`.
- **Two compounding defects a partir do mesmo dado:**
  1. **Iframe `src` não-embeddable** (`_video.blade.php:30`): `src="https://www.youtube.com/watch?v=dQw4w9WgXcQ?rel=0&..."`. O YouTube só permite embedding via `youtube.com/embed/{id}`; qualquer outra forma recebe `X-Frame-Options: SAMEORIGIN` → navegador bloqueia → cara triste.
  2. **`data-video-id` corrompido** (`_video.blade.php:15,23`): `parse_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ', PHP_URL_PATH)` retorna `"/watch"`; `basename()` retorna `"watch"`. O `LessonPlayer` então instanciaria o player YT com `videoId: "watch"` — inválido, sem progresso, sem auto-conclusão.
- **Stack Trace / Log Evidence:** Não há erro de backend (200 OK, render normal). Falha puramente client-side — sem entrada no `laravel.log`. O único sinal é a renderização do iframe recusado pelo YouTube no browser do aluno. (Conforme `mcp__laravel-boost__browser-logs`: nenhum log capturado; `laravel-boost:last-error` sem erro de backend.)

## 5. Test Specification Plan (TDD Blueprint)

### Escopo do fix (decidido com o usuário): **Corrigir o Seeder + migrar dados existentes.**
A view do aluno continua confiando no formato stored (contrato já documentado). A origem dos dados é corrigida para sempre produzir URLs sanitizadas, e as linhas já gravadas em formato `watch?v=` são migradas para `embed/{id}`.

### Unit / Feature Test (PHPUnit) — arquivo NOVO:
- **Test File:** `tests/Feature/LessonYoutubeEmbedRenderingTest.php`
- **Cenário A — `test_course_seeder_persists_sanitized_youtube_embed_urls()`:**
  - Rodar `CourseSeeder` (ou chamar `(new \Database\Seeders\CourseSeeder())->run()` num DB de teste isolado/transactional).
  - Assert: toda `Lesson` com `youtube_url` não-nulo tem valor casando `#^https://www\.youtube\.com/embed/[A-Za-z0-9_-]{11}$#`.
  - Assert específico: lesson "Introdução à Arquitetura Multitenant" tem `youtube_url === 'https://www.youtube.com/embed/dQw4w9WgXcQ'`.
- **Cenário B — `test_student_lesson_view_embeds_iframe_with_sanitized_src()`:**
  - Criar `Lesson` (factory `withVideo()`) + `User` ALUNO matriculado.
  - `GET route('classroom.lesson', $lesson)` atuando como o aluno.
  - Assert: response 200 e o HTML contém `src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0&amp;modestbranding=1&amp;controls=1"` (formato embeddable).
  - Assert: HTML contém `data-video-id="dQw4w9WgXcQ"` (não `"watch"`).
- **Cenário C (regression) — `test_student_lesson_view_renders_correct_video_id_data_attribute()`:** garante que `basename(parse_url(...))` retorna o ID de 11 chars quando o stored está sanitizado.

### Migration data-migration test (PHPUnit) — arquivo NOVO:
- **Test File:** `tests/Feature/MigrateRawYoutubeUrlsToEmbedTest.php` (ou `tests/Feature/LessonYoutubeEmbedRenderingTest.php`, mesmo arquivo).
- **Test Method:** `it_migrates_existing_watch_v_urls_to_embed_form()` — criar 2 lessons (uma `watch?v=ID`, uma já `embed/ID`, uma `youtu.be/ID`), rodar a migration de dados, assert que todas terminam `embed/{ID}` e a já-sanitizada permanece inalterada.

### Browser Test (Laravel Dusk) — opcional, se bug de UI:
- **Test File:** `tests/Browser/StudentVideoLessonEmbedTest.php` (NOVO).
- **Cenário:** logar como aluno, navegar `/lessons/{id}` (lesson com `youtube_url` sanitizada via seeder corrigido), esperar o `<iframe>` carregar `src` começando com `https://www.youtube.com/embed/`.
- **Selectors:** `dusk="video-player-{id}"` (já existe em `_video.blade.php:26`).
- Observação: como o `LessonPlayer.reportProgress` já é o hook de teste (vide `VideoThresholdCompletionTest`), o Dusk aqui foca em **afirmar que o iframe rendered tem `src` embeddable** — não em reproduzir o vídeo de fato (IFrame API é network-bound).

## 6. Acceptance Criteria for Fix Verification
- [ ] **`CourseSeeder` corrigido** — todo `youtube_url` persistido em formato `embed/{id}` (idealmente via chamada a `YoutubeSanitizerService` para evitar drift futuro; aceita-se também valor hardcoded sanitizado).
- [ ] **Migration de dados** — nova migration que normaliza quaisquer `lessons.youtube_url` em formato `watch?v=` ou `youtu.be/` para `embed/{id}` (idempotente; deve usar `YoutubeSanitizerService` ou SQL equivalente, e pular/silenciar linhas que já estejam sanitizadas ou sejam inválidas).
- [ ] Fix passa PHPUnit test `tests/Feature/LessonYoutubeEmbedRenderingTest.php::test_student_lesson_view_embeds_iframe_with_sanitized_src`.
- [ ] Fix passa PHPUnit test `tests/Feature/LessonYoutubeEmbedRenderingTest.php::test_course_seeder_persists_sanitized_youtube_embed_urls`.
- [ ] Fix passa PHPUnit test da migration de dados.
- [ ] Fix passa Dusk `tests/Browser/StudentVideoLessonEmbedTest.php` (se criado).
- [ ] **Verificação manual:** após `db:seed --class=CourseSeeder` + `migrate:fresh` (ou só rodar a nova migration), abrir `http://localhost/lessons/1` como aluno → iframe carrega o vídeo do YouTube normalmente, sem `refused to connect`.
- [ ] Nenhuma regressão em `tests/Feature/LessonMultimediaTest.php` (especialmente `test_gestor_can_create_a_youtube_lesson_with_a_sanitized_embed_url`) nem em `tests/Browser/VideoThresholdCompletionTest.php`.
- [ ] Nenhuma regressão de tenancy/auth scope (a view do aluno segue 404 para lessons não-publicadas para ALUNO — `ClassroomController.php:64-66`).
