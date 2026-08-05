<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SPEC-10 §2/RF22 — payload for posting a `ForumReply`
 * (`POST courses/{course}/forum/topics/{topic}/replies`). See
 * {@see StoreForumTopicRequest} for why `authorize()` defers to
 * `Gate::authorize('create', $topic)` in
 * `ForumReplyController::store()`.
 */
class StoreForumReplyRequest extends FormRequest
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
            'content' => ['required', 'string'],
        ];
    }
}
