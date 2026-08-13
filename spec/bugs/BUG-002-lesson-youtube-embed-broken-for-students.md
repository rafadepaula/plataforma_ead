# BUG-002: YouTube embed quebrado na visualização da lesson pelo aluno (`www.youtube.com refused to connect`)

> **Revalidado em 2026-08-13 (pós-migração Bootstrap 5.3, commit 3088d99):** **PARCIAL.** A causa raiz reportada foi eliminada **antes** da migração, pelo commit `44c7e8a` — `CourseSeeder.php:53,121` agora passa por `YoutubeSanitizerService::sanitize()`, e `tests/Feature/LessonYoutubeEmbedRenderingTest.php` (3 testes, passando) trava a regressão. Sobrevive, porém, a causa estrutural: a **migração de dados nunca foi escrita** e `_video.blade.php:20,34` continua confiando cegamente no formato armazenado, então qualquer linha legada ou gravada fora do fluxo de FormRequest ainda produz o mesmo `refused to connect`.

## 1. Executive Summary & Impact
- **ID:** BUG-002
- **Severity:** High
- **Affected Role(s):** Aluno (visualização do player de vídeo na classroom)
- **Tenant Context:** Cross-tenant (qualquer `org_id`) — afeta qualquer aluno matriculado em curso cuja lesson tenha `youtube_url` persistida no formato `watch?v=` em vez do formato `embed/{id}` canônico.
- **Summary:** Ao abrir `/lessons/{id}` como aluno, o `<iframe>` do YouTube é renderizado com `src` malformado porque o `ClassroomController@showLesson` / `resources/views/classroom/partials/_video.blade.php` assumem que `lessons.youtube_url` já está persistida na forma sanitizada `https://www.youtube.com/embed/{id}`. A origem dos dados (`CourseSeeder` e qualquer seed/factory que grave direto no banco) bypassa `YoutubeSanitizerService` e grava URLs no formato bruto `https://www.youtube.com/watch?v={id}`. O YouTube recusa a conexão (cara triste com `www.youtube.com refused to connect.`). A tela de edição admin (`LessonController@edit` → `_form.blade.php`) funciona porque seu JS extrai o video ID via regex e reconstrói `embed/{id}` client-side, mascarando a inconsistência. Impacto: lesson de vídeo 100% indisponível para o aluno; progresso de vídeo (`LessonPlayer` polling → `lessons.progress`) também nunca dispara, pois `data-video-id` é calculado como `"watch"` (não o ID real), impedindo a auto-conclusão aos 90%.

## 2. Step-by-Step Reproduction Guide

> **Atualizado em 2026-08-13.** O caminho de reprodução original (via `CourseSeeder`) **não reproduz mais**: o seeder foi corrigido em `44c7e8a` e o banco de desenvolvimento hoje só contém URLs em forma `embed/` (verificado: `Lesson::withoutGlobalScopes()->whereNotNull('youtube_url')->pluck('youtube_url','id')` → `{"121":".../embed/dQw4w9WgXcQ","125":".../embed/dQw4w9WgXcQ"}`). O que ainda reproduz é o caminho de dados legado/direto abaixo.

### Pre-conditions:
1. Existe no banco uma `Lesson` com `is_published = true` e `youtube_url` no formato bruto `watch?v=...` (não-sanitizado). **Não é mais produzido pelo `CourseSeeder`**; hoje só surge de linhas gravadas antes de `44c7e8a`, de `UPDATE`/import direto no banco, ou de qualquer futuro seeder/comando que grave sem passar pelo `YoutubeSanitizerService`.
2. Existe um `User` com role `ALUNO` matriculado no curso dessa lesson (`course_user`).

### Reproduction Steps:
1. Gravar direto no banco uma URL não-sanitizada, simulando o dado legado:
   `vendor/bin/sail artisan tinker --execute 'DB::table("lessons")->where("id", 121)->update(["youtube_url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ"]);'`
   *(passo original, hoje inócuo: `vendor/bin/sail artisan db:seed --class=CourseSeeder` — o seeder já sanitiza.)*
2. Log in como um `Aluno` matriculado no curso dessa lesson.
3. Navegar para `http://localhost/lessons/121` (rota nomeada `classroom.lesson` → `ClassroomController@showLesson`).
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

> Referências de arquivo:linha reconferidas em 2026-08-13 contra o código atual.

- **Route Name / URL:** `classroom.lesson` (`GET /lessons/{lesson}`) — visão do aluno (`routes/web.php:223`).
- **Route Name / URL (admin edit):** `lessons.edit` (`GET /lessons/{lesson}/edit`) — resource `modules.lessons` shallow.
- **Controller / Action:** `App\Http\Controllers\ClassroomController@showLesson` (`app/Http/Controllers/ClassroomController.php:71`) — repassa `$lesson` cru para a view, **segue** sem sanitizar/normalizar `youtube_url`.
- **Blade View (aluno — ponto frágil remanescente):** `resources/views/classroom/partials/_video.blade.php`. Foi reescrita pela migração (a razão 16:9 agora é `.ratio.ratio-16x9`, o badge usa `.d-none`), mas **as duas linhas do defeito sobreviveram intactas**:
  - Linha 20: `$videoId = basename(parse_url($lesson->youtube_url, PHP_URL_PATH));` — só funciona quando `youtube_url` já está em forma `embed/{id}`; para `watch?v=...` ainda resulta em `"watch"`.
  - Linha 34: `src="{{ $lesson->youtube_url }}?rel=0&modestbranding=1&controls=1"` — usa a URL armazenada crua como `src`, com query string duplicada (`watch?v=...?rel=0`).
  - Linhas 1-5: o comentário reafirma o contrato implícito (*"`youtube_url` is already persisted in sanitized embed form"*), que continua sem nenhuma verificação em runtime.
  - `dusk="video-player-{{ $lesson->id }}"` permanece na linha 30 (preservado pela migração).
- **Blade View (admin — funciona):** `resources/views/modules/lessons/_form.blade.php` ([_form.blade.php:99-117](file:///home/rafael/projects/cursos/plataforma_ead/resources/views/modules/lessons/_form.blade.php#L99-L117)) — regex JS extrai o ID e reconstrói `embed/{id}` na pré-visualização (linha 111).
- **Model / Database Table:** `App\Models\Lesson` (`lessons` table; coluna `youtube_url` nullable, definida em `database/migrations/2026_08_01_000006_create_lessons_table.php:23`).
- **Service SOURCE OF TRUTH:** `App\Services\YoutubeSanitizerService::sanitize()` (`app/Services/YoutubeSanitizerService.php:27-36`, regex em `:25`) — reescreve qualquer entrada válida (`watch?v=`, `embed/`, `youtu.be/`) para a forma canônica `https://www.youtube.com/embed/{id}`; lança `InvalidYoutubeUrlException` para o resto.
- **Origem do dado inconsistente (ROOT CAUSE original — CORRIGIDA):** `database/seeders/CourseSeeder.php:53` e `:121`. Hoje lê `'youtube_url' => app(YoutubeSanitizerService::class)->sanitize('https://www.youtube.com/watch?v=dQw4w9WgXcQ')`. Corrigido em `44c7e8a` *fix(seeder): sanitize YouTube URLs in CourseSeeder for embed support (BUG-002)* — **antes** da migração Bootstrap, portanto sem relação com `3088d99`. (`database/factories/LessonFactory.php:85-89`, state `withYoutube()`, já persistia no formato `embed/` correto.)
- **Lacuna remanescente (ROOT CAUSE atual):** não existe migração de normalização de dados. `ls database/migrations/ | grep -i "youtube\|normaliz\|sanit"` → vazio. Nada impede uma linha em `watch?v=` de existir na tabela, e nada na view a protege.
- **JS (aluno — colateral):** `resources/js/modules/LessonPlayer.js::createPlayer()` (`resources/js/modules/LessonPlayer.js:96-105`) — lê `data-video-id` do container (`:98`) e o repassa como `videoId` ao `YT.Player` (`:105`); recebe `"watch"` quando o dado não está sanitizado. Guard de linha `:100` só rejeita valores vazios, não valores inválidos.
- **Caminhos de gravação que SÓ passam pelo sanitizador (não afetados):** `LessonController@store` ([LessonController.php:175-176](file:///home/rafael/projects/cursos/plataforma_ead/app/Http/Controllers/LessonController.php#L175-L176)) e os FormRequests `StoreLessonRequest` / `UpdateLessonRequest` (`app/Http/Requests/Store*.php`, `UpdateLessonRequest.php:50`). Esses sim normalizam — bug exclusivo da via de seed.

## 4. Root Cause Technical Analysis
- **Failure Branch:** O contrato implícito entre a camada de persistência e a view do aluno é: **"todo `lessons.youtube_url` não-nulo já está sanitizado para `https://www.youtube.com/embed/{id}`"**. Esse contrato é honrado por `StoreLessonRequest`/`UpdateLessonRequest`/`LessonController` (via `YoutubeSanitizerService`), e explicitamente documentado no comentário do `_video.blade.php` (linhas 1–12): *"`youtube_url` is already persisted in sanitized embed form by `YoutubeSanitizerService`"*. **Mas `CourseSeeder` (e qualquer gravação direta no banco) não passa pelo sanitizador**, violando o contrato e persistindo o formato `watch?v=...`.
- **Two compounding defects a partir do mesmo dado:**
  1. **Iframe `src` não-embeddable** (`_video.blade.php:30`): `src="https://www.youtube.com/watch?v=dQw4w9WgXcQ?rel=0&..."`. O YouTube só permite embedding via `youtube.com/embed/{id}`; qualquer outra forma recebe `X-Frame-Options: SAMEORIGIN` → navegador bloqueia → cara triste.
  2. **`data-video-id` corrompido** (`_video.blade.php:15,23`): `parse_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ', PHP_URL_PATH)` retorna `"/watch"`; `basename()` retorna `"watch"`. O `LessonPlayer` então instanciaria o player YT com `videoId: "watch"` — inválido, sem progresso, sem auto-conclusão.
- **Stack Trace / Log Evidence:** Não há erro de backend (200 OK, render normal). Falha puramente client-side — sem entrada no `laravel.log`. O único sinal é a renderização do iframe recusado pelo YouTube no browser do aluno. (Conforme `mcp__laravel-boost__browser-logs`: nenhum log capturado; `laravel-boost:last-error` sem erro de backend.)

### 4.1 Causa raiz atual, pós-revalidação (2026-08-13)

A migração Bootstrap 5.3 **não tocou** neste defeito — reescreveu o layout de `_video.blade.php` (razão 16:9 via `.ratio`, badge via `.d-none`) preservando exatamente a lógica de `parse_url`/`basename` e o `src` cru. O que mudou foi anterior e independente: `44c7e8a` corrigiu o produtor de dados.

Restam, portanto, **dois** dos três elos do problema:

1. ~~Produtor de dado inválido (`CourseSeeder`)~~ — **fechado** (`CourseSeeder.php:53,121`).
2. **Dados legados nunca normalizados** — nenhuma migração de dados foi escrita. Qualquer linha gravada antes de `44c7e8a`, por import/`UPDATE` direto, ou por um futuro seeder desatento, permanece em `watch?v=`. No banco de desenvolvimento atual isso não ocorre (as duas lessons de vídeo, ids 121 e 125, estão em forma `embed/`), mas isso é estado, não garantia.
3. **Consumidor sem defesa** (`_video.blade.php:20,34`) — a view confia num contrato que nenhuma constraint, cast de model ou mutator impõe. É o elo que transforma um dado ruim em `refused to connect` + `data-video-id="watch"`.

Fechar 2 e 3 é o que converte este bug de "corrigido por acaso no estado atual do banco" para "estruturalmente impossível".

## 5. Test Specification Plan (TDD Blueprint)

### Escopo do fix (decidido com o usuário): **Corrigir o Seeder + migrar dados existentes.**
A view do aluno continua confiando no formato stored (contrato já documentado). A origem dos dados é corrigida para sempre produzir URLs sanitizadas, e as linhas já gravadas em formato `watch?v=` são migradas para `embed/{id}`.

### Unit / Feature Test (PHPUnit) — ~~arquivo NOVO~~ **JÁ EXISTE E PASSA**:
- **Test File:** `tests/Feature/LessonYoutubeEmbedRenderingTest.php` — os 3 cenários abaixo estão implementados (`:25`, `:59`, `:95`). Verificado em 2026-08-13: `vendor/bin/sail artisan test --compact --filter=LessonYoutubeEmbedRenderingTest` → **3 passed, 10 assertions**. (Nota: o cenário B usa o state `withYoutube()` da factory, não `withVideo()` como escrito no plano original.)
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

### Migration data-migration test (PHPUnit) — **AINDA PENDENTE** (arquivo inexistente em 2026-08-13):
- **Test File:** `tests/Feature/MigrateRawYoutubeUrlsToEmbedTest.php` (ou `tests/Feature/LessonYoutubeEmbedRenderingTest.php`, mesmo arquivo).
- **Test Method:** `it_migrates_existing_watch_v_urls_to_embed_form()` — criar 2 lessons (uma `watch?v=ID`, uma já `embed/ID`, uma `youtu.be/ID`), rodar a migration de dados, assert que todas terminam `embed/{ID}` e a já-sanitizada permanece inalterada.

### Browser Test (Laravel Dusk) — opcional, **ainda não criado** (arquivo inexistente em 2026-08-13):
- **Test File:** `tests/Browser/StudentVideoLessonEmbedTest.php` (NOVO).
- **Cenário:** logar como aluno, navegar `/lessons/{id}` (lesson com `youtube_url` sanitizada via seeder corrigido), esperar o `<iframe>` carregar `src` começando com `https://www.youtube.com/embed/`.
- **Selectors:** `dusk="video-player-{id}"` (já existe em `_video.blade.php:26`).
- Observação: como o `LessonPlayer.reportProgress` já é o hook de teste (vide `VideoThresholdCompletionTest`), o Dusk aqui foca em **afirmar que o iframe rendered tem `src` embeddable** — não em reproduzir o vídeo de fato (IFrame API é network-bound).

## 6. Acceptance Criteria for Fix Verification
- [x] **`CourseSeeder` corrigido** — `CourseSeeder.php:53,121` chamam `app(YoutubeSanitizerService::class)->sanitize(...)`. Fechado em `44c7e8a`.
- [ ] **Migration de dados** — nova migration que normaliza quaisquer `lessons.youtube_url` em formato `watch?v=` ou `youtu.be/` para `embed/{id}` (idempotente; deve usar `YoutubeSanitizerService` ou SQL equivalente, e pular/silenciar linhas que já estejam sanitizadas ou sejam inválidas). **AINDA NÃO EXISTE.**
- [x] Fix passa PHPUnit test `tests/Feature/LessonYoutubeEmbedRenderingTest.php::test_student_lesson_view_embeds_iframe_with_sanitized_src` (`:59`).
- [x] Fix passa PHPUnit test `tests/Feature/LessonYoutubeEmbedRenderingTest.php::test_course_seeder_persists_sanitized_youtube_embed_urls` (`:25`).
- [ ] Fix passa PHPUnit test da migration de dados. **Bloqueado pela ausência da migration.**
- [ ] Fix passa Dusk `tests/Browser/StudentVideoLessonEmbedTest.php` (se criado). **Não criado.**
- [x] **Verificação manual:** o banco de desenvolvimento hoje só tem URLs em forma `embed/` (lessons 121 e 125).
- [ ] Nenhuma regressão em `tests/Feature/LessonMultimediaTest.php` (especialmente `test_gestor_can_create_a_youtube_lesson_with_a_sanitized_embed_url`) nem em `tests/Browser/VideoThresholdCompletionTest.php`. *(Não reexecutados nesta revalidação — Dusk fora de escopo.)*
- [ ] Nenhuma regressão de tenancy/auth scope (a view do aluno segue 404 para lessons não-publicadas para ALUNO — `ClassroomController@showLesson`, `app/Http/Controllers/ClassroomController.php:71`).

### 6.1 Trabalho remanescente (aberto)
- [ ] Escrever a migration de normalização de `lessons.youtube_url` + seu teste.
- [ ] Tornar o consumidor defensivo: normalizar em `_video.blade.php:20,34` (ou, preferencialmente, num accessor/mutator de `App\Models\Lesson` sobre `youtube_url`, o que fecha o buraco para todos os consumidores de uma vez) em vez de confiar no formato armazenado.

## Resolution Status
- **Status:** CLOSED — produtor corrigido em `44c7e8a`; dados legados normalizados por migration e consumidor endurecido em `274f700`.
- **Reproduction Tests:** `tests/Feature/LessonYoutubeEmbedRenderingTest.php:25,59,95` (3 passed, 10 assertions em 2026-08-13).
- **Fixed In Files:** `database/seeders/CourseSeeder.php:53,121`.
- **Still Broken In Files:** `resources/views/classroom/partials/_video.blade.php:20,34`; ausência de migration em `database/migrations/`.
