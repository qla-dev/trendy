<?php

namespace App\Services\WorkOrder;

use InvalidArgumentException;

class WorkOrderClosingCalculator
{
    public const SCALE = 6;

    public function normalizeNonNegative(mixed $value, string $field = 'vrijednost'): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw new InvalidArgumentException($field . ' je obavezna.');
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException($field . ' mora biti nenegativan broj.');
        }

        return bcadd($normalized, '0', self::SCALE);
    }

    public function multiply(string $left, string $right): string
    {
        return bcmul($left, $right, self::SCALE);
    }

    public function add(string ...$values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, $value, self::SCALE);
        }

        return $total;
    }

    public function divide(string $value, string $quantity): string
    {
        if (bccomp($quantity, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Proizvedena količina mora biti veća od nule.');
        }

        return bcdiv($value, $quantity, self::SCALE);
    }

    public function operation(string $minutesPerUnit, string $pricePerMinute, string $producedQuantity): array
    {
        $consumedMinutes = $this->multiply($minutesPerUnit, $producedQuantity);
        $costPerUnit = $this->multiply($minutesPerUnit, $pricePerMinute);
        $totalCost = $this->multiply($consumedMinutes, $pricePerMinute);

        return compact('minutesPerUnit', 'consumedMinutes', 'pricePerMinute', 'costPerUnit', 'totalCost');
    }

    public function receipt(string $materialTotal, string $operationTotal, string $producedQuantity): array
    {
        $materialCostPerUnit = $this->divide($materialTotal, $producedQuantity);
        $operationCostPerUnit = $this->divide($operationTotal, $producedQuantity);
        $pricePerUnit = $this->add($materialCostPerUnit, $operationCostPerUnit);
        $totalPrice = $this->multiply($pricePerUnit, $producedQuantity);

        return compact(
            'materialTotal',
            'operationTotal',
            'materialCostPerUnit',
            'operationCostPerUnit',
            'pricePerUnit',
            'totalPrice'
        );
    }
}
