<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF06 — validates an update to a Course. Mirrors
 * {@see StoreCourseRequest}; authorization is scoped to the route-bound
 * `course` instance (so `CoursePolicy::update()` can enforce the acting
 * Gestor's tenant boundary).
 */
class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('course'));
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
