<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CloseWorkOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $operations = $this->input('operations');
        $materials = $this->input('materials');
        $receipts = $this->input('receipts');

        if (is_array($operations)) {
            $operations = array_values(array_filter(array_map(function ($operation) {
                $operation = is_array($operation) ? $operation : [];
                $operation['code'] = trim((string) ($operation['code'] ?? ''));
                $itemQid = (int) ($operation['item_qid'] ?? 0);
                $operation['item_qid'] = $itemQid > 0 ? $itemQid : null;

                return $operation;
            }, $operations), function (array $operation): bool {
                // A selected operation code by itself is only a placeholder
                // row in the modal. It cannot create a document entry, so do
                // not let it turn an otherwise complete close into a partial
                // close. Keep rows that contain any actual work input; the
                // closing service then validates the required combination.
                foreach (['worker_id', 'time', 'start_time', 'end_time'] as $field) {
                    if (trim((string) ($operation[$field] ?? '')) !== '') {
                        return true;
                    }
                }

                return false;
            }));
        }

        if (is_array($materials)) {
            $materials = array_values(array_filter(array_map(function ($material) {
                $material = is_array($material) ? $material : [];
                $material['code'] = trim((string) ($material['code'] ?? ''));
                // Newly added rows in the closing modal do not have a
                // Pantheon WO-item QId yet. Treat the browser's 0 value as
                // null so the closing service can create and link it.
                $itemQid = (int) ($material['item_qid'] ?? 0);
                $material['item_qid'] = $itemQid > 0 ? $itemQid : null;

                return $material;
            }, $materials), function (array $material): bool {
                return trim((string) ($material['code'] ?? '')) !== ''
                    || trim((string) ($material['quantity'] ?? '')) !== '';
            }));
        }

        if (is_array($receipts)) {
            $receipts = array_values(array_filter(array_map(function ($receipt) {
                $receipt = is_array($receipt) ? $receipt : [];
                $receipt['target'] = trim((string) ($receipt['target'] ?? ''));
                $receipt['quantity'] = trim((string) ($receipt['quantity'] ?? ''));
                return $receipt;
            }, $receipts), fn (array $receipt): bool => $receipt['target'] !== '' || $receipt['quantity'] !== ''));
        }

        $this->merge([
            'operations' => $operations,
            'materials' => $materials,
            'receipts' => $receipts,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'operations' => ['nullable', 'array'],
            // A copied row represents another worker/time entry for the same
            // Pantheon operation, so item_qid is intentionally not distinct.
            // Manual rows are document-only and deliberately have no WO item QId.
            'operations.*.item_qid' => ['nullable', 'integer', 'min:1'],
            'operations.*.code' => ['nullable', 'string', 'max:64'],
            // Empty worker/time rows are valid partial-close input. When every
            // row is complete, the closing service creates final documents.
            'operations.*.worker_id' => ['nullable', 'integer', 'min:1'],
            'operations.*.time' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:[.,]\d+)?$/'],
            'operations.*.start_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'operations.*.end_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'materials' => ['nullable', 'array'],
            'materials.*.item_qid' => ['nullable', 'integer', 'min:1'],
            'materials.*.code' => ['nullable', 'string', 'max:64'],
            'materials.*.quantity' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:[.,]\d+)?$/'],
            'materials.*.is_new' => ['nullable', 'boolean'],
            'receipts' => ['nullable', 'array'],
            'receipts.*.target' => ['required', 'in:vp,scrap'],
            'receipts.*.quantity' => ['required', 'regex:/^(?:0|[1-9]\d*)(?:[.,]\d+)?$/'],
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

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Work order closing validation failed.', [
            'work_order' => (string) $this->route('id', ''),
            'user_id' => (int) ($this->user()?->id ?? 0),
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['_token']),
        ]);

        throw new ValidationException($validator);
    }
}
