<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for the AJAX video-progress endpoint
 * (`POST /lessons/{lesson}/progress`), sent every 5s by the lesson player
 * with the batch of second-ranges replayed since the previous POST plus the
 * provider-reported `duration_seconds`.
 *
 * The server never trusts the percentage the client might derive: only raw
 * `segments` travel here, and `VideoWatchCalculator` (via
 * `LessonProgress::applyWatchedSegments()`) clamps them to
 * `[0, duration_seconds]` and unions them into the stored ranges. The per-POST
 * volume limit bounds abuse without constraining legitimate batches.
 */
class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration_seconds' => ['required', 'integer', 'min:1'],
            // `present`, never `required`: a position-only batch (the player
            // seeked/paused with no new seconds watched) travels with an
            // EMPTY array, which `required` would reject — silently dropping
            // the resume bookmark.
            'segments' => ['present', 'array', 'max:120'],
            'segments.*.start' => ['required', 'integer', 'min:0'],
            'segments.*.end' => ['required', 'integer', 'min:0'],
            // Current playhead — the "resume from where you left off"
            // bookmark. Optional so older clients stay valid; the server
            // keeps the LATEST reported position (a backward seek must be
            // able to move the bookmark back).
            'position_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
