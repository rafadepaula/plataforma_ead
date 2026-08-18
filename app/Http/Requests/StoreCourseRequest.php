<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

/**
 *  validates creation of a Course. `org_id` is intentionally absent
 * from these rules: it is always resolved server-side by the `OrgScope`
 * trait's `creating` hook, never trusted from request input.
 */
class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Course::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'workload_hours' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['boolean'],
        ];
    }
}
