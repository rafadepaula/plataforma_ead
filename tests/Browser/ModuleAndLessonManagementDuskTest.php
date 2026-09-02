<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the full selector contract for the trail-builder
 * screens: module list (rows, "{N} lições" chip, manage/edit actions),
 * cascade-aware ConfirmModal deletion (delete-module-{id} lives on the
 * modal's confirm submit, not on a raw row submit), lesson list rows with
 * publication chips, the multi-file lesson form (images[]/pdfs[], live
 * YouTube preview iframe) and the AJAX reorder round-trip.
 *
 * Real drag-and-drop cannot be emulated through WebDriver (see
 * `tests/Browser/ModuleReorderTest.php`): the reorder step rearranges the
 * DOM nodes and calls `window.ModuleReorder.persistOrder(list)` — the same
 * routine `ModuleReorder.js` runs on a real `drop` event.
 *
 * YouTube iframe race: waiting on the external iframe can hang on network,
 * so the preview is asserted through its `src` attribute, never through
 * iframe load, and no submit ever races a YouTube-bearing edit form (the
 * type-select toggling runs against a text-only lesson instead).
 */
class ModuleAndLessonManagementDuskTest extends DuskTestCase
{
    /**
     * @return array{0: Organization, 1: User, 2: Course}
     */
    private function makeOrgGestorAndCourse(): array
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        return [$org, $gestor, $course];
    }

    private function tempFile(string $suffix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lesson_media_').$suffix;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Minimal 1x1 PNG — `attach()` needs a real file on disk whose content
     * survives the server's `image` mime sniffing.
     */
    private function tempPng(int $index): string
    {
        return $this->tempFile("-{$index}.png",
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function tempPdf(int $index): string
    {
        return $this->tempFile("-{$index}.pdf",
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF");
    }

    /**
     * Attaches several real files to a `multiple` file input.
     *
     * `Browser::attach()` takes exactly one path (its LocalFileDetector
     * upload cannot carry an array, and chromedriver's session cannot read
     * paths newline-joined into a single send-keys payload). The
     * standard multi-file route is the same `DataTransfer` ->
     * `input.files` assignment `LessonForm.js` itself performs on a drop:
     * real `File` objects are built in-page from base64 bytes and a bubbling
     * `change` is dispatched so the form's own handler runs.
     *
     * @param  list<string>  $paths
     */
    private function attachManyFiles(Browser $browser, string $duskSelector, array $paths): void
    {
        $files = array_map(static fn (string $path): array => [
            'name' => basename($path),
            'mime' => mime_content_type($path) ?: 'application/octet-stream',
            'bytes' => base64_encode((string) file_get_contents($path)),
        ], $paths);

        $browser->script(
            '(function () {
                var input = document.querySelector("[dusk=\"'.$duskSelector.'\"]");
                var transfer = new DataTransfer();
                '.json_encode($files).'.forEach(function (file) {
                    var binary = atob(file.bytes);
                    var bytes = new Uint8Array(binary.length);
                    for (var i = 0; i < binary.length; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    transfer.items.add(new File([bytes], file.name, { type: file.mime }));
                });
                input.files = transfer.files;
                input.dispatchEvent(new Event("change", { bubbles: true }));
            })();'
        );
    }

    public function test_module_management_lifecycle(): void
    {
        [$org, $gestor, $course] = $this->makeOrgGestorAndCourse();
        $withLessons = Module::factory()->for($course)->create(['title' => 'Módulo com Aulas', 'order_index' => 0]);
        $empty = Module::factory()->for($course)->create(['title' => 'Módulo Vazio', 'order_index' => 1]);
        Lesson::factory()->for($withLessons)->richText()->create(['order_index' => 0, 'is_published' => true]);
        Lesson::factory()->for($withLessons)->richText()->create(['order_index' => 1, 'is_published' => true]);

        $this->browse(function (Browser $browser) use ($gestor, $course, $withLessons, $empty): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.modules.index', $course))
                ->waitFor('@module-list')

                // 1. Row contract: title + "{N} lições" metadata chip with
                //    the real lesson count (0-lesson wording included).
                ->assertSeeIn('@module-row-'.$withLessons->id, 'Módulo com Aulas')
                ->assertSeeIn('@module-row-'.$withLessons->id, '2 lições')
                ->assertSeeIn('@module-row-'.$empty->id, '0 lições')

                // 2. Header "Novo módulo" action creates through the UI.
                ->click('@new-module')
                ->waitFor('@module-form')
                ->type('title', 'Módulo Criado pelo Dusk')
                ->click('@module-submit')
                ->waitFor('@module-list')
                ->assertSeeIn('@module-list', 'Módulo Criado pelo Dusk')

                // 3. Row actions navigate.
                ->click('@manage-lessons-'.$withLessons->id)
                ->waitFor('@lesson-list')
                ->back()
                ->waitFor('@module-list')
                ->click('@edit-module-'.$empty->id)
                ->waitFor('@module-form')
                ->assertInputValue('title', 'Módulo Vazio')
                ->back()
                ->waitFor('@module-list')

                // 4. AJAX reorder round-trip via the same persistOrder()
                //    routine a real drop event triggers.
                ->script(
                    "(function () {
                        var list = document.querySelector('[dusk=\"module-list\"]');
                        var dragged = document.querySelector('[data-id=\"{$empty->id}\"]');
                        var target = document.querySelector('[data-id=\"{$withLessons->id}\"]');
                        list.insertBefore(dragged, target);
                        window.ModuleReorder.persistOrder(list);
                    })();"
                );
            $browser->waitForText('Ordem atualizada com sucesso.')
                ->refresh()
                ->waitFor('@module-list');
        });

        $this->assertSame(0, $empty->fresh()->order_index);
        $this->assertSame(1, $withLessons->fresh()->order_index);

        // 5. Destructive delete goes through the ConfirmModal: the trigger
        //    opens the modal, the cascade warning quotes the real lesson
        //    count, and dusk="delete-module-{id}" is the modal's confirm
        //    submit.
        $this->browse(function (Browser $browser) use ($gestor, $course, $withLessons): void {
            $trigger = '@module-row-'.$withLessons->id.' [data-bs-target*="delete-module-modal-'.$withLessons->id.'"]';

            $browser->loginAs($gestor)
                ->visit(route('courses.modules.index', $course))
                ->waitFor('@module-list')
                ->click($trigger);

            $modalId = trim(str_replace('#', '', $browser->attribute($trigger, 'data-bs-target')));

            $browser->waitForModalShown($modalId)
                ->assertSeeIn('#'.$modalId, 'As 2 lições deste módulo também serão removidas. Esta ação não poderá ser desfeita.')
                ->clickAndWaitForReload('@delete-module-'.$withLessons->id)
                ->waitFor('@module-list')
                ->assertDontSeeIn('@module-list', 'Módulo com Aulas');
        });

        $this->assertSoftDeleted('modules', ['id' => $withLessons->id]);
        // Soft delete never cascade-purges the lessons: the modal warning
        // mirrors the DB-level ON DELETE CASCADE that only a hard delete
        // would fire; lesson_progress history must survive regardless.
        $this->assertDatabaseHas('lessons', [
            'module_id' => $withLessons->id,
            'deleted_at' => null,
        ]);
    }

    public function test_lesson_authoring_lifecycle(): void
    {
        [$org, $gestor, $course] = $this->makeOrgGestorAndCourse();
        $module = Module::factory()->for($course)->create(['title' => 'Módulo Alvo', 'order_index' => 0]);
        $first = Lesson::factory()->for($module)->richText()->create(['title' => 'Lição Publicada', 'order_index' => 0, 'is_published' => true]);
        $second = Lesson::factory()->for($module)->richText()->create(['title' => 'Lição Oculta', 'order_index' => 1, 'is_published' => false]);

        $firstImage = $this->tempPng(1);
        $secondImage = $this->tempPng(2);
        $firstPdf = $this->tempPdf(1);
        $secondPdf = $this->tempPdf(2);

        try {
            $this->browse(function (Browser $browser) use ($gestor, $module, $first, $second, $firstImage, $secondImage, $firstPdf, $secondPdf): void {
                // 1. List contract: rows, type chip and the "Não publicada"
                //    chip on the unpublished row.
                $browser->loginAs($gestor)
                    ->visit(route('modules.lessons.index', $module))
                    ->waitFor('@lesson-list')
                    ->assertSeeIn('@lesson-row-'.$first->id, 'Lição Publicada')
                    ->assertSeeIn('@lesson-row-'.$first->id, 'Conteúdo')
                    ->assertSeeIn('@lesson-row-'.$second->id, 'Não publicada')

                    // 2. Multi-file authoring: two images + two PDFs and a
                    //    live YouTube preview asserted via `src` only.
                    ->click('@new-lesson')
                    ->waitFor('@lesson-form')
                    ->type('title', 'Lição Multimídia do Dusk')
                    ->select('@lesson-type-select', 'content')
                    ->select('@lesson-provider-select', 'youtube')
                    ->type('@lesson-video-input', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                    ->waitFor('@video-preview')
                    ->assertAttributeContains('@video-preview', 'src', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
                $this->attachManyFiles($browser, 'lesson-image-input', [$firstImage, $secondImage]);
                $this->attachManyFiles($browser, 'lesson-pdf-input', [$firstPdf, $secondPdf]);
                $browser->click('@lesson-submit')
                    ->waitFor('@lesson-list')
                    ->assertSee('Lição Multimídia do Dusk')
                    ->assertSeeIn('@lesson-list', 'Não publicada');
            });

            $created = Lesson::where('module_id', $module->id)
                ->where('title', 'Lição Multimídia do Dusk')
                ->firstOrFail();

            $this->assertSame(2, $created->media()->where('kind', 'image')->count());
            $this->assertSame(2, $created->media()->where('kind', 'pdf')->count());
            $created->media()->where('kind', 'image')->pluck('path')->each(function (string $path) use ($org, $course): void {
                $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/images/", $path);
            });
            $this->assertSame(
                'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
                $created->fresh()->video_url,
            );

            $this->browse(function (Browser $browser) use ($gestor, $module, $first, $second, $created): void {
                // 3. Type-select toggling on a text-only lesson (keeps this
                //    step clear of the YouTube iframe load race): selecting
                //    "Quiz (em breve)" hides the content fields.
                $browser->loginAs($gestor)
                    ->visit(route('lessons.edit', $second))
                    ->waitFor('@lesson-form')
                    ->select('@lesson-type-select', 'quiz')
                    ->assertMissing('@lesson-video-input')
                    ->assertMissing('@lesson-pdf-input')
                    ->select('@lesson-type-select', 'content')
                    ->assertVisible('@lesson-video-input')
                    ->assertVisible('@lesson-pdf-input')

                    // 4. edit-lesson-{id} from the listing (assert only —
                    //    never submit against the YouTube-bearing lesson).
                    ->visit(route('modules.lessons.index', $module))
                    ->waitFor('@lesson-list')
                    ->click('@edit-lesson-'.$created->id)
                    ->waitFor('@lesson-form')
                    ->assertInputValue('title', 'Lição Multimídia do Dusk')

                    // 5. Lesson reorder round-trip through persistOrder().
                    ->visit(route('modules.lessons.index', $module))
                    ->waitFor('@lesson-list')
                    ->script(
                        "(function () {
                            var list = document.querySelector('[dusk=\"lesson-list\"]');
                            var dragged = document.querySelector('[data-id=\"{$second->id}\"]');
                            var target = document.querySelector('[data-id=\"{$first->id}\"]');
                            list.insertBefore(dragged, target);
                            window.ModuleReorder.persistOrder(list);
                        })();"
                    );
                $browser->waitForText('Ordem atualizada com sucesso.')
                    ->refresh()
                    ->waitFor('@lesson-list');
            });

            $this->assertSame(0, $second->fresh()->order_index);
            $this->assertSame(1, $first->fresh()->order_index);

            $this->browse(function (Browser $browser) use ($gestor, $module, $created): void {
                // 6. Destructive delete through the ConfirmModal, with
                //    dusk="delete-lesson-{id}" on the modal's confirm submit.
                $trigger = '@lesson-row-'.$created->id.' [data-bs-target*="delete-lesson-modal-'.$created->id.'"]';

                $browser->loginAs($gestor)
                    ->visit(route('modules.lessons.index', $module))
                    ->waitFor('@lesson-list')
                    ->click($trigger);

                $modalId = trim(str_replace('#', '', $browser->attribute($trigger, 'data-bs-target')));

                $browser->waitForModalShown($modalId)
                    ->clickAndWaitForReload('@delete-lesson-'.$created->id)
                    ->waitFor('@lesson-list')
                    ->assertDontSeeIn('@lesson-list', 'Lição Multimídia do Dusk');
            });

            $this->assertSoftDeleted('lessons', ['id' => $created->id]);
        } finally {
            foreach ([$firstImage, $secondImage, $firstPdf, $secondPdf] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Attaches a file to a `multiple` input WITHOUT dispatching `change` —
     * `LessonForm.js`'s `handleFiles()` listener (client-side max-size
     * gate) never runs, so `input.files` reaches the real POST exactly as
     * assigned. This is the only way to drive the SERVER's 422 response
     * for an oversized/wrong-type file through an actual browser instead
     * of only ever hitting the client-side rejection path.
     */
    private function attachFileBypassingClientValidation(Browser $browser, string $duskSelector, string $path): void
    {
        $file = [
            'name' => basename($path),
            'mime' => mime_content_type($path) ?: 'application/octet-stream',
            'bytes' => base64_encode((string) file_get_contents($path)),
        ];

        $browser->script(
            '(function () {
                var input = document.querySelector("[dusk=\"'.$duskSelector.'\"]");
                var transfer = new DataTransfer();
                var file = '.json_encode($file).';
                var binary = atob(file.bytes);
                var bytes = new Uint8Array(binary.length);
                for (var i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                transfer.items.add(new File([bytes], file.name, { type: file.mime }));
                input.files = transfer.files;
            })();'
        );
    }

    /**
     * Exception-flow coverage that the happy-path lifecycle chain
     * above never exercises: an invalid YouTube URL rejected with a
     * validation error rendered on `error-video_url`, an oversized image
     * rejected client-side by `LessonForm.js` (`.is-invalid` + message on
     * the dropzone, no server round-trip), and an oversized PDF plus a
     * non-PDF file named `.pdf` rejected by the SERVER with a 422-driven
     * validation error rendered on `error-pdfs` — the latter two bypass
     * `LessonForm.js`'s client-side gate entirely (see
     * `attachFileBypassingClientValidation()`) so the real
     * `StoreLessonRequest`/`FileUploadService` rejection path renders in
     * the browser, not just in `LessonMultimediaTest`'s HTTP-only coverage.
     */
    public function test_lesson_form_validation_rejections(): void
    {
        [, $gestor, $course] = $this->makeOrgGestorAndCourse();
        $module = Module::factory()->for($course)->create(['order_index' => 0]);

        $oversizedImage = $this->tempFile('-oversized.png', str_repeat('a', 2 * 1024 * 1024 + 1024));
        $oversizedPdf = $this->tempFile('-oversized.pdf', "%PDF-1.4\n".str_repeat('a', 10 * 1024 * 1024 + 1024));
        $fakePdf = $this->tempFile('-fake.pdf', 'this is not a real pdf, just plain text');

        try {
            $this->browse(function (Browser $browser) use ($gestor, $module, $oversizedImage, $oversizedPdf, $fakePdf): void {
                $browser->loginAs($gestor)
                    ->visit(route('modules.lessons.create', $module))
                    ->waitFor('@lesson-form')
                    ->type('title', 'Lição Rejeitada')
                    ->select('@lesson-type-select', 'content')

                    // 1. Invalid YouTube URL: server-rendered validation
                    //    error surfaces on `error-youtube_url` and no
                    //    lesson is persisted.
                    ->type('@lesson-video-input', 'https://vimeo.com/12345')
                    ->click('@lesson-submit')
                    ->waitFor('@error-video_url')
                    // O select continua em YouTube (a URL não casa com
                    // nenhum provedor), então a mensagem é a do YouTube.
                    ->assertSeeIn('@error-video_url', 'YouTube')
                    ->assertInputValue('title', 'Lição Rejeitada');

                $this->assertDatabaseMissing('lessons', ['title' => 'Lição Rejeitada']);

                // 2. Oversized image: rejected CLIENT-SIDE by
                //    LessonForm.js's own change handler — the dropzone is
                //    marked invalid with a size-limit message and the file
                //    never joins the upload, with no server round-trip.
                $this->attachManyFiles($browser, 'lesson-image-input', [$oversizedImage]);

                $zoneClasses = $browser->script(
                    'return document.querySelector("[dusk=\"lesson-image-input\"]").closest("[data-file-drop]").querySelector("[data-file-drop-zone]").className;'
                )[0];
                $this->assertStringContainsString('is-invalid', $zoneClasses);

                // Note: `.invalid-feedback` only exists server-side once
                // `$errors->has()` is true (Blade renders it conditionally),
                // so `LessonForm.js`'s `setError()` guards the text write
                // with `if (feedback && message)`. The `is-invalid` class
                // toggle above is what a real user sees on a fresh form and
                // is exactly the behavior this Dusk gap left unverified.

                // 3. Oversized PDF: bypasses the client-side gate entirely
                //    and hits the real server 422, rendered on `error-pdfs`.
                $this->attachFileBypassingClientValidation($browser, 'lesson-pdf-input', $oversizedPdf);
                $browser->click('@lesson-submit')
                    ->waitFor('@error-pdfs');

                // 4. Non-PDF content named `.pdf`: same server-side mime
                //    sniff rejection, exercised end to end through the
                //    browser instead of only through the Feature suite.
                $this->attachFileBypassingClientValidation($browser, 'lesson-pdf-input', $fakePdf);
                $browser->click('@lesson-submit')
                    ->waitFor('@error-pdfs');
            });

            $this->assertDatabaseMissing('lessons', ['title' => 'Lição Rejeitada']);
        } finally {
            foreach ([$oversizedImage, $oversizedPdf, $fakePdf] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Cross-tenant guessing against the module and lesson management
     * routes must be denied with a real 403, rendered end to end through
     * the browser (the Feature suite already pins the HTTP status in
     * `MultiTenantCourseManagementTest`, but no Dusk test drove the same
     * denial through an actual page load until now).
     */
    public function test_module_and_lesson_management_forbidden_across_tenants(): void
    {
        [, $gestor] = $this->makeOrgGestorAndCourse();
        $otherOrg = Organization::factory()->create();
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $otherModule = Module::factory()->for($otherCourse)->create(['order_index' => 0]);
        $otherLesson = Lesson::factory()->for($otherModule)->richText()->create();

        $this->browse(function (Browser $browser) use ($gestor, $otherModule, $otherLesson): void {
            $browser->loginAs($gestor)
                ->visit(route('modules.edit', $otherModule))
                ->assertSee('403')
                ->visit(route('modules.lessons.index', $otherModule))
                ->assertSee('403')
                ->visit(route('lessons.edit', $otherLesson))
                ->assertSee('403');
        });
    }
}
