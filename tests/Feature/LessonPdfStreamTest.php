<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 *  the gated PDF stream (`GET lessons/{lesson}/pdf/{index}`,
 * `LessonPdfController::show`): enrolled students read the bytes inline from
 * the private `local` disk, everyone else is denied by the same
 * `auth` + `student.enrolled` + draft-visibility gates as the classroom.
 */
class LessonPdfStreamTest extends TestCase
{
    public function test_enrolled_student_streams_the_pdf_inline_with_no_store_headers(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes');
        $path = $lesson->pdfAttachments()->first()->path;

        $response = $this->get(route('lessons.pdf.show', [$lesson, 0]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('%PDF-1.4 fake-bytes', $response->streamedContent());
        $this->assertSame($path, $lesson->pdfAttachments()->first()->path);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes', enrolled: false);

        $this->get(route('lessons.pdf.show', [$lesson, 0]))->assertRedirect(route('login'));
    }

    public function test_aluno_without_enrollment_is_redirected_to_meus_cursos_with_error_flash(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes', enrolled: false);

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $this->get(route('lessons.pdf.show', [$lesson, 0]))
            ->assertRedirect(route('student.courses.index'))
            ->assertSessionHas('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
    }

    public function test_cross_org_gestor_is_forbidden(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes', enrolled: false);
        $otherOrg = Organization::factory()->create();

        /** @var User $gestor */
        $gestor = User::factory()->create(['org_id' => $otherOrg->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->actingAs($gestor);

        $this->get(route('lessons.pdf.show', [$lesson, 0]))->assertForbidden();
    }

    public function test_unpublished_lesson_is_404_for_aluno(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes', published: false);

        $this->get(route('lessons.pdf.show', [$lesson, 0]))->assertNotFound();
    }

    public function test_unpublished_lesson_streams_for_admin_preview(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes', published: false, enrolled: false);

        /** @var User $admin */
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $this->actingAs($admin);

        $this->get(route('lessons.pdf.show', [$lesson, 0]))->assertOk();
    }

    public function test_out_of_range_index_is_404(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf('%PDF-1.4 fake-bytes');

        $this->get(route('lessons.pdf.show', [$lesson, 5]))->assertNotFound();
    }

    public function test_missing_file_is_404(): void
    {
        Storage::fake('local');

        $lesson = $this->lessonWithPdf(null);

        $this->get(route('lessons.pdf.show', [$lesson, 0]))->assertNotFound();
    }

    public function test_second_pdf_resolves_by_index(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orgs/1/courses/1/pdfs/primeiro.pdf', '%PDF-1.4 primeiro');
        Storage::disk('local')->put('orgs/1/courses/1/pdfs/segundo.pdf', '%PDF-1.4 segundo');

        $organization = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $organization->id]);
        $module = Module::factory()->for($course)->create();

        /** @var Lesson $lesson */
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'content',
            'is_published' => true,
            'pdf_path' => 'orgs/1/courses/1/pdfs/primeiro.pdf',
        ]);
        $lesson->media()->createMany([
            ['kind' => 'pdf', 'path' => 'orgs/1/courses/1/pdfs/primeiro.pdf', 'original_name' => 'primeiro.pdf'],
            ['kind' => 'pdf', 'path' => 'orgs/1/courses/1/pdfs/segundo.pdf', 'original_name' => 'segundo.pdf'],
        ]);
        $this->actAsEnrolledAluno($course);

        $response = $this->get(route('lessons.pdf.show', [$lesson, 1]));

        $response->assertOk();
        $this->assertSame('%PDF-1.4 segundo', $response->streamedContent());
    }

    /**
     * Lesson carrying a single PDF attachment; optionally actually writes
     * the bytes to the faked `local` disk (pass null to simulate a missing
     * file) and optionally authenticates an enrolled ALUNO.
     */
    private function lessonWithPdf(?string $bytes, bool $published = true, bool $enrolled = true): Lesson
    {
        $organization = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $organization->id]);
        $module = Module::factory()->for($course)->create();

        /** @var Lesson $lesson */
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'content',
            'is_published' => $published,
            'pdf_path' => 'orgs/1/courses/1/pdfs/material.pdf',
        ]);

        if ($bytes !== null) {
            Storage::disk('local')->put('orgs/1/courses/1/pdfs/material.pdf', $bytes);
        }

        if ($enrolled) {
            $this->actAsEnrolledAluno($course);
        }

        return $lesson;
    }

    private function actAsEnrolledAluno(Course $course): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'progress_percentage' => 0,
            'enrolled_at' => now(),
        ]);
        $this->actingAs($aluno);

        return $aluno;
    }
}
