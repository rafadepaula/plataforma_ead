<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidVideoUrlException;
use App\Http\Requests\Concerns\ValidatesQuizQuestionPayload;
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
 *  owns quiz question authoring; this form exposes the `type = content`
 * fields (Rich Text / Imagem / PDF / Vídeo — four supported content
 * kinds), all optional/nullable so a Gestor can fill in exactly one of
 * them. The video kind is provider-agnostic: `video_provider`
 * (`youtube`|`vimeo`) selects the sanitizer that `video_url` is validated
 * against.
 *
 * When `type = quiz` the lesson form itself embeds the full quiz
 * authoring payload: a `quiz[...]` meta array (instructions, min score,
 * retries, time limit, gabarito) and the `questions[...]` builder array
 * (validated via {@see ValidatesQuizQuestionPayload}, persisted by
 * `SaveQuizForLessonAction`). The quiz `title` is NOT part of the
 * payload — it is kept synced to the Lesson `title` server-side (the
 * lesson form has a single title field).
 */
class StoreLessonRequest extends FormRequest
{
    use ValidatesQuizQuestionPayload;

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

            // Quiz authoring payload (only meaningful when `type = quiz`).
            // Absent `quiz`/`questions` keys simply mean "no quiz meta /
            // question sync in this request" — `LessonController` skips
            // the quiz persistence entirely.
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
     * Re-validates a non-empty `video_url` through the sanitizer of its
     * provider (`video_provider`, or detected from the URL itself when the
     * select is empty), so a malformed/foreign link (including
     * XSS/embed-injection attempts) surfaces as a normal validation
     * failure on the `video_url` field rather than an uncaught
     * `InvalidVideoUrlException` bubbling out of the controller — and
     * applies the quiz builder's per-question correctness rules.
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
