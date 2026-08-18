<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * payload for creating a `ForumTopic`
 * (`POST courses/{course}/forum`). Authorization is deferred to
 * `Gate::authorize('create', $course)` in `ForumTopicController::store()`
 * rather than checked here: the route already sits behind the
 * `student.enrolled` middleware, and `{course}` is resolved there
 * bypassing `OrgScope` (a multi-org Aluno carries no `org_id` of their
 * own, so a naive `$this->route('course')` binding would silently 404 —
 * mirrors `SubmitQuizAttemptRequest`'s "authorize() => true, real check
 * happens deeper" convention).
 */
class StoreForumTopicRequest extends FormRequest
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
