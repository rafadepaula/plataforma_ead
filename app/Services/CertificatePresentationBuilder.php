<?php

namespace App\Services;

use App\Models\Certificate;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the measured `presentation` contract consumed by
 * `resources/views/certificates/pdf.blade.php` (see ClickUp 86e34v8z6 and
 * the agreed dimensions in `/tmp/certificados_contrato_2026090503.md`):
 *
 * - Text is wrapped server-side with the REAL font metrics of the renderer
 *   (Dompdf's `FontMetrics`), not by character counting — same-length
 *   strings have different widths. Each element shrinks through
 *   deterministic font-size bands until the wrapped lines fit its box;
 *   the contract's worst cases (255/200/150 chars, no spaces) are proven
 *   to fit by `CertificatePdfTest`.
 * - Wrapping never loses characters: oversized single words are
 *   hard-broken mid-word, there is no ellipsis and no truncation.
 * - The Organization logo is resolved defensively (path traversal, remote
 *   URLs, symlink escapes, truncated/corrupted and non-raster content all
 *   degrade to `null` — the template's typographic fallback) and returned
 *   with dimensions already fitted proportionally into its box, so the
 *   template never stretches it and never depends on `object-fit`.
 *
 * The template must render the same font family/style and line-height
 * factor used to measure (`font_family`/`font_style`/`line_height_factor`
 * below) — the fit guarantee breaks if it does not.
 */
class CertificatePresentationBuilder
{
    private const MM_PER_PT = 25.4 / 72;

    /** Dompdf's default DPI — px→mm conversion for logo dimensions. */
    private const PX_PER_MM = 96 / 25.4;

    private const LOGO_BOX_WIDTH_MM = 45.0;

    private const LOGO_BOX_HEIGHT_MM = 18.0;

    /**
     * Per-element measurement configuration. Bands are sorted descending;
     * the largest size whose wrapped lines fit `box_height_mm` wins.
     *
     * @var array<string, array{font_family: string, font_style: string, box_width_mm: float, box_height_mm: float, font_sizes_pt: list<float>, line_height_factor: float}>
     */
    private const ELEMENTS = [
        'student' => [
            'font_family' => 'DejaVu Serif',
            'font_style' => 'normal',
            'box_width_mm' => 249.0,
            'box_height_mm' => 55.0,
            'font_sizes_pt' => [32.0, 30.0, 28.0, 26.0, 24.0, 22.0, 20.0, 18.0],
            'line_height_factor' => 1.2,
        ],
        'course' => [
            'font_family' => 'DejaVu Serif',
            'font_style' => 'normal',
            'box_width_mm' => 249.0,
            'box_height_mm' => 27.0,
            'font_sizes_pt' => [18.0, 16.0, 14.0, 12.0],
            'line_height_factor' => 1.5,
        ],
        'organization' => [
            'font_family' => 'DejaVu Sans',
            'font_style' => 'normal',
            'box_width_mm' => 190.0,
            'box_height_mm' => 18.0,
            'font_sizes_pt' => [12.0, 10.0],
            'line_height_factor' => 1.5,
        ],
    ];

    private ?FontMetrics $fontMetrics = null;

    /**
     * @return array{logo: ?array{src: string, widthMm: float, heightMm: float}, presentation: array<string, array{lines: list<string>, fontSize: float}>}
     */
    public function build(Certificate $certificate): array
    {
        $organization = $certificate->course->organization;

        return [
            'logo' => $this->resolveLogo($organization->logo_path),
            'presentation' => [
                'student' => $this->fitText((string) $certificate->user->name, 'student'),
                'course' => $this->fitText((string) $certificate->course->title, 'course'),
                'organization' => $this->fitText((string) $organization->name, 'organization'),
            ],
        ];
    }

    /**
     * Picks the largest font-size band whose wrapped lines fit the box.
     * Falls back to the smallest band's full wrap when even it does not
     * fit (never truncates — the caller sees exactly which text overflows
     * through the returned line count).
     *
     * @return array{lines: list<string>, fontSize: float}
     */
    private function fitText(string $text, string $element): array
    {
        $config = self::ELEMENTS[$element];
        $font = $this->fontMetrics()->getFont($config['font_family'], $config['font_style']);

        foreach ($config['font_sizes_pt'] as $sizePt) {
            $lines = $this->wrapText($text, $font, $sizePt, $config['box_width_mm']);
            $requiredMm = count($lines) * $sizePt * $config['line_height_factor'] * self::MM_PER_PT;

            if ($requiredMm <= $config['box_height_mm']) {
                return ['lines' => $lines, 'fontSize' => $sizePt];
            }
        }

        $smallestPt = $config['font_sizes_pt'][array_key_last($config['font_sizes_pt'])];

        return [
            'lines' => $this->wrapText($text, $font, $smallestPt, $config['box_width_mm']),
            'fontSize' => $smallestPt,
        ];
    }

    /**
     * Greedy word wrap against real font widths; a single word wider than
     * the box is hard-broken character by character (zero character loss).
     *
     * @return list<string>
     */
    private function wrapText(string $text, string $fontFile, float $sizePt, float $boxWidthMm): array
    {
        $maxWidthPt = $boxWidthMm / self::MM_PER_PT;
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;

            if ($this->fontMetrics()->getTextWidth($candidate, $fontFile, $sizePt) <= $maxWidthPt) {
                $line = $candidate;

                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }

            if ($this->fontMetrics()->getTextWidth($word, $fontFile, $sizePt) <= $maxWidthPt) {
                $line = $word;

                continue;
            }

            foreach ($this->chunkOversizedWord($word, $fontFile, $sizePt, $maxWidthPt) as $chunk) {
                $lines[] = $chunk;
            }
            $line = '';
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * Breaks a word wider than the box into width-fitting chunks.
     *
     * @return list<string>
     */
    private function chunkOversizedWord(string $word, string $fontFile, float $sizePt, float $maxWidthPt): array
    {
        $chunks = [];
        $chunk = '';

        foreach (mb_str_split($word) as $char) {
            $candidate = $chunk.$char;

            if ($chunk !== '' && $this->fontMetrics()->getTextWidth($candidate, $fontFile, $sizePt) > $maxWidthPt) {
                $chunks[] = $chunk;
                $chunk = $char;

                continue;
            }

            $chunk = $candidate;
        }

        if ($chunk !== '') {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * Safe local resolution of the Organization logo. Any doubt degrades
     * to `null` (typographic fallback) — never an exception, never a
     * remote fetch, never a file outside the storage root.
     *
     * @return ?array{src: string, widthMm: float, heightMm: float}
     */
    private function resolveLogo(?string $logoPath): ?array
    {
        if ($logoPath === null || trim($logoPath) === '') {
            return null;
        }

        // Remote URLs and absolute paths are never rendered — assets must
        // be local and the renderer's remote access stays disabled.
        if (str_starts_with($logoPath, '/') || str_starts_with($logoPath, '\\')) {
            return null;
        }

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $logoPath) === 1) {
            return null;
        }

        if (str_contains($logoPath, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $target = realpath($disk->path($logoPath));

        // `realpath` resolves symlinks — an escaping link is rejected here.
        if ($root === false || $target === false || ! str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! is_file($target) || ! is_readable($target)) {
            return null;
        }

        $bytes = (string) file_get_contents($target);

        // Full GD decode — catches truncated/corrupted images that plain
        // `getimagesize` would still accept.
        $decoded = @imagecreatefromstring($bytes);

        if ($decoded === false) {
            return null;
        }
        imagedestroy($decoded);

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        [$widthPx, $heightPx] = $info;
        $widthMm = $widthPx / self::PX_PER_MM;
        $heightMm = $heightPx / self::PX_PER_MM;

        // Fit proportionally into the box; never upscale past natural size.
        $scale = min(
            self::LOGO_BOX_WIDTH_MM / $widthMm,
            self::LOGO_BOX_HEIGHT_MM / $heightMm,
            1.0,
        );

        return [
            'src' => 'data:'.$info['mime'].';base64,'.base64_encode($bytes),
            'widthMm' => round($widthMm * $scale, 2),
            'heightMm' => round($heightMm * $scale, 2),
        ];
    }

    /**
     * Memoized Dompdf font-metrics provider — measurement uses the very
     * same bundled DejaVu fonts the renderer embeds.
     */
    private function fontMetrics(): FontMetrics
    {
        return $this->fontMetrics ??= (new Dompdf)->getFontMetrics();
    }
}
