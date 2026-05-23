<?php

namespace App\Support;

use Illuminate\Support\Str;
use Throwable;

class Utf8Sanitizer
{
    public static function clean(mixed $value, int $maxLength = 0): string
    {
        if ($value === null) {
            return '';
        }

        $string = str_replace("\0", '', (string) $value);

        if ($string === '') {
            return '';
        }

        if (!self::isUtf8($string)) {
            $utf8Ignored = @iconv('UTF-8', 'UTF-8//IGNORE', $string);

            if (is_string($utf8Ignored) && $utf8Ignored !== '') {
                $string = $utf8Ignored;
            } else {
                $converted = @mb_convert_encoding($string, 'UTF-8', 'Windows-1252, ISO-8859-1');

                if (is_string($converted) && $converted !== '') {
                    $string = $converted;
                }
            }
        }

        $finalCleanup = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        if (is_string($finalCleanup)) {
            $string = $finalCleanup;
        }

        $string = preg_replace('/[^\P{C}\t\r\n]/u', '', $string) ?? $string;

        if ($maxLength > 0) {
            $string = mb_substr($string, 0, $maxLength, 'UTF-8');
        }

        return $string;
    }

    public static function cleanRecursive(mixed $value, int $maxStringLength = 0): mixed
    {
        if (is_string($value)) {
            return self::clean($value, $maxStringLength);
        }

        if (!is_array($value)) {
            return $value;
        }

        $cleaned = [];

        foreach ($value as $key => $item) {
            $cleaned[$key] = self::cleanRecursive($item, $maxStringLength);
        }

        return $cleaned;
    }

    public static function cleanExceptionMessage(Throwable|string $value, int $maxLength = 600): string
    {
        $message = $value instanceof Throwable ? $value->getMessage() : (string) $value;
        $message = trim(self::clean($message));

        if ($message === '') {
            return '';
        }

        $message = preg_replace('/\s+\(SQL:.*$/s', '', $message) ?? $message;

        if (preg_match('/\[SQL Server\](.+)$/s', $message, $matches) === 1) {
            $message = trim((string) ($matches[1] ?? ''));
        }

        $message = preg_replace('/^SQLSTATE\[[^\]]+\]:\s*/', '', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;
        $message = trim($message);

        return $maxLength > 0 ? Str::limit($message, $maxLength) : $message;
    }

    private static function isUtf8(string $value): bool
    {
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return preg_match('//u', $value) === 1;
    }
}
