<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidVideoUrlException;
use App\Http\Requests\Concerns\ValidatesQuizQuestionPayload;
use App\Services\VideoUrlSanitizerManager;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates an update to a Lesson. Mirrors
 * {@see StoreLessonRequest}; authorization is scoped to the route-bound
 * `lesson` instance so `LessonPolicy::update()` can verify the parent
 * `Module -> Course` chain's `org_id`.
 *
 * Carries the same embedded quiz authoring payload as
 * {@see StoreLessonRequest}: a `quiz[...]` meta array plus the
 * `questions[...]` builder array, validated via
 * {@see ValidatesQuizQuestionPayload}. An absent `questions` key means
 * "do not touch the persisted questions" (quiz meta may still sync);
 * a present one triggers a full sync via `SaveQuizForLessonAction`.
 */
class UpdateLessonRequest extends FormRequest
{
    use ValidatesQuizQuestionPayload;

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
            'video_provider' => ['nullable', Rule::in(VideoUrlSanitizerManager::PROVIDERS)],
            'video_url' => ['nullable', 'url'],
            'is_published' => ['sometimes', 'boolean'],

            // Embedded quiz authoring payload (only meaningful when
            // `type = quiz`) — see the class docblock.
            'quiz' => ['nullable', 'array'],
            'quiz.instructions' => ['nullable', 'string'],
            'quiz.allow_retries' => ['boolean'],
            'quiz.max_attempts' => ['nullable', 'integer', 'min:1', 'max:255'],
            'quiz.time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'quiz.show_correct_answers' => ['boolean'],
            'quiz.min_score_percentage' => ['required_with:quiz', 'integer', 'min:0', 'max:100'],
            ...$this->quizQuestionRules(),
        ];
    }

    /**
     * Mirrors {@see StoreLessonRequest::withValidator()}: the sanitizer is
     * chosen by `video_provider` (or detected from the URL itself when the
     * select is empty), so a malformed/foreign link surfaces as a normal
     * validation failure on the `video_url` field rather than an uncaught
     * `InvalidVideoUrlException` bubbling out of the controller — and the
     * quiz builder's per-question correctness rules are applied.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateQuestionCorrectness($validator);

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
