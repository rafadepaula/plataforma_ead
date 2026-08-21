<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * Guardrail required by the test-contract guideline:
 * the compiled production CSS must never contain a red/orange/yellow hue,
 * nor a `border-radius: 0` outside Bootstrap's own known resets.
 *
 * This test reads the CSS bundle already produced by `npm run build`
 * (resolved through `public/build/manifest.json`) instead of invoking Sass
 * itself, so it stays fast and exercises exactly what ships to the browser.
 */
class CompiledCssTokenRegressionTest extends TestCase
{
    /**
     * Selector fragments that legitimately compile to `border-radius: 0`
     * as part of Bootstrap's own component resets, not a design violation.
     *
     * @var array<int, string>
     */
    private const BORDER_RADIUS_ZERO_EXCEPTIONS = [
        'accordion',
        'modal-fullscreen',
        'list-group-flush',
        'file-selector-button',
        'rounded-0',
    ];

    public function test_compiled_css_contains_no_forbidden_hue_colors(): void
    {
        $css = $this->compiledCss();

        $offenders = $this->forbiddenHueHexColors($css);

        $this->assertSame(
            [],
            $offenders,
            'Compiled CSS contains red/orange/yellow-hued colors, which are '
            .'forbidden by the design system: '.implode(', ', $offenders)
        );
    }

    public function test_compiled_css_has_no_unexplained_zero_border_radius(): void
    {
        $css = $this->compiledCss();

        $offenders = $this->unexplainedZeroBorderRadiusSelectors($css);

        $this->assertSame(
            [],
            $offenders,
            'Compiled CSS contains `border-radius: 0` outside the known '
            .'Bootstrap resets, in selector(s): '.implode(' | ', $offenders)
        );
    }

    private function compiledCss(): string
    {
        $manifestPath = public_path('build/manifest.json');

        $this->assertFileExists(
            $manifestPath,
            'public/build/manifest.json not found. Run `vendor/bin/sail npm run build` first.'
        );

        /** @var array<string, array{file: string}> $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        $this->assertArrayHasKey(
            'resources/scss/app.scss',
            $manifest,
            'public/build/manifest.json has no entry for resources/scss/app.scss.'
        );

        $cssPath = public_path('build/'.$manifest['resources/scss/app.scss']['file']);

        $this->assertFileExists($cssPath, "Compiled CSS file not found at {$cssPath}.");

        return (string) file_get_contents($cssPath);
    }

    /**
     * @return array<int, string>
     */
    private function forbiddenHueHexColors(string $css): array
    {
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $matches);

        $offenders = [];

        foreach (array_unique($matches[0]) as $hex) {
            [$hue, $saturation] = $this->hexToHueAndSaturation($hex);

            if ($hue === null) {
                continue;
            }

            // Skip near-grey/near-white/near-black colors: low saturation
            // means the hue angle is not visually perceivable.
            if ($saturation <= 0.12) {
                continue;
            }

            // Forbidden band: red through yellow (roughly 345°-360° and 0°-65°).
            if ($hue <= 65.0 || $hue >= 345.0) {
                $offenders[] = $hex;
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * @return array{0: ?float, 1: float}
     */
    private function hexToHueAndSaturation(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $hex = implode('', array_map(fn ($c) => str_repeat($c, 2), str_split(substr($hex, 0, 3))));
        }

        if (strlen($hex) < 6) {
            return [null, 0.0];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255.0;
        $g = hexdec(substr($hex, 2, 2)) / 255.0;
        $b = hexdec(substr($hex, 4, 2)) / 255.0;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = (float) ($max - $min);
        $lightness = ($max + $min) / 2;

        if (abs($delta) < 1e-9) {
            return [0.0, 0.0];
        }

        $saturation = $lightness > 0.5
            ? $delta / (2 - $max - $min)
            : $delta / ($max + $min);

        $hue = match ($max) {
            $r => fmod((($g - $b) / $delta), 6),
            $g => (($b - $r) / $delta) + 2,
            default => (($r - $g) / $delta) + 4,
        };

        $hue *= 60;

        if ($hue < 0) {
            $hue += 360;
        }

        return [$hue, $saturation];
    }

    /**
     * @return array<int, string>
     */
    private function unexplainedZeroBorderRadiusSelectors(string $css): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $offenders = [];

        foreach ($rules as $rule) {
            $selectorList = $rule[1];
            $body = $rule[2];

            if (! preg_match('/border-radius:\s*0(?:px)?\s*(;|$)/', $body)) {
                continue;
            }

            foreach (explode(',', $selectorList) as $selector) {
                $selector = trim($selector);

                if ($selector === '') {
                    continue;
                }

                $isKnownException = false;

                foreach (self::BORDER_RADIUS_ZERO_EXCEPTIONS as $exception) {
                    if (str_contains($selector, $exception)) {
                        $isKnownException = true;
                        break;
                    }
                }

                // Bare `button` element reset (before component-level radius
                // overrides apply later in the cascade) is a known Bootstrap
                // base reset, not a design violation.
                if ($selector === 'button') {
                    $isKnownException = true;
                }

                if (! $isKnownException) {
                    $offenders[] = $selector;
                }
            }
        }

        sort($offenders);

        return array_values(array_unique($offenders));
    }
}
