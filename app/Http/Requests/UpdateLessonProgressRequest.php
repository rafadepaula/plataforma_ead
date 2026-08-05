<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-07 RF15/§1.1 — payload for the AJAX video-progress polling endpoint
 * (`POST /lessons/{lesson}/progress`), sent every 5s by `LessonPlayer.js`
 * while a video lesson plays. `duration_seconds` comes from the YouTube
 * IFrame API's `getDuration()` and is only used to compute the 90%
 * threshold — it is never persisted.
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
            'watched_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
        ];
    }
}
