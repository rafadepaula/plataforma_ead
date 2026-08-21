<?php

namespace Tests\Feature\Theme;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardrail required by the test-contract guideline:
 * `resources/views/` must never contain an inline `style="..."` attribute,
 * except in the documented, permanent exceptions below:
 *
 * - `certificates/pdf.blade.php`, where dompdf cannot render the
 *   Bootstrap/component CSS pipeline and hardcoded inline styles are
 *   required.
 * - `components/ui/progress.blade.php`, where the progress-bar width is an
 *   arbitrary runtime percentage (0-100) that Bootstrap has no utility class
 *   for; the exception is documented in-file and must not be "fixed" by
 *   removing the `style` attribute.
 */
class InlineStyleRegressionTest extends TestCase
{
    private const ALLOWED_FILES = [
        'certificates/pdf.blade.php',
        'components/ui/progress.blade.php',
    ];

    public function test_views_contain_no_inline_style_attribute_outside_the_pdf_template(): void
    {
        $viewsPath = resource_path('views');

        $offenders = [];

        foreach (Finder::create()->files()->in($viewsPath)->name('*.blade.php') as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if (in_array($relativePath, self::ALLOWED_FILES, true)) {
                continue;
            }

            $contents = $this->withoutBladeComments($file->getContents());

            if (preg_match('/style="/', $contents)) {
                $offenders[] = $relativePath;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'Inline `style="..."` attribute found outside the allowed exceptions ('
            .implode(', ', self::ALLOWED_FILES).'), in: '.implode(', ', $offenders)
        );
    }

    private function withoutBladeComments(string $contents): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
    }
}
