<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'min:1'],
            // A copied row represents another worker/time entry for the same
            // Pantheon operation, so item_qid is intentionally not distinct.
            'operations.*.item_qid' => ['required', 'integer', 'min:1'],
            // Empty worker/time rows are valid partial-close input. When every
            // row is complete, the closing service creates final documents.
            'operations.*.worker_id' => ['nullable', 'integer', 'min:1'],
            'operations.*.time' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:[.,]\d+)?$/'],
            'operations.*.start_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'operations.*.end_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'operations.required' => 'Sve operacije moraju biti popunjene.',
            'operations.*.worker_id.required' => 'Radnik je obavezan za svaku operaciju.',
            'operations.*.time.required' => 'Vrijeme je obavezno za svaku operaciju.',
            'operations.*.time.regex' => 'Vrijeme mora biti nenegativan broj.',
        ];
    }
}
