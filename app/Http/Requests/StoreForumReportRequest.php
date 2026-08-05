<?php

namespace App\Http\Requests;

use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SPEC-10 §2.2/RF26 — payload for the "Denunciar" button
 * (`POST courses/{course}/forum/report`, `ForumReportModal.js`).
 * `postable_type` is the short string `forum_topic`/`forum_reply` the
 * view/JS use (the button's own `data-postable-type` attribute) —
 * `ForumReportController::store()` is the boundary that translates it
 * into the FQCN `ForumReport::postable_type` actually persists (see its
 * docblock). The raw FQCN (`ForumTopic::class`/`ForumReply::class`) is
 * also accepted so a caller that already has the model class-string
 * (e.g. a test hitting this endpoint directly, bypassing the view/JS)
 * doesn't need to know the short-string label. Any authenticated user
 * reachable by the `student.enrolled` route group may report, so
 * `authorize()` is a plain `true`.
 */
class StoreForumReportRequest extends FormRequest
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
            'postable_type' => ['required', 'string', Rule::in(['forum_topic', 'forum_reply', ForumTopic::class, ForumReply::class])],
            'postable_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
