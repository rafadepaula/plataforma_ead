<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * validates the `settings.update` submission (SMTP + logo +
 * certificate signature org-override). `smtp_password` is optional: a
 * blank value means "keep the currently stored password" (see
 * `SystemSettingController::update()`), never overwritten with an empty
 * string. Field names must stay in sync with
 * `resources/views/settings/edit.blade.php` (see `dashboard-conventions`).
 */
class UpdateSystemSettingRequest extends FormRequest
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
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
