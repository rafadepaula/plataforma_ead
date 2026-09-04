<?php

namespace App\Services;

/**
 * Pure interval math behind honest video tracking (ClickUp task
 * 86e32nupm): a video's progress is the size of the UNION of the intervals
 * actually replayed, not the playhead position. A forward seek therefore
 * contributes nothing, replaying a segment never double-counts, and the
 * 90% auto-completion threshold fires on unique watched seconds.
 *
 * Ranges are normalized to sorted, disjoint `[start, end)` second pairs —
 * the exact shape persisted in `lesson_progress.watched_ranges`. Everything
 * here is stateless and side-effect free; callers own the persistence.
 */
final class VideoWatchCalculator
{
    /**
     * Coerces client-reported segments into normalized pair ranges,
     * clamping to `[0, duration]` and discarding empty/inverted segments.
     * Accepts both the wire shape (`['start' => int, 'end' => int]`) and
     * the storage shape (`[int, int]`).
     *
     * @param  list<mixed>  $segments
     * @return list<array{0: int, 1: int}>
     */
    public static function normalize(array $segments, int $duration): array
    {
        if ($duration <= 0) {
            return [];
        }

        $ranges = [];

        foreach ($segments as $segment) {
            $start = (int) (is_array($segment) ? ($segment['start'] ?? $segment[0] ?? 0) : 0);
            $end = (int) (is_array($segment) ? ($segment['end'] ?? $segment[1] ?? 0) : 0);

            $start = max(0, $start);
            $end = min($duration, $end);

            if ($end > $start) {
                $ranges[] = [$start, $end];
            }
        }

        return self::mergeRanges($ranges);
    }

    /**
     * Union of the stored ranges with the freshly reported ones — the
     * replacement for the old `GREATEST(current, reported)` playhead rule.
     * Inputs must already be normalized pair lists (or empty).
     *
     * @param  list<array{0: int, 1: int}>  $existing
     * @param  list<array{0: int, 1: int}>  $incoming
     * @return list<array{0: int, 1: int}>
     */
    public static function merge(array $existing, array $incoming): array
    {
        return self::mergeRanges([...$existing, ...$incoming]);
    }

    /**
     * Distinct seconds covered by the union — the figure the 90% threshold
     * reads.
     *
     * @param  list<array{0: int, 1: int}>  $ranges
     */
    public static function uniqueSeconds(array $ranges): int
    {
        $seconds = 0;

        foreach ($ranges as [$start, $end]) {
            $seconds += max(0, $end - $start);
        }

        return $seconds;
    }

    /**
     * Watched percentage on the familiar 0–100 scale, or `0.0` when the
     * duration is unknown (a division by zero must never surface).
     */
    public static function percent(int $uniqueSeconds, int $durationSeconds): float
    {
        if ($durationSeconds <= 0) {
            return 0.0;
        }

        return round(($uniqueSeconds / $durationSeconds) * 100, 2);
    }

    /**
     * The 90% auto-completion gate, inclusive. An unknown duration never
     * completes a lesson.
     */
    public static function reachedCompletion(int $uniqueSeconds, int $durationSeconds, float $threshold = 0.90): bool
    {
        if ($durationSeconds <= 0) {
            return false;
        }

        return ($uniqueSeconds / $durationSeconds) >= $threshold;
    }

    /**
     * The "resume from where you left off" position: the FIRST unwatched
     * second, walking the contiguous coverage from 0 across the merged
     * ranges. Watched 0–2min and 8–9min? The gap at 2min is where playback
     * resumes — not second 540, which would replay a seen stretch. A fully
     * covered (or empty) set yields its end (or 0); callers clamp against
     * the real duration before seeking.
     *
     * @param  list<array{0: int, 1: int}>  $ranges  merged, sorted, disjoint
     */
    public static function resumePosition(array $ranges): int
    {
        $cursor = 0;

        foreach ($ranges as [$start, $end]) {
            if ($start > $cursor) {
                break;
            }

            $cursor = max($cursor, $end);
        }

        return $cursor;
    }

    /**
     * Sorts by start and sweeps once, coalescing overlapping AND adjacent
     * ranges (`[0,60]` + `[60,120]` is one contiguous stretch of watched
     * seconds, so it persists as a single interval).
     *
     * @param  list<array{0: int, 1: int}>  $ranges
     * @return list<array{0: int, 1: int}>
     */
    private static function mergeRanges(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        usort($ranges, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [$ranges[0]];

        foreach (array_slice($ranges, 1) as [$start, $end]) {
            $lastKey = array_key_last($merged);

            if ($start <= $merged[$lastKey][1]) {
                $merged[$lastKey][1] = max($merged[$lastKey][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }
}
