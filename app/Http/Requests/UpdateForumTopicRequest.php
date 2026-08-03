<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-10 §2.1 — payload for editing a `ForumTopic`
 * (`PUT courses/{course}/forum/topics/{topic}`). See
 * {@see StoreForumTopicRequest} for why `authorize()` defers to
 * `Gate::authorize('update', $topic)` in `ForumTopicController::update()`
 * instead of resolving the route-bound topic here.
 */
class UpdateForumTopicRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
        ];
    }
}
