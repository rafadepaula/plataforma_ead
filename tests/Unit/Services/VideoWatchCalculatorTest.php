<?php

namespace Tests\Unit\Services;

use App\Services\VideoWatchCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Pure math behind the video tracking: the union of played intervals must
 * express time actually watched — seek gaps collapse out, replay never
 * double-counts — which is what makes the 90% auto-completion threshold
 * honest (see ClickUp task 86e32nupm).
 */
class VideoWatchCalculatorTest extends TestCase
{
    public function test_the_task_example_yields_three_minutes_of_a_ten_minute_video(): void
    {
        // 0–2min watched, seek to 8min, 8–9min watched: 3min = 30%, never 90%.
        $ranges = VideoWatchCalculator::merge(
            VideoWatchCalculator::normalize([['start' => 0, 'end' => 120]], 600),
            VideoWatchCalculator::normalize([['start' => 480, 'end' => 540]], 600),
        );

        $this->assertSame([[0, 120], [480, 540]], $ranges);
        $this->assertSame(180, VideoWatchCalculator::uniqueSeconds($ranges));
        $this->assertSame(30.0, VideoWatchCalculator::percent(180, 600));
        $this->assertFalse(VideoWatchCalculator::reachedCompletion(180, 600));
    }

    public function test_normalize_accepts_pair_arrays_and_drops_invalid_segments(): void
    {
        $ranges = VideoWatchCalculator::normalize([
            ['start' => 10, 'end' => 20],
            ['start' => 20, 'end' => 30],   // covered as pairs too
            [30, 40],
            ['start' => 50, 'end' => 50],   // empty
            ['start' => 60, 'end' => 40],   // inverted
            ['start' => -10, 'end' => 5],   // clamped below zero
            ['start' => 550, 'end' => 900], // clamped to duration
        ], 600);

        $this->assertSame([[0, 5], [10, 40], [550, 600]], $ranges);
    }

    public function test_normalize_rejects_everything_when_duration_is_unknown(): void
    {
        $this->assertSame([], VideoWatchCalculator::normalize([['start' => 0, 'end' => 10]], 0));
    }

    public function test_merge_unifies_overlapping_ranges(): void
    {
        $merged = VideoWatchCalculator::merge(
            [[0, 120], [480, 540]],
            [[100, 200], [530, 600]],
        );

        $this->assertSame([[0, 200], [480, 600]], $merged);
        $this->assertSame(320, VideoWatchCalculator::uniqueSeconds($merged));
    }

    public function test_merge_joins_adjacent_ranges(): void
    {
        $merged = VideoWatchCalculator::merge(
            [[0, 60]],
            [[60, 120]],
        );

        $this->assertSame([[0, 120]], $merged);
        $this->assertSame(120, VideoWatchCalculator::uniqueSeconds($merged));
    }

    public function test_replaying_an_already_watched_segment_never_double_counts(): void
    {
        $first = VideoWatchCalculator::merge([], [[0, 120]]);
        $second = VideoWatchCalculator::merge($first, [[30, 90]]);

        $this->assertSame([[0, 120]], $second);
        $this->assertSame(120, VideoWatchCalculator::uniqueSeconds($second));
    }

    public function test_a_smaller_later_report_never_regresses_the_union(): void
    {
        // The old GREATEST-on-playhead contract, restated on real ranges: a
        // late batch of already-covered seconds cannot shrink the union.
        $stored = VideoWatchCalculator::merge([], [[0, 600]]);
        $updated = VideoWatchCalculator::merge($stored, [[10, 20]]);

        $this->assertSame([[0, 600]], $updated);
        $this->assertTrue(VideoWatchCalculator::reachedCompletion(
            VideoWatchCalculator::uniqueSeconds($updated),
            600,
        ));
    }

    public function test_percent_and_completion_guard_against_an_unknown_duration(): void
    {
        $this->assertSame(0.0, VideoWatchCalculator::percent(120, 0));
        $this->assertFalse(VideoWatchCalculator::reachedCompletion(120, 0));
    }

    public function test_completion_threshold_is_inclusive_at_ninety_percent(): void
    {
        $this->assertTrue(VideoWatchCalculator::reachedCompletion(90, 100));
        $this->assertFalse(VideoWatchCalculator::reachedCompletion(89, 100));
    }
}
