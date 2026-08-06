<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonYoutubeEmbedRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * BUG-002 — Scenario A: CourseSeeder must persist youtube_url in sanitized
     * embed form, never in raw `watch?v=` form.
     */
    public function test_course_seeder_persists_sanitized_youtube_embed_urls(): void
    {
        // Seed the base organizations that CourseSeeder depends on
        $this->seed(OrganizationSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(CourseSeeder::class);

        $embedPattern = '#^https://www\.youtube\.com/embed/[A-Za-z0-9_-]{11}$#';

        $videoLessons = Lesson::whereNotNull('youtube_url')->get();

        $this->assertGreaterThan(0, $videoLessons->count(), 'Expected at least one video lesson from CourseSeeder.');

        foreach ($videoLessons as $lesson) {
            $this->assertMatchesRegularExpression(
                $embedPattern,
                $lesson->youtube_url,
                "Lesson #{$lesson->id} (\"{$lesson->title}\") youtube_url is not in embed form: {$lesson->youtube_url}"
            );
        }

        // Assert the specific known lesson has the correct embed URL
        $introLesson = Lesson::where('title', 'Introdução à Arquitetura Multitenant')->first();
        $this->assertNotNull($introLesson);
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            $introLesson->youtube_url
        );
    }

    /**
     * BUG-002 — Scenario B: Student classroom view must render iframe with
     * sanitized embed src and correct data-video-id attribute.
     */
    public function test_student_lesson_view_embeds_iframe_with_sanitized_src(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->withYoutube()->create([
            'is_published' => true,
        ]);

        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($aluno);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();

        // Assert iframe src starts with sanitized embed URL (Blade escapes & to &amp;)
        $this->assertStringContainsString(
            'src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0',
            $response->getContent()
        );

        // Assert data-video-id contains the real 11-char video ID (not "watch")
        $response->assertSee('data-video-id="dQw4w9WgXcQ"', false);
    }

    /**
     * BUG-002 — Regression: When youtube_url is already sanitized (embed form),
     * the view must extract the correct video ID via basename(parse_url()).
     */
    public function test_student_lesson_view_renders_correct_video_id_data_attribute(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        // Use a different known video ID to prove extraction works generically
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'content',
            'youtube_url' => 'https://www.youtube.com/embed/9bZkp7q19f0',
            'is_published' => true,
        ]);

        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($aluno);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-id="9bZkp7q19f0"', false);
    }
}
