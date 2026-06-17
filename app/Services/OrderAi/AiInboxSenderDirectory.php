<?php

namespace App\Services\OrderAi;

use App\Models\AiInboxWhitelistEntry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiInboxSenderDirectory
{
    private ?array $entries = null;

    public function parseAddress(mixed $value): array
    {
        $raw = $this->decodeHeaderText($value);

        if ($raw === '') {
            return [
                'email' => '',
                'name' => '',
            ];
        }

        if (preg_match('/^(?:"?([^"<]+?)"?\s*)?<([^>]+)>$/', $raw, $matches) === 1) {
            return [
                'email' => $this->normalizeEmail($matches[2] ?? ''),
                'name' => $this->cleanText(trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B\"'")),
            ];
        }

        $email = $this->normalizeEmail($raw);

        if ($email !== '') {
            return [
                'email' => $email,
                'name' => '',
            ];
        }

        return [
            'email' => '',
            'name' => $this->cleanText($raw),
        ];
    }

    public function decodeHeaderText(mixed $value): string
    {
        $raw = $this->cleanText((string) $value);

        if ($raw === '' || preg_match('/=\?.+\?=/i', $raw) !== 1) {
            return $raw;
        }

        $decoded = $raw;

        if (function_exists('iconv_mime_decode')) {
            $mode = defined('ICONV_MIME_DECODE_CONTINUE_ON_ERROR')
                ? ICONV_MIME_DECODE_CONTINUE_ON_ERROR
                : 0;

            $candidate = iconv_mime_decode($raw, $mode, 'UTF-8');

            if (is_string($candidate) && trim($candidate) !== '') {
                $decoded = $candidate;
            }
        }

        if ($decoded === $raw && function_exists('mb_decode_mimeheader')) {
            $candidate = mb_decode_mimeheader($raw);

            if (is_string($candidate) && trim($candidate) !== '') {
                $decoded = $candidate;
            }
        }

        return $this->cleanText($decoded);
    }

    public function normalizeEmail(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : '';
    }

    public function isWhitelistEnabled(): bool
    {
        $configuredValue = config('ai-order-scan.inbox.enforce_sender_whitelist');

        if ($configuredValue === null || $configuredValue === '') {
            return $this->entries() !== [];
        }

        return filter_var($configuredValue, FILTER_VALIDATE_BOOL);
    }

    public function isAllowed(mixed $email): bool
    {
        if (!$this->isWhitelistEnabled()) {
            return true;
        }

        $normalizedEmail = $this->normalizeEmail($email);
        $entry = $normalizedEmail !== '' ? ($this->entries()[$normalizedEmail] ?? null) : null;

        if (!is_array($entry) || !array_key_exists($normalizedEmail, $this->entries())) {
            return false;
        }

        $isAllowed = (bool) ($entry['is_active'] ?? false);

        if ($isAllowed) {
            $this->touchLastMatchedAt($entry);
        }

        return $isAllowed;
    }

    public function resolveDisplayLabel(mixed $email, mixed $fallbackName = null): string
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $fallback = $this->decodeHeaderText($fallbackName);

        if ($fallback !== '') {
            return $fallback;
        }

        if ($normalizedEmail !== '' && isset($this->entries()[$normalizedEmail]['name']) && $this->entries()[$normalizedEmail]['name'] !== '') {
            return $this->decodeHeaderText($this->entries()[$normalizedEmail]['name']);
        }

        return $normalizedEmail !== '' ? $normalizedEmail : '-';
    }

    public function allEntries(): array
    {
        return $this->entries();
    }

    public function flushCache(): void
    {
        $this->entries = null;
    }

    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $databaseEntries = $this->databaseEntries();

        if ($databaseEntries !== []) {
            return $this->entries = $databaseEntries;
        }

        $configuredEntries = array_merge(
            $this->asArray(config('ai-order-scan.inbox.allowed_senders', [])),
            $this->asArray(config('ai-order-scan.inbox.fallback_allowed_senders', []))
        );
        $resolved = [];

        foreach ($configuredEntries as $entry) {
            $parsed = is_array($entry)
                ? [
                    'email' => $this->normalizeEmail($entry['email'] ?? ''),
                    'name' => $this->decodeHeaderText($entry['name'] ?? ''),
                ]
                : $this->parseAddress($entry);

            if ($parsed['email'] === '') {
                continue;
            }

            $resolved[$parsed['email']] = [
                'id' => null,
                'email' => $parsed['email'],
                'name' => $parsed['name'],
                'notes' => '',
                'is_active' => true,
                'source' => 'config',
                'last_matched_at' => null,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return $this->entries = $resolved;
    }

    private function databaseEntries(): array
    {
        try {
            $model = new AiInboxWhitelistEntry();
            $connection = $model->getConnectionName() ?: config('database.default');
            $table = $model->getTable();

            if (!Schema::connection($connection)->hasTable($table)) {
                return [];
            }

            return AiInboxWhitelistEntry::query()
                ->orderBy('email')
                ->get([
                    'id',
                    'name',
                    'email',
                    'notes',
                    'is_active',
                    'last_matched_at',
                    'created_at',
                    'updated_at',
                ])
                ->mapWithKeys(function (AiInboxWhitelistEntry $entry) {
                    $email = $this->normalizeEmail($entry->email);

                    if ($email === '') {
                        return [];
                    }

                    return [
                        $email => [
                            'id' => (int) $entry->id,
                            'email' => $email,
                            'name' => $this->decodeHeaderText($entry->name ?? ''),
                            'notes' => trim((string) ($entry->notes ?? '')),
                            'is_active' => (bool) $entry->is_active,
                            'source' => 'database',
                            'last_matched_at' => $entry->last_matched_at,
                            'created_at' => $entry->created_at,
                            'updated_at' => $entry->updated_at,
                        ],
                    ];
                })
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Unable to load AI inbox whitelist entries from database.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function touchLastMatchedAt(array $entry): void
    {
        $entryId = (int) ($entry['id'] ?? 0);
        $email = $this->normalizeEmail($entry['email'] ?? '');

        if ($entryId <= 0 || $email === '' || ($entry['source'] ?? '') !== 'database') {
            return;
        }

        try {
            $timestamp = now();

            AiInboxWhitelistEntry::query()
                ->whereKey($entryId)
                ->update(['last_matched_at' => $timestamp]);

            if ($this->entries !== null && isset($this->entries[$email])) {
                $this->entries[$email]['last_matched_at'] = $timestamp;
            }
        } catch (\Throwable $exception) {
            // Best-effort metadata update only.
        }
    }

    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function cleanText(mixed $value): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', trim((string) $value));
        $normalized = preg_replace('/\s+/u', ' ', $text);

        return is_string($normalized) ? trim($normalized) : trim($text);
    }
}
