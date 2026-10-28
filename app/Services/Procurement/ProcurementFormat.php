<?php

namespace App\Services\Procurement;

class ProcurementFormat
{
    public static function number(mixed $value, int $precision = 2, bool $trim = false): string
    {
        $value = (string) $value;
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) return '—';
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');
        $rounder = $precision === 0 ? '0.5' : '0.' . str_repeat('0', $precision) . '5';
        $rounded = bcadd($absolute, $rounder, $precision);
        [$whole, $fraction] = array_pad(explode('.', $rounded), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        if ($trim) $fraction = rtrim($fraction, '0');
        return ($negative && bccomp($absolute, '0', 6) !== 0 ? '-' : '') . $whole . ($fraction !== '' ? ',' . $fraction : '');
    }

    public static function quantity(mixed $value, string $unit): string
    {
        return self::number($value, (int) config('procurement.quantity_precision.' . strtoupper($unit), config('procurement.quantity_precision.default', 3)), true);
    }

    public static function event(string $action): string
    {
        return ['created'=>'Kreirana nabavka','modified'=>'Izmijenjena nabavka','received'=>'Evidentiran prijem','cancelled'=>'Otkazana nabavka',
            'sync_blocked'=>'ERP prijenos blokiran','sync_error'=>'Greška ERP prijenosa','sync_synced'=>'ERP potvrdio prijenos',
            'document_uploaded'=>'Dodan dokument','supplier_delay'=>'Dobavljač najavio kašnjenje','supplier_confirm'=>'Dobavljač potvrdio narudžbu',
            'complaint_created'=>'Kreirana reklamacija','complaint_sent'=>'Reklamacija poslana dobavljaču','complaint_processing'=>'Reklamacija u obradi',
            'complaint_resolved'=>'Reklamacija riješena','complaint_rejected'=>'Reklamacija odbijena','notices_read'=>'Pregledane obavijesti'][$action] ?? 'Promjena evidencije';
    }
}
