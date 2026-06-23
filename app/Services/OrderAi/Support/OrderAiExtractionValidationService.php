<?php

namespace App\Services\OrderAi\Support;

use App\Support\Utf8Sanitizer;

class OrderAiExtractionValidationService
{
    public function validate(array $payload, array $preparedDocument, string $profileKey = ''): array
    {
        $searchableText = trim((string) ($preparedDocument['searchable_text'] ?? ''));
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $lineTotalSum = round(array_reduce($items, function (float $carry, $item) {
            if (!is_array($item)) {
                return $carry;
            }

            return $carry + max(0, (float) ($item['line_total'] ?? 0));
        }, 0.0), 4);
        $documentFields = $this->detectDocumentFields($searchableText);
        $documentNetTotal = $documentFields['total_net_amount'] ?? null;
        $documentGrossTotal = $documentFields['total_gross_amount'] ?? null;
        $warnings = [];
        $errors = [];

        if ($documentNetTotal !== null && abs($lineTotalSum - $documentNetTotal) >= 0.01) {
            $errors[] = sprintf(
                'Zbir stavki (%.2f) se ne poklapa sa dokumentnim neto iznosom (%.2f).',
                $lineTotalSum,
                $documentNetTotal
            );
        }

        if ($documentNetTotal === null) {
            $summarySubtotal = (float) ($summary['subtotal'] ?? 0);

            if ($summarySubtotal > 0 && abs($lineTotalSum - $summarySubtotal) >= 0.01) {
                $warnings[] = sprintf(
                    'Zbir stavki (%.2f) se razlikuje od summary.subtotal (%.2f).',
                    $lineTotalSum,
                    $summarySubtotal
                );
            }
        }

        if (($preparedDocument['page_processing_limit_reason'] ?? '') !== '') {
            $warnings[] = trim((string) $preparedDocument['page_processing_limit_reason']);
        }

        if (($preparedDocument['extraction_method'] ?? '') === 'hybrid') {
            $warnings[] = 'Dokument je klasifikovan kao hybrid PDF i zahtijeva rucnu provjeru.';
        }

        if (($preparedDocument['extraction_method'] ?? '') === 'ocr') {
            $warnings[] = 'Dokument nema pouzdan embedded tekst i obradjen je OCR/AI putem.';
        }

        $baseConfidence = max(
            0.0,
            (float) ($order['confidence'] ?? 0),
            (float) ($preparedDocument['extraction_confidence'] ?? 0)
        );
        $confidence = $baseConfidence;
        $confidence -= min(0.45, count($errors) * 0.2);
        $confidence -= min(0.25, count($warnings) * 0.05);
        $confidence = max(0.0, min(1.0, $confidence));
        $requiresManualReview = $errors !== [] || $confidence < 0.75;

        if ($requiresManualReview) {
            $warnings[] = 'Rezultat treba rucnu provjeru prije prenosa u Trendy.';
        }

        return [
            'warnings' => array_values(array_unique(array_map(
                fn ($warning) => Utf8Sanitizer::clean(trim((string) $warning)),
                array_filter($warnings)
            ))),
            'errors' => array_values(array_unique(array_map(
                fn ($error) => Utf8Sanitizer::clean(trim((string) $error)),
                array_filter($errors)
            ))),
            'confidence_score' => round($confidence, 4),
            'requires_manual_review' => $requiresManualReview,
            'line_item_count' => count($items),
            'line_total_sum' => $lineTotalSum,
            'document_totals' => [
                'net_total' => $documentNetTotal,
                'gross_total' => $documentGrossTotal,
            ],
            'detected_fields' => $documentFields,
            'profile_key' => trim($profileKey),
        ];
    }

    private function detectDocumentFields(string $searchableText): array
    {
        return [
            'supplier_code' => $this->matchFieldValue($searchableText, [
                '/\bLieferant\s*:?\s*(\d{4,})/iu',
                '/\bSupplier\s+Code\s*:?\s*([A-Z0-9\-\/]+)/iu',
            ]),
            'document_number' => $this->matchFieldValue($searchableText, [
                '/\bBestell-Nr\.?\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
                '/\bBestellung\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
                '/\bOrder\s+(?:No\.?|Number)\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
                '/\bDocument\s+(?:No\.?|Number)\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
            ]),
            'invoice_number' => $this->matchFieldValue($searchableText, [
                '/\bRechn(?:ung|ungsnummer|ungs-Nr\.?)\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
                '/\bInvoice\s+(?:No\.?|Number)\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
            ]),
            'invoice_date' => $this->matchFieldValue($searchableText, [
                '/\bRechn(?:ungsdatum|ung)\s*:?\s*([0-9]{1,2}[\.\/-][0-9]{1,2}[\.\/-][0-9]{2,4})/iu',
                '/\bInvoice\s+Date\s*:?\s*([0-9]{1,2}[\.\/-][0-9]{1,2}[\.\/-][0-9]{2,4})/iu',
                '/\bDatum\s*:?\s*([0-9]{1,2}[\.\/-][0-9]{1,2}[\.\/-][0-9]{2,4})/iu',
            ]),
            'delivery_date' => $this->matchFieldValue($searchableText, [
                '/\bLieferdatum\s*:?\s*([0-9]{1,2}[\.\/-][0-9]{1,2}[\.\/-][0-9]{2,4})/iu',
                '/\bDelivery\s+Date\s*:?\s*([0-9]{1,2}[\.\/-][0-9]{1,2}[\.\/-][0-9]{2,4})/iu',
            ]),
            'currency' => $this->matchFieldValue($searchableText, [
                '/\b(EUR|BAM|KM|USD|CHF|GBP)\b/u',
            ]),
            'total_net_amount' => $this->matchAmount($searchableText, [
                '/\bNettowert\s*:?\s*([0-9\.\,\s]+)/iu',
                '/\bNettobetrag\s*:?\s*([0-9\.\,\s]+)/iu',
                '/\bNetto(?:\s+Amount|\s+Total)?\s*:?\s*([0-9\.\,\s]+)/iu',
                '/\bSubtotal\s*:?\s*([0-9\.\,\s]+)/iu',
                '/\bGesamtbetrag\s*:?\s*([0-9\.\,\s]+)/iu',
            ]),
            'total_gross_amount' => $this->matchAmount($searchableText, [
                '/\bBruttobetrag\s*:?\s*([0-9\.\,\s]+)/iu',
                '/\bGross(?:\s+Amount|\s+Total)?\s*:?\s*([0-9\.\,\s]+)/iu',
                '/\bGrand\s+Total\s*:?\s*([0-9\.\,\s]+)/iu',
            ]),
        ];
    }

    private function matchFieldValue(string $text, array $patterns): string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return Utf8Sanitizer::clean(trim((string) ($matches[1] ?? '')));
            }
        }

        return '';
    }

    private function matchAmount(string $text, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return $this->normalizeNumber((string) ($matches[1] ?? ''));
            }
        }

        return null;
    }

    private function normalizeNumber(string $value): ?float
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,\.\-]+/u', '', $normalized) ?? $normalized;

        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (substr_count($normalized, ',') === 1 && substr_count($normalized, '.') === 0) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (substr_count($normalized, '.') > 1 && substr_count($normalized, ',') === 0) {
            $lastDot = strrpos($normalized, '.');
            $normalized = str_replace('.', '', substr($normalized, 0, $lastDot)) . substr($normalized, $lastDot);
        }

        return is_numeric($normalized) ? round((float) $normalized, 4) : null;
    }
}
