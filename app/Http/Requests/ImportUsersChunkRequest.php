<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RF05/RN09 — validates one AJAX CSV-import chunk. The whole file is read
 * and split into batches of up to 50 rows client-side by
 * `CsvImporter.js` (see the `auth-orgs-maintenance` skill for why this is
 * client-driven rather than a server-side streamed upload); this request
 * never receives the raw file, only its already-parsed `rows`. `filename`
 * is optional metadata carried on the first chunk only, so the extension
 * can still be sanity-checked without ever transmitting file bytes to the
 * server.
 */
class ImportUsersChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer'],
            'filename' => ['nullable', 'string', 'regex:/\.(csv|txt)$/i'],
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.email' => ['nullable', 'string', 'max:255'],
            'rows.*.cpf' => ['nullable', 'string', 'max:14'],
        ];
    }
}
