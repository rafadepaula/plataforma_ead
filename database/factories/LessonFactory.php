<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 *
 * `module_id` is intentionally left out of the default definition (mirrors
 * `CourseFactory`'s `org_id` convention): callers set it explicitly via
 * `->for(Module::factory())` / `->create(['module_id' => $module->id])`.
 *
 * The base definition leaves all four content columns
 * (`content_text`/`image_path`/`pdf_path`/`video_url`) empty — the four
 * content kinds are each opt-in via a dedicated state so a test only
 * populates the column(s) relevant to the kind it is exercising.
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'type' => 'content',
            'content_text' => null,
            'video_provider' => null,
            'video_url' => null,
            'pdf_path' => null,
            'image_path' => null,
            'order_index' => 0,
            'is_published' => false,
        ];
    }

    /**
     * Rich-text lesson: only `content_text` populated.
     */
    public function richText(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'content',
            'content_text' => fake()->paragraphs(3, true),
            'video_provider' => null,
            'video_url' => null,
            'pdf_path' => null,
            'image_path' => null,
        ]);
    }

    /**
     * Image lesson: only `image_path` populated.
     */
    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'content',
            'image_path' => 'orgs/1/courses/1/images/'.fake()->uuid().'.jpg',
            'content_text' => null,
            'video_provider' => null,
            'video_url' => null,
            'pdf_path' => null,
        ]);
    }

    /**
     * PDF lesson: only `pdf_path` populated.
     */
    public function withPdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'content',
            'pdf_path' => 'orgs/1/courses/1/pdfs/'.fake()->uuid().'.pdf',
            'content_text' => null,
            'video_provider' => null,
            'video_url' => null,
            'image_path' => null,
        ]);
    }

    /**
     * YouTube lesson: only `video_url` populated, already in the
     * sanitized `YoutubeSanitizerService` (privacy-enhanced) embed form.
     */
    public function withYoutube(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'content_text' => null,
            'pdf_path' => null,
            'image_path' => null,
        ]);
    }

    /**
     * Vimeo lesson: only `video_url` populated, already in the sanitized
     * `VimeoSanitizerService` embed form.
     */
    public function withVimeo(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'content',
            'video_provider' => 'vimeo',
            'video_url' => 'https://player.vimeo.com/video/76979871',
            'content_text' => null,
            'pdf_path' => null,
            'image_path' => null,
        ]);
    }

    /**
     * Multi-file lesson: attaches `LessonMedia` rows after creation
     * (default one image + one PDF; pass counts to customize). Also syncs
     * the legacy `image_path`/`pdf_path` columns to the first attachment of
     * each kind, mirroring `LessonController::syncMedia()`.
     */
    public function media(int $images = 1, int $pdfs = 1): static
    {
        return $this->afterCreating(function (Lesson $lesson) use ($images, $pdfs): void {
            $firstImage = null;
            $firstPdf = null;

            if ($images > 0) {
                LessonMedia::factory()->count($images)->image()->for($lesson, 'lesson')->create();
                $firstImage = $lesson->images()->orderBy('id')->value('path');
            }

            if ($pdfs > 0) {
                LessonMedia::factory()->count($pdfs)->pdf()->for($lesson, 'lesson')->create();
                $firstPdf = $lesson->pdfs()->orderBy('id')->value('path');
            }

            $lesson->forceFill([
                'image_path' => $firstImage,
                'pdf_path' => $firstPdf,
            ])->save();
        });
    }
}
