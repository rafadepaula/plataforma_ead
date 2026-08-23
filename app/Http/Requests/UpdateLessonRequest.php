<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidYoutubeUrlException;
use App\Services\YoutubeSanitizerService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates an update to a Lesson. Mirrors
 * {@see StoreLessonRequest}; authorization is scoped to the route-bound
 * `lesson` instance so `LessonPolicy::update()` can verify the parent
 * `Module -> Course` chain's `org_id`.
 */
class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('lesson'));
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
            'removed_media' => ['nullable', 'array'],
            'removed_media.*' => ['integer'],
            'youtube_url' => ['nullable', 'url'],
        ];
    }

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
