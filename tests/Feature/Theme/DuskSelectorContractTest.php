<?php

namespace Tests\Feature\Theme;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardrail required by the test-contract guideline:
 * the 388 `dusk="..."` selectors present in `resources/views/` before the
 * front-end redesign must survive verbatim (same file, same value) for the
 * whole duration of the redesign, because 25 files in `tests/Browser/`
 * depend on them.
 *
 * `tests/fixtures/dusk-selectors-snapshot.json` is the frozen, versioned
 * baseline captured from the pre-redesign state. This test compares the
 * current count and set of selectors against that snapshot.
 */
class DuskSelectorContractTest extends TestCase
{
    private const SNAPSHOT_PATH = 'tests/fixtures/dusk-selectors-snapshot.json';

    public function test_dusk_selectors_match_the_versioned_snapshot(): void
    {
        $snapshotPath = base_path(self::SNAPSHOT_PATH);

        $this->assertFileExists($snapshotPath, self::SNAPSHOT_PATH.' is missing.');

        /** @var array{count: int, entries: array<int, array{file: string, selector: string}>} $snapshot */
        $snapshot = json_decode((string) file_get_contents($snapshotPath), true);

        $expected = $this->normalize($snapshot['entries']);
        $actual = $this->normalize($this->currentDuskSelectors());

        $this->assertSame(
            $snapshot['count'],
            count($actual),
            'Current dusk="..." selector count ('.count($actual).') no longer '
            .'matches the frozen snapshot count ('.$snapshot['count'].'). '
            .'No dusk selector may be renamed, removed, or moved without updating '
            .'the versioned snapshot deliberately.'
        );

        $missing = array_values(array_diff($expected, $actual));
        $unexpected = array_values(array_diff($actual, $expected));

        $this->assertSame(
            [],
            $missing,
            'Selector(s) present in the snapshot but missing from the views: '
            .implode(', ', $missing)
        );

        $this->assertSame(
            [],
            $unexpected,
            'Selector(s) present in the views but absent from the snapshot: '
            .implode(', ', $unexpected)
        );
    }

    /**
     * @return array<int, array{file: string, selector: string}>
     */
    private function currentDuskSelectors(): array
    {
        $viewsPath = resource_path('views');

        $entries = [];

        foreach (Finder::create()->files()->in($viewsPath)->name('*.blade.php') as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $contents = $file->getContents();

            if (preg_match_all('/dusk="([^"]*)"/', $contents, $matches)) {
                foreach ($matches[1] as $selector) {
                    $entries[] = ['file' => $relative, 'selector' => $selector];
                }
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, array{file: string, selector: string}>  $entries
     * @return array<int, string>
     */
    private function normalize(array $entries): array
    {
        $lines = array_map(
            static fn (array $entry): string => $entry['file'].'::'.$entry['selector'],
            $entries
        );

        sort($lines);

        return $lines;
    }
}
