<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guardrails for the unified lesson player:
 *
 * 1. the media dispatch order (quiz -> video -> pdf -> text/image) is
 *    exclusive: exactly one format renders, even when a row carries
 *    conflicting content columns;
 * 2. the completion controls are toggled ONLY through the `d-none` class —
 *    the `hidden` attribute is forbidden because the theme ships a
 *    `[hidden] { display: none !important; }` rule the JS cannot override;
 * 3. `content_text` is rendered escaped.
 */
class LessonDispatchOrderTest extends TestCase
{
    public function test_quiz_lesson_renders_the_quiz_placeholder_even_when_it_carries_video_and_pdf(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'quiz',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'pdf_path' => 'orgs/1/courses/1/pdfs/material.pdf',
            'content_text' => 'Texto que não deve aparecer.',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="quiz-placeholder"', false);
        $response->assertDontSee('dusk="video-player-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="lesson-content-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="mark-complete-button"', false);
    }

    public function test_content_lesson_with_video_and_pdf_renders_only_the_video_player(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'pdf_path' => 'orgs/1/courses/1/pdfs/material.pdf',
            'content_text' => 'Texto que não deve aparecer.',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="video-player-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="lesson-content-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="quiz-placeholder"', false);
    }

    public function test_video_lesson_with_an_unrecognizable_url_offers_manual_completion(): void
    {
        $lesson = $this->publishedLesson(['type' => 'content']);
        DB::table('lessons')->where('id', $lesson->id)->update(['video_url' => 'https://vimeo.com/12345', 'video_provider' => null]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="video-unavailable-'.$lesson->id.'"', false);
        $response->assertSee('dusk="mark-complete-button"', false);
        $response->assertDontSee('data-video-player', false);
    }

    public function test_pdf_only_lesson_renders_the_pdf_viewer(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('orgs/1/courses/1/pdfs/material.pdf', '%PDF-1.4');

        $lesson = $this->publishedLesson([
            'type' => 'content',
            'pdf_path' => 'orgs/1/courses/1/pdfs/material.pdf',
            'content_text' => 'Texto que não deve aparecer.',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertSee('dusk="pdf-download-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="video-player-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="lesson-content-'.$lesson->id.'"', false);
    }

    public function test_text_lesson_renders_the_lesson_content_block(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="lesson-content-'.$lesson->id.'"', false);
        $response->assertSee('Conteúdo textual da aula.', false);
        $response->assertDontSee('dusk="video-player-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="quiz-placeholder"', false);
    }

    public function test_lesson_without_any_content_still_renders_the_completion_controls(): void
    {
        $lesson = $this->publishedLesson(['type' => 'content']);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="lesson-empty-'.$lesson->id.'"', false);
        $response->assertSee('dusk="mark-complete-button"', false);
        $response->assertSee('dusk="lesson-completed-badge"', false);
    }

    /**
     * `LessonPlayer.resolveLessonScope()` narrows a completion update to the
     * card of the lesson it belongs to, and finds that card through the
     * `data-lesson-id` anchor. Without the attribute on the manual button the
     * scoping silently degrades to the whole document.
     */
    public function test_completion_controls_carry_the_lesson_id_the_player_scopes_by(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        $html = $this->get(route('classroom.lesson', $lesson))->getContent();

        foreach (['mark-complete-button', 'lesson-completed-badge'] as $selector) {
            $this->assertStringContainsString(
                'data-lesson-id="'.$lesson->id.'"',
                $this->tagFor($html, $selector),
                "The `{$selector}` element must carry `data-lesson-id` so the player can scope the update."
            );
        }
    }

    public function test_completion_controls_never_use_the_hidden_attribute(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        $html = $this->get(route('classroom.lesson', $lesson))->getContent();

        foreach (['lesson-completed-badge', 'mark-complete-button'] as $selector) {
            $tag = $this->tagFor($html, $selector);

            $this->assertDoesNotMatchRegularExpression(
                '/\shidden(\s|=|>|\/)/',
                $tag,
                "The `{$selector}` element must never carry the `hidden` attribute: "
                .'the theme ships `[hidden] { display: none !important; }`, which the '
                .'player JS cannot override by toggling classes.'
            );
        }
    }

    public function test_pending_lesson_shows_the_button_and_hides_the_badge_through_d_none(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        $html = $this->get(route('classroom.lesson', $lesson))->getContent();

        $this->assertStringContainsString('d-none', $this->tagFor($html, 'lesson-completed-badge'));
        $this->assertStringNotContainsString('d-none', $this->tagFor($html, 'mark-complete-button'));
    }

    public function test_completed_lesson_shows_the_badge_and_hides_the_button_through_d_none(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        LessonProgress::create([
            'user_id' => auth()->id(),
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => now(),
        ]);

        $html = $this->get(route('classroom.lesson', $lesson))->getContent();

        $this->assertStringNotContainsString('d-none', $this->tagFor($html, 'lesson-completed-badge'));
        $this->assertStringContainsString('d-none', $this->tagFor($html, 'mark-complete-button'));
    }

    public function test_lesson_content_text_is_html_escaped(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => "<script>alert(1)</script>\nSegunda linha & \"aspas\".",
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('Segunda linha &amp; ', $html);
        $this->assertMatchesRegularExpression(
            '/dusk="lesson-content-'.$lesson->id.'"[^>]*>\s*&lt;script&gt;/',
            $html
        );
    }

    /**
     * O padding de 32px do card não existe na escala de spacers do Bootstrap
     * (`p-4` = 24px, `p-5` = 48px), então ele mora em `.ds-lesson-card`. O
     * teste cobre os dois lados do contrato: a tela usa a classe e a classe
     * declara o valor — assertar só os tokens deixaria passar um
     * `padding: 8px` no SCSS.
     */
    public function test_lesson_content_card_padding_comes_from_the_project_class(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        $html = $this->get(route('classroom.lesson', $lesson))->getContent();

        $matched = preg_match('/<div[^<>]*class="[^"]*ds-lesson-card[^"]*"[^<>]*>/', $html, $matches);
        $this->assertSame(1, $matched, 'The lesson content card must carry the `ds-lesson-card` class.');

        $this->assertContains('ds-lesson-card', $this->classTokens($matches[0]));

        $this->assertMatchesRegularExpression(
            '/padding:\s*32px\s*;/',
            $this->scssRule('.ds-lesson-card'),
            '`.ds-lesson-card` must declare the 32px padding the design mandates.'
        );
    }

    /**
     * `.border-dashed` é classe fantasma (declarada só em
     * `_reorder-list.scss`), então a borda tracejada do hand-off de prova
     * pertence ao próprio `.ds-quiz-placeholder`. Asserta a classe na tela e a
     * declaração no SCSS: sem a segunda metade, apagar a borda passaria verde.
     */
    public function test_quiz_placeholder_owns_its_dashed_border(): void
    {
        $lesson = $this->publishedLesson(['type' => 'quiz']);

        $html = $this->get(route('classroom.lesson', $lesson))->getContent();

        $this->assertContains(
            'ds-quiz-placeholder',
            $this->classTokens($this->tagFor($html, 'quiz-placeholder'))
        );

        $this->assertMatchesRegularExpression(
            '/border:\s*[^;]*dashed[^;]*;/',
            $this->scssRule('.ds-quiz-placeholder'),
            'The quiz placeholder must own its dashed border instead of borrowing a utility class.'
        );
    }

    public function test_pdf_lesson_whose_file_is_missing_shows_a_notice_instead_of_an_empty_viewer(): void
    {
        Storage::fake('public');

        $lesson = $this->publishedLesson([
            'type' => 'content',
            'pdf_path' => 'orgs/1/courses/1/pdfs/arquivo-inexistente.pdf',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertDontSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="pdf-download-'.$lesson->id.'"', false);
        $response->assertSee('dusk="pdf-unavailable-'.$lesson->id.'"', false);
        $response->assertSee('Documento indisponível');
        $response->assertSee('dusk="mark-complete-button"', false);
    }

    public function test_pdf_lesson_whose_file_exists_renders_the_viewer_and_the_download_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('orgs/1/courses/1/pdfs/material.pdf', '%PDF-1.4');

        $lesson = $this->publishedLesson([
            'type' => 'content',
            'pdf_path' => 'orgs/1/courses/1/pdfs/material.pdf',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="pdf-viewer-'.$lesson->id.'"', false);
        $response->assertSee('dusk="pdf-download-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="pdf-unavailable-'.$lesson->id.'"', false);
    }

    public function test_gestor_previewing_a_lesson_without_enrollment_cannot_mark_it_complete(): void
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $organization->id]);
        $module = Module::factory()->for($course)->create();

        /** @var Lesson $lesson */
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'content',
            'content_text' => 'Conteúdo textual da aula.',
            'is_published' => true,
        ]);

        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $organization->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->actingAs($gestor);

        $this->get(route('classroom.lesson', $lesson))
            ->assertOk()
            ->assertDontSee('dusk="mark-complete-button"', false);

        $this->postJson(route('lessons.complete', $lesson))->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $gestor->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_gestor_previewing_a_video_lesson_does_not_get_the_progress_polling_wiring(): void
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $organization->id]);
        $module = Module::factory()->for($course)->create();

        /** @var Lesson $lesson */
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_published' => true,
        ]);

        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $organization->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->actingAs($gestor);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        // O player continua visível para conferência (fachada + controles
        // customizados), mas sem o polling que o endpoint recusaria com 403
        // a cada 5 segundos.
        $response->assertSee('dusk="video-player-'.$lesson->id.'"', false);
        $response->assertSee('data-video-player', false);
        $response->assertDontSee('data-progress-url', false);

        $this->postJson(route('lessons.progress', $lesson), [
            'watched_seconds' => 10,
            'duration_seconds' => 100,
        ])->assertForbidden();
    }

    public function test_enrolled_student_keeps_the_progress_polling_wiring_on_a_video_lesson(): void
    {
        $lesson = $this->publishedLesson([
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-player', false);
        $response->assertSee('data-progress-url', false);
    }

    public function test_image_lesson_whose_file_is_missing_shows_the_same_neutral_notice_as_a_pdf(): void
    {
        Storage::fake('public');

        $lesson = $this->publishedLesson([
            'type' => 'content',
            'image_path' => 'orgs/1/courses/1/images/inexistente.png',
            'content_text' => 'Conteúdo textual da aula.',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertDontSee('dusk="lesson-image-'.$lesson->id.'"', false);
        $response->assertSee('dusk="image-unavailable-'.$lesson->id.'"', false);
        $response->assertSee('Imagem indisponível');
    }

    public function test_image_lesson_whose_file_exists_renders_the_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('orgs/1/courses/1/images/diagrama.png', 'fake-png');

        $lesson = $this->publishedLesson([
            'type' => 'content',
            'image_path' => 'orgs/1/courses/1/images/diagrama.png',
        ]);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="lesson-image-'.$lesson->id.'"', false);
        $response->assertDontSee('dusk="image-unavailable-'.$lesson->id.'"', false);
    }

    /**
     * Creates a published lesson with the given raw column values and
     * authenticates an ALUNO enrolled in its course.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function publishedLesson(array $attributes): Lesson
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $organization->id]);
        $module = Module::factory()->for($course)->create();

        /** @var Lesson $lesson */
        $lesson = Lesson::factory()->for($module)->create($attributes + ['is_published' => true]);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'progress_percentage' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($aluno);

        return $lesson;
    }

    /**
     * Returns the class tokens of an opening tag, so an assertion matches whole
     * utility classes instead of substrings (`p-4` must not hide behind `p-40`,
     * and must be caught wherever it sits in the attribute).
     *
     * @return array<int, string>
     */
    private function classTokens(string $tag): array
    {
        if (preg_match('/class="([^"]*)"/', $tag, $matches) !== 1) {
            return [];
        }

        return preg_split('/\s+/', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Returns the declaration block of a top-level rule in the classroom
     * stylesheet, so a test can assert on the property VALUE the class ships,
     * not merely on the presence of its name in the markup.
     */
    private function scssRule(string $selector): string
    {
        $stylesheet = file_get_contents(resource_path('scss/components/_classroom.scss'));

        $matched = preg_match(
            '/^'.preg_quote($selector, '/').'\s*\{(.*?)^\}/ms',
            $stylesheet,
            $matches
        );

        $this->assertSame(1, $matched, "No top-level `{$selector}` rule in `_classroom.scss`.");

        return $matches[1];
    }

    /**
     * Returns the single opening tag carrying the given dusk selector.
     */
    private function tagFor(string $html, string $duskSelector): string
    {
        $matched = preg_match('/<[^<>]*dusk="'.preg_quote($duskSelector, '/').'"[^<>]*>/', $html, $matches);

        $this->assertSame(1, $matched, "No element with dusk=\"{$duskSelector}\" was rendered.");

        return $matches[0];
    }
}
