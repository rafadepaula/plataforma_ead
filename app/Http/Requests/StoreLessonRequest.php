<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidYoutubeUrlException;
use App\Models\Lesson;
use App\Services\YoutubeSanitizerService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates creation of a Lesson. `module_id` is intentionally
 * absent from these rules: it is always resolved from the route-bound
 * `{module}` segment by `LessonController::store()`, never trusted from
 * request input.
 *
 *  owns quiz question authoring; this form only exposes
 * `type = content` fields (Rich Text / Imagem / PDF / YouTube —
 * four supported content kinds), all optional/nullable so a Gestor can
 * fill in exactly one of them.
 */
class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', [Lesson::class, $this->route('module')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['content', 'quiz'])],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'content_text' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:2048'],
            'pdfs' => ['nullable', 'array'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:10240'],
            'youtube_url' => ['nullable', 'url'],
        ];
    }

    /**
     * Re-validates a non-empty `youtube_url` through
     * {@see YoutubeSanitizerService}, so a malformed/non-YouTube link
     * (including XSS/embed-injection attempts) surfaces as a normal
     * validation failure on the `youtube_url` field rather than an
     * uncaught `InvalidYoutubeUrlException` bubbling out of the
     * controller.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = $this->input('youtube_url');

            if (! $url) {
                return;
            }

            try {
                app(YoutubeSanitizerService::class)->sanitize($url);
            } catch (InvalidYoutubeUrlException $e) {
                $validator->errors()->add('youtube_url', $e->getMessage());
            }
        });
    }
}
