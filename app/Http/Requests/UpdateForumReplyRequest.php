<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * payload for editing a `ForumReply`
 * (`PUT courses/{course}/forum/topics/{topic}/replies/{reply}`). See
 * {@see StoreForumTopicRequest} for why `authorize()` defers to
 * `Gate::authorize('update', $reply)` in
 * `ForumReplyController::update()`.
 */
class UpdateForumReplyRequest extends FormRequest
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
