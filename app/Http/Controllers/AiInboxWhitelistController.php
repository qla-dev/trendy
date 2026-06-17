<?php

namespace App\Http\Controllers;

use App\Models\AiInboxWhitelistEntry;
use App\Models\OrderAiScan;
use App\Services\OrderAi\AiInboxSenderDirectory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AiInboxWhitelistController extends Controller
{
    public function index(Request $request, AiInboxSenderDirectory $senderDirectory)
    {
        $this->authorizeModuleAccess($request);
        $this->bootstrapConfiguredEntries($senderDirectory);

        $pageConfigs = ['pageHeader' => false];
        $payload = $this->buildWhitelistPayload($senderDirectory);

        return view('content.apps.ai.app-ai-whitelist', [
            'pageConfigs' => $pageConfigs,
            'whitelistEntries' => collect($payload['entries']),
            'whitelistStats' => $payload['stats'],
        ]);
    }

    public function store(Request $request, AiInboxSenderDirectory $senderDirectory): RedirectResponse|JsonResponse
    {
        $this->authorizeModuleAccess($request);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique($this->whitelistTableForValidation(), 'email'),
            ],
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            if ($this->expectsJsonResponse($request)) {
                return $this->validationErrorJsonResponse($validator);
            }

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput()
                ->with('openWhitelistModal', true);
        }

        AiInboxWhitelistEntry::create([
            'name' => $this->normalizeOptionalText($request->input('name')),
            'email' => $this->normalizeEmail((string) $request->input('email')),
            'notes' => $this->normalizeOptionalText($request->input('notes')),
            'is_active' => $request->boolean('is_active'),
            'created_by_user_id' => (int) ($request->user()->id ?? 0) ?: null,
            'updated_by_user_id' => (int) ($request->user()->id ?? 0) ?: null,
        ]);

        $senderDirectory->flushCache();

        if ($this->expectsJsonResponse($request)) {
            return $this->successJsonResponse($senderDirectory, 'Pošiljalac je uspješno dodat.');
        }

        return redirect()
            ->route('app-ai-whitelist')
            ->with('success', 'Pošiljalac je uspješno dodat.');
    }

    public function update(
        Request $request,
        AiInboxWhitelistEntry $entry,
        AiInboxSenderDirectory $senderDirectory
    ): RedirectResponse|JsonResponse {
        $this->authorizeModuleAccess($request);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique($this->whitelistTableForValidation(), 'email')->ignore($entry->id),
            ],
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            if ($this->expectsJsonResponse($request)) {
                return $this->validationErrorJsonResponse($validator);
            }

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput()
                ->with('openWhitelistEditModal', true)
                ->with('openWhitelistEditModalId', (int) $entry->id);
        }

        $entry->update([
            'name' => $this->normalizeOptionalText($request->input('name')),
            'email' => $this->normalizeEmail((string) $request->input('email')),
            'notes' => $this->normalizeOptionalText($request->input('notes')),
            'is_active' => $request->boolean('is_active'),
            'updated_by_user_id' => (int) ($request->user()->id ?? 0) ?: null,
        ]);

        $senderDirectory->flushCache();

        if ($this->expectsJsonResponse($request)) {
            return $this->successJsonResponse($senderDirectory, 'Pošiljalac je uspješno ažuriran.');
        }

        return redirect()
            ->route('app-ai-whitelist')
            ->with('success', 'Pošiljalac je uspješno ažuriran.');
    }

    public function toggle(
        Request $request,
        AiInboxWhitelistEntry $entry,
        AiInboxSenderDirectory $senderDirectory
    ): RedirectResponse|JsonResponse {
        $this->authorizeModuleAccess($request);

        $entry->update([
            'is_active' => !$entry->is_active,
            'updated_by_user_id' => (int) ($request->user()->id ?? 0) ?: null,
        ]);

        $senderDirectory->flushCache();
        $message = $entry->is_active
            ? 'Pošiljalac je aktiviran.'
            : 'Pošiljalac je deaktiviran.';

        if ($this->expectsJsonResponse($request)) {
            return $this->successJsonResponse($senderDirectory, $message);
        }

        return redirect()
            ->route('app-ai-whitelist')
            ->with('success', $message);
    }

    public function destroy(
        Request $request,
        AiInboxWhitelistEntry $entry,
        AiInboxSenderDirectory $senderDirectory
    ): RedirectResponse|JsonResponse {
        $this->authorizeModuleAccess($request);

        $entry->delete();
        $senderDirectory->flushCache();

        if ($this->expectsJsonResponse($request)) {
            return $this->successJsonResponse($senderDirectory, 'Pošiljalac je obrisan.');
        }

        return redirect()
            ->route('app-ai-whitelist')
            ->with('success', 'Pošiljalac je obrisan.');
    }

    private function authorizeModuleAccess(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $canAccess = method_exists($user, 'canAccessAiOrderModule')
            ? (bool) $user->canAccessAiOrderModule()
            : false;

        if (!$canAccess) {
            abort(403);
        }
    }

    private function whitelistTableForValidation(): string
    {
        $model = new AiInboxWhitelistEntry();
        $connection = $model->getConnectionName();
        $table = $model->getTable();

        if (is_string($connection) && $connection !== '') {
            return $connection . '.' . $table;
        }

        return $table;
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('d.m.Y H:i');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('d.m.Y H:i');
            } catch (\Throwable $exception) {
                return trim($value);
            }
        }

        return '-';
    }

    private function bootstrapConfiguredEntries(AiInboxSenderDirectory $senderDirectory): void
    {
        try {
            if (Cache::get('ai_inbox_whitelist_bootstrapped', false)) {
                return;
            }

            if (AiInboxWhitelistEntry::query()->exists()) {
                Cache::forever('ai_inbox_whitelist_bootstrapped', true);
                return;
            }

            $configuredEntries = collect($senderDirectory->allEntries())
                ->filter(function (array $entry) {
                    return ($entry['source'] ?? '') === 'config';
                })
                ->values();

            if ($configuredEntries->isEmpty()) {
                return;
            }

            foreach ($configuredEntries as $entry) {
                $email = $this->normalizeEmail((string) ($entry['email'] ?? ''));

                if ($email === '') {
                    continue;
                }

                AiInboxWhitelistEntry::query()->create([
                    'name' => $this->normalizeOptionalText($entry['name'] ?? null),
                    'email' => $email,
                    'notes' => 'Uvezeno iz konfiguracije.',
                    'is_active' => true,
                ]);
            }

            Cache::forever('ai_inbox_whitelist_bootstrapped', true);
            $senderDirectory->flushCache();
        } catch (\Throwable $exception) {
            // If the table is unavailable or bootstrap fails, keep the config fallback working.
        }
    }

    private function resolveLastReceivedMailMap(array $entries, AiInboxSenderDirectory $senderDirectory): array
    {
        $emails = collect($entries)
            ->map(function (array $entry) use ($senderDirectory) {
                return $senderDirectory->normalizeEmail($entry['email'] ?? '');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return [];
        }

        $rows = OrderAiScan::query()
            ->where('source_origin', 'imap')
            ->whereNotNull('source_email_from')
            ->where(function ($query) use ($emails) {
                foreach ($emails as $email) {
                    $query->orWhere(function ($emailQuery) use ($email) {
                        $emailQuery
                            ->where('source_email_from', $email)
                            ->orWhere('source_email_from', 'like', '%' . $email . '%');
                    });
                }
            })
            ->orderByDesc('source_email_received_at')
            ->orderByDesc('created_at')
            ->get([
                'source_email_from',
                'source_email_received_at',
                'created_at',
            ]);

        $resolved = [];

        foreach ($rows as $row) {
            $sender = $senderDirectory->parseAddress((string) ($row->source_email_from ?? ''));
            $email = $senderDirectory->normalizeEmail($sender['email'] ?? '');

            if ($email === '' || array_key_exists($email, $resolved)) {
                continue;
            }

            $resolved[$email] = $this->formatDateTime($row->source_email_received_at ?? $row->created_at);
        }

        return $resolved;
    }

    private function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function buildWhitelistPayload(AiInboxSenderDirectory $senderDirectory): array
    {
        $directoryEntries = $senderDirectory->allEntries();
        $lastReceivedMailMap = $this->resolveLastReceivedMailMap($directoryEntries, $senderDirectory);
        $entries = collect($directoryEntries)
            ->map(function (array $entry) use ($lastReceivedMailMap) {
                return $this->transformWhitelistEntry($entry, $lastReceivedMailMap);
            })
            ->sortBy(function (array $entry) {
                return sprintf(
                    '%s|%s|%s|%s',
                    $entry['is_active'] ? '0' : '1',
                    $entry['source'] === 'database' ? '0' : '1',
                    strtolower($entry['name']),
                    strtolower($entry['email'])
                );
            })
            ->values();

        return [
            'entries' => $entries->all(),
            'stats' => [
                'total' => $entries->count(),
                'active' => $entries->where('is_active', true)->count(),
            ],
        ];
    }

    private function transformWhitelistEntry(array $entry, array $lastReceivedMailMap): array
    {
        $source = (string) ($entry['source'] ?? 'database');
        $isActive = (bool) ($entry['is_active'] ?? false);
        $notes = trim((string) ($entry['notes'] ?? ''));
        $email = trim((string) ($entry['email'] ?? ''));

        return [
            'id' => isset($entry['id']) ? (int) $entry['id'] : null,
            'name' => trim((string) ($entry['name'] ?? '')) !== '' ? trim((string) $entry['name']) : '-',
            'name_raw' => trim((string) ($entry['name'] ?? '')),
            'email' => $email ?: '-',
            'source' => $source,
            'is_active' => $isActive,
            'status_label' => $isActive ? 'Aktivan' : 'Neaktivan',
            'status_tone' => $isActive ? 'success' : 'secondary',
            'last_received_mail_display' => $lastReceivedMailMap[$email] ?? '-',
            'notes_display' => $notes !== ''
                ? $notes
                : ($source === 'config' ? 'Preuzeto iz konfiguracije / env fallback-a.' : '-'),
            'notes_raw' => $notes,
            'created_at_display' => $this->formatDateTime($entry['created_at'] ?? null),
            'is_read_only' => $source === 'config',
        ];
    }

    private function expectsJsonResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax() || $request->wantsJson();
    }

    private function successJsonResponse(AiInboxSenderDirectory $senderDirectory, string $message): JsonResponse
    {
        return response()->json(array_merge(
            ['message' => $message],
            $this->buildWhitelistPayload($senderDirectory)
        ));
    }

    private function validationErrorJsonResponse(ValidatorContract $validator): JsonResponse
    {
        return response()->json([
            'message' => 'Provjerite unesene podatke i pokušajte ponovo.',
            'errors' => $validator->errors(),
        ], 422);
    }
}
