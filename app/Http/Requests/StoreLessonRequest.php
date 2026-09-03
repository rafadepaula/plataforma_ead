<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidVideoUrlException;
use App\Models\Lesson;
use App\Services\VideoUrlSanitizerManager;
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
 * `type = content` fields (Rich Text / Imagem / PDF / Vídeo —
 * four supported content kinds), all optional/nullable so a Gestor can
 * fill in exactly one of them. The video kind is provider-agnostic:
 * `video_provider` (`youtube`|`vimeo`) selects the sanitizer that
 * `video_url` is validated against.
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
            'video_provider' => ['nullable', Rule::in(VideoUrlSanitizerManager::PROVIDERS)],
            'video_url' => ['nullable', 'url'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Re-validates a non-empty `video_url` through the sanitizer of its
     * provider (`video_provider`, or detected from the URL itself when the
     * select is empty), so a malformed/foreign link (including
     * XSS/embed-injection attempts) surfaces as a normal validation
     * failure on the `video_url` field rather than an uncaught
     * `InvalidVideoUrlException` bubbling out of the controller.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = $this->input('video_url');

            if (! $url) {
                return;
            }

            $manager = app(VideoUrlSanitizerManager::class);
            $provider = $this->input('video_provider') ?: $manager->providerFor($url);

            if ($provider === null) {
                $validator->errors()->add('video_url', 'Não foi possível identificar o provedor do vídeo — informe uma URL do YouTube ou do Vimeo.');

                return;
            }

            try {
                $manager->for($provider)->sanitize($url);
            } catch (InvalidVideoUrlException $e) {
                $validator->errors()->add('video_url', $e->getMessage());
            }
        });
    }
}
