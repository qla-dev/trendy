<?php

namespace App\Services\Procurement;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ProcurementCalculator
{
    public function decimal(mixed $value, bool $positive = false): string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', $value)) {
            throw new InvalidArgumentException('Unesite nenegativan broj s najviše 6 decimala i 12 cijelih cifara.');
        }
        if ($positive && bccomp($value, '0', 6) <= 0) {
            throw new InvalidArgumentException('Količina mora biti veća od nule.');
        }
        return bcadd($value, '0', 6);
    }

    private function money(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    public function line(mixed $quantity, mixed $price, mixed $vatRate, mixed $discountRate = '0'): array
    {
        $quantity = $this->decimal($quantity, true);
        $price = $this->decimal($price);
        $vatRate = $this->decimal($vatRate);
        $discountRate = $this->decimal($discountRate);
        if (bccomp($discountRate, '100', 6) > 0) throw new InvalidArgumentException('Rabat mora biti između 0 i 100%.');
        if (bccomp($vatRate, '100', 6) > 0) {
            throw new InvalidArgumentException('PDV mora biti između 0 i 100%.');
        }
        $base = $this->money(bcmul($quantity, $price, 12));
        $discount = $this->money(bcdiv(bcmul($base, $discountRate, 8), '100', 8));
        $net = bcsub($base, $discount, 2);
        $vat = $this->money(bcdiv(bcmul($net, $vatRate, 8), '100', 8));
        return compact('quantity', 'price', 'vatRate', 'net', 'vat', 'base', 'discountRate', 'discount') + ['gross' => bcadd($net, $vat, 2)];
    }

    public function totals(array $items): array
    {
        $total = ['quantity' => '0.000000', 'net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];
        foreach ($items as $item) {
            foreach ($total as $key => $value) {
                $total[$key] = bcadd($value, $item[$key], $key === 'quantity' ? 6 : 2);
            }
        }
        if (count(array_unique(array_map(fn($item)=>$item['unit'] ?? '',$items)))>1) $total['quantity'] = null;
        return $total;
    }

    public function deliveryStatus(array $purchase): string
    {
        if (!empty($purchase['cancelled_at'])) return 'cancelled';
        $received = '0';
        $remaining = '0';
        foreach ($purchase['items'] as $item) {
            $received = bcadd($received, $item['received'] ?? '0', 6);
            $lineRemaining = bcsub($item['quantity'], $item['received'] ?? '0', 6);
            if (bccomp($lineRemaining,'0',6)>0) $remaining = bcadd($remaining,$lineRemaining,6);
        }
        if (bccomp($remaining, '0', 6) <= 0) return 'delivered';
        return bccomp($received, '0', 6) > 0 ? 'partial' : 'open';
    }

    public function deadline(array $purchase, ?CarbonImmutable $today = null): array
    {
        $status = $this->deliveryStatus($purchase);
        if (in_array($status, ['delivered', 'cancelled'], true)) {
            return ['days' => 0, 'label' => $status === 'delivered' ? 'Isporučeno' : 'Otkazano', 'class' => 'secondary'];
        }
        $timezone = config('app.timezone', 'Europe/Sarajevo');
        $today = ($today ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $deadlines = [];
        foreach ($purchase['items'] as $item) {
            if (bccomp($item['quantity'], $item['received'] ?? '0', 6) > 0) $deadlines[] = $item['deadline'] ?? $purchase['deadline'];
        }
        sort($deadlines);
        $due = CarbonImmutable::parse($deadlines[0] ?? $purchase['deadline'], $timezone)->startOfDay();
        $days = $due->lt($today) ? (int) $due->diffInDays($today) : 0;
        return ['days' => $days, 'label' => $days > 0 ? ($days === 1 ? 'Kasni 1 dan' : "Kasni {$days} dana") : ($due->eq($today) ? 'Rok danas' : 'Na vrijeme'),
            'class' => $days > 0 ? 'danger' : ($due->eq($today) ? 'warning' : 'success')];
    }
}
