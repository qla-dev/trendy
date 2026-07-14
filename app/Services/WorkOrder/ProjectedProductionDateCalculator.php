<?php

namespace App\Services\WorkOrder;

use DateTimeImmutable;
use DateTimeInterface;

final class ProjectedProductionDateCalculator
{
    private const FOUR_WEEK_PROTECTION_NAMES = [
        'plazma+lakiranje',
        'plazmanitriranje',
    ];

    public function calculate(
        mixed $deliveryDate,
        ?string $protectionCode = null,
        ?int $protectionId = null
    ): ?string {
        $date = $this->dateOnly($deliveryDate);

        if ($date === null) {
            return null;
        }

        $days = $this->weeksForProtection($protectionCode, $protectionId) * 7;

        return $date->modify('-' . $days . ' days')->format('Y-m-d');
    }

    public function weeksForProtection(?string $protectionCode = null, ?int $protectionId = null): int
    {
        // acCostDrv is the stable Pantheon catalogue code; anQId is retained
        // for transport/validation but the business rule does not depend on
        // database-specific numeric IDs.
        if (in_array($this->normalizeProtectionName($protectionCode), self::FOUR_WEEK_PROTECTION_NAMES, true)) {
            return 4;
        }

        return trim((string) $protectionCode) === '' && $protectionId === null ? 2 : 3;
    }

    public function dateOnly(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromFormat('!Y-m-d', $value->format('Y-m-d')) ?: null;
        }

        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) === 1) {
            return DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]) ?: null;
        }

        foreach (['!d.m.Y', '!d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }

    public function formatDisplay(mixed $value): string
    {
        return ($date = $this->dateOnly($value)) !== null
            ? $date->format('d/m/Y')
            : '';
    }

    private function normalizeProtectionName(?string $value): string
    {
        $normalized = trim((string) ($value ?? ''));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
    }
}
