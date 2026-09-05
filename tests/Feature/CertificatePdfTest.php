<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Services\CertificatePdfService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Document-level coverage for the professional certificate PDF (ClickUp
 * 86e34v8z6): explicit A4 landscape geometry, the measured
 * `presentation` contract (band fitting + zero-loss wrapping for the
 * 255/200/150 worst cases) and the security of the Organization logo
 * resolution (traversal/URL/symlink/truncation must all degrade to the
 * typographic fallback instead of a 500).
 *
 * These tests inspect the REAL rendered document bytes (`DomPdf::output()`)
 * — per the refinement, HTTP asserts alone do not prove the PDF.
 */
class CertificatePdfTest extends TestCase
{
    private CertificatePdfService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CertificatePdfService::class);
    }

    /**
     * Builds a certificate whose owner/course/organization carry the
     * given names (a compact factory shortcut shared by every case).
     *
     * @param  array{name?: string, course?: string, org?: string}  $names
     */
    private function certificateWith(array $names = []): Certificate
    {
        $org = Organization::factory()->create([
            'name' => $names['org'] ?? 'Instituição de Ensino Exemplo',
        ]);
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => $names['course'] ?? 'Curso de Aperfeiçoamento Profissional',
        ]);

        /** @var User $student */
        $student = User::factory()->create([
            'org_id' => null,
            'name' => $names['name'] ?? 'Maria Silva',
        ]);
        $student->assignRole('aluno');

        return Certificate::factory()->for($course)->for($student)->create();
    }

    /**
     * A real 40×20 px PNG produced with GD (2:1 aspect), used to exercise
     * the valid-logo path with genuinely decodable bytes.
     *
     * @return array{bytes: string, mime: string, width: int, height: int}
     */
    private function pngFixture(): array
    {
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return ['bytes' => $bytes, 'mime' => 'image/png', 'width' => 40, 'height' => 20];
    }

    public function test_generated_pdf_is_a4_landscape_with_a_single_page(): void
    {
        $output = $this->service->generate($this->certificateWith())->output();

        $this->assertStringStartsWith('%PDF', $output);
        // A4 landscape MediaBox: 297mm = 841.89pt wide, 210mm = 595.28pt tall.
        $this->assertMatchesRegularExpression('/841\.89\d*\s+595\.28\d*/', $output);
        $this->assertSame(
            1,
            preg_match_all('#/Type\s*/Page[^s]#', $output),
            'Certificate must render as exactly one page.',
        );
    }

    public function test_presentation_uses_the_largest_fitting_band_for_typical_names(): void
    {
        $result = $this->service->presentation()->build($this->certificateWith());

        $this->assertSame(32.0, $result['presentation']['student']['fontSize']);
        $this->assertSame(['Maria Silva'], $result['presentation']['student']['lines']);
        $this->assertSame(
            'Maria Silva',
            implode('', $result['presentation']['student']['lines']),
        );
    }

    public function test_worst_case_texts_fit_their_boxes_without_losing_characters(): void
    {
        $certificate = $this->certificateWith([
            'name' => str_repeat('W', 255),
            'course' => str_repeat('W', 200),
            'org' => str_repeat('W', 150),
        ]);

        $result = $this->service->presentation()->build($certificate);
        $presentation = $result['presentation'];

        // Final (guaranteed-fit) bands, per the agreed contract: the
        // 255-char no-space name lands at 18pt in 7 lines inside 55mm,
        // the 200-char course at 12pt in 4 lines inside 27mm, and the
        // 150-char organization at 10pt in 3 lines inside 18mm.
        $this->assertSame(18.0, $presentation['student']['fontSize']);
        $this->assertSame(7, count($presentation['student']['lines']));
        $this->assertSame(12.0, $presentation['course']['fontSize']);
        $this->assertSame(4, count($presentation['course']['lines']));
        $this->assertSame(10.0, $presentation['organization']['fontSize']);
        $this->assertSame(3, count($presentation['organization']['lines']));

        // Zero-loss: every character survives (no ellipsis, no cuts).
        $this->assertSame(
            str_repeat('W', 255),
            implode('', $presentation['student']['lines']),
        );
        $this->assertSame(
            str_repeat('W', 200),
            implode('', $presentation['course']['lines']),
        );
        $this->assertSame(
            str_repeat('W', 150),
            implode('', $presentation['organization']['lines']),
        );
    }

    public function test_valid_logo_is_embedded_as_a_data_uri_with_proportional_box_fit(): void
    {
        Storage::fake('public');
        $fixture = $this->pngFixture();
        Storage::disk('public')->put('logos/org.png', $fixture['bytes']);

        $org = Organization::factory()->create(['logo_path' => 'logos/org.png']);
        $course = Course::factory()->create(['org_id' => $org->id]);

        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $certificate = Certificate::factory()->for($course)->for($student)->create();

        $logo = $this->service->presentation()->build($certificate)['logo'];

        $this->assertNotNull($logo);
        $this->assertStringStartsWith('data:image/png;base64,', $logo['src']);
        // 40×20px at 96dpi = 10.58×5.29mm — smaller than the 45×18mm box,
        // so natural size is kept and the 2:1 aspect must be preserved.
        $this->assertEqualsWithDelta(10.58, $logo['widthMm'], 0.05);
        $this->assertEqualsWithDelta(5.29, $logo['heightMm'], 0.05);
        $this->assertEqualsWithDelta(2.0, $logo['widthMm'] / $logo['heightMm'], 0.01);
    }

    public function test_valid_logo_larger_than_the_box_is_scaled_down_proportionally(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(400, 200);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('logos/big.png', $bytes);

        $org = Organization::factory()->create(['logo_path' => 'logos/big.png']);
        $course = Course::factory()->create(['org_id' => $org->id]);

        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $certificate = Certificate::factory()->for($course)->for($student)->create();

        $logo = $this->service->presentation()->build($certificate)['logo'];

        $this->assertNotNull($logo);
        // 400×200px = 105.8×52.9mm → constrained by the 45mm width.
        $this->assertLessThanOrEqual(45.0, $logo['widthMm']);
        $this->assertLessThanOrEqual(18.0, $logo['heightMm']);
        $this->assertEqualsWithDelta(2.0, $logo['widthMm'] / $logo['heightMm'], 0.01);
    }

    public function test_logo_path_with_traversal_segments_falls_back_to_null(): void
    {
        Storage::fake('public');
        $certificate = $this->certificateWith();
        $certificate->course->organization->update(['logo_path' => '../secret.png']);

        $this->assertNull($this->service->presentation()->build($certificate)['logo']);
    }

    public function test_remote_logo_url_falls_back_to_null(): void
    {
        Storage::fake('public');
        $certificate = $this->certificateWith();
        $certificate->course->organization->update(['logo_path' => 'https://evil.example/logo.png']);

        $this->assertNull($this->service->presentation()->build($certificate)['logo']);
    }

    public function test_missing_logo_file_falls_back_to_null(): void
    {
        Storage::fake('public');
        $certificate = $this->certificateWith();
        $certificate->course->organization->update(['logo_path' => 'logos/missing.png']);

        $this->assertNull($this->service->presentation()->build($certificate)['logo']);
    }

    public function test_truncated_logo_file_falls_back_to_null(): void
    {
        Storage::fake('public');
        $fixture = $this->pngFixture();
        Storage::disk('public')->put('logos/cut.png', substr($fixture['bytes'], 0, intdiv(strlen($fixture['bytes']), 2)));

        $certificate = $this->certificateWith();
        $certificate->course->organization->update(['logo_path' => 'logos/cut.png']);

        $this->assertNull($this->service->presentation()->build($certificate)['logo']);
    }

    public function test_non_image_logo_file_falls_back_to_null(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $certificate = $this->certificateWith();
        $certificate->course->organization->update(['logo_path' => 'logos/logo.svg']);

        $this->assertNull($this->service->presentation()->build($certificate)['logo']);
    }

    public function test_logo_symlink_escaping_the_storage_root_falls_back_to_null(): void
    {
        Storage::fake('public');
        $fixture = $this->pngFixture();
        $outside = tempnam(sys_get_temp_dir(), 'outside-logo');
        file_put_contents($outside, $fixture['bytes']);

        $diskRoot = Storage::disk('public')->path('');
        @mkdir($diskRoot.'logos', 0777, true);
        symlink($outside, $diskRoot.'logos/evil.png');

        $certificate = $this->certificateWith();
        $certificate->course->organization->update(['logo_path' => 'logos/evil.png']);

        try {
            $this->assertNull($this->service->presentation()->build($certificate)['logo']);
        } finally {
            @unlink($diskRoot.'logos/evil.png');
            @unlink($outside);
        }
    }
}
