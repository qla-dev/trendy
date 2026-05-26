<?php

namespace App\Services\OrderAi;

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

        return array_key_exists($this->normalizeEmail($email), $this->entries());
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

    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $configuredEntries = config('ai-order-scan.inbox.allowed_senders', []);
        $resolved = [];

        foreach (is_array($configuredEntries) ? $configuredEntries : [] as $entry) {
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
                'email' => $parsed['email'],
                'name' => $parsed['name'],
            ];
        }

        return $this->entries = $resolved;
    }

    private function cleanText(mixed $value): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', trim((string) $value));
        $normalized = preg_replace('/\s+/u', ' ', $text);

        return is_string($normalized) ? trim($normalized) : trim($text);
    }
}
