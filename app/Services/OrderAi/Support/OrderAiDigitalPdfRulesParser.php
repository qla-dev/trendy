<?php

namespace App\Services\OrderAi\Support;

use App\Models\OrderAiScan;
use App\Support\Utf8Sanitizer;
use Illuminate\Support\Str;

class OrderAiDigitalPdfRulesParser
{
    private const TRENDY_DE_PARTY_NAME = 'Trendy Germany GmbH';
    private const LOCAL_PROVIDER = 'digital_pdf_rules';
    private const LOCAL_MODEL = 'local-digital-pdf-rules-v1';

    public function parse(OrderAiScan $scan, array $preparedDocument): ?array
    {
        if (trim((string) ($preparedDocument['pdf_type'] ?? '')) !== 'digital') {
            return null;
        }

        $profileKey = trim((string) ($scan->document_profile ?? ''));
        $startedAt = microtime(true);

        return match ($profileKey) {
            'grob' => $this->parseGrob($scan, $preparedDocument, $startedAt),
            'trendy_de' => $this->parseTrendyDe($scan, $preparedDocument, $startedAt),
            default => null,
        };
    }

    private function parseGrob(OrderAiScan $scan, array $preparedDocument, float $startedAt): ?array
    {
        $searchableText = trim((string) ($preparedDocument['searchable_text'] ?? ''));
        $processedPages = is_array($preparedDocument['processed_pages'] ?? null)
            ? $preparedDocument['processed_pages']
            : [];
        $parsedItems = $processedPages !== []
            ? $this->parseGrobItemsFromPages($processedPages)
            : $this->parseGrobItemsFromText($searchableText);

        if ($parsedItems === []) {
            return null;
        }

        $supplierName = $this->extractFieldValue($searchableText, [
            '/\b(GROB-WERKE\s+GmbH\s*&\s*Co\.\s*KG)\b/iu',
            '/\b(GROB-WERKE)\b/iu',
        ]);
        $customerName = $this->resolveGrobCustomerName($searchableText);
        $documentNumber = $this->extractFieldValue($searchableText, [
            '/\bBestell-Nr\.?\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
            '/\bBestellung\s*:?\s*([A-Z0-9\-\/\.]+)/iu',
        ]);
        $currency = $this->extractCurrency($searchableText);
        $pageCount = max(
            0,
            (int) ($preparedDocument['effective_page_count'] ?? 0),
            (int) ($preparedDocument['source_page_count'] ?? 0)
        );
        $lineTotalSum = round(array_reduce($parsedItems, function (float $carry, array $item): float {
            return $carry + max(0, (float) ($item['line_total'] ?? 0));
        }, 0.0), 4);
        $netTotal = $this->extractLastGermanAmountAfterLabels($searchableText, ['Nettowert', 'Gesamtbetrag']);
        $grossTotal = $this->extractLastGermanAmountAfterLabels($searchableText, ['Gesamtbetrag', 'Bruttobetrag']);

        if ($netTotal <= 0) {
            $netTotal = $lineTotalSum;
        }

        if ($grossTotal <= 0) {
            $grossTotal = $netTotal;
        }

        $warnings = [];

        if ($customerName === '') {
            $warnings[] = 'Kupac nije eksplicitno pronadjen u GROB dokumentu; primijenjen je profilni fallback.';
        }

        $payload = [
            'order' => [
                'customer_name' => $customerName,
                'supplier_name' => $supplierName,
                'page_count' => $pageCount,
                'receiver_name' => $customerName,
                'contact_name' => '',
                'external_document_number' => $documentNumber,
                'document_type' => (string) config('ai-order-scan.default_doc_type', '0110'),
                'currency' => $currency !== '' ? $currency : 'EUR',
                'delivery_deadline' => '',
                'note' => '',
                'way_of_sale' => (string) config('ai-order-scan.default_way_of_sale', 'D'),
                'confidence' => $this->resolveGrobConfidence($parsedItems, $documentNumber, $supplierName),
                'warnings' => $warnings,
            ],
            'items' => array_values(array_map(function (array $item): array {
                $note = trim((string) ($item['note'] ?? ''));

                if (!empty($item['warnings']) && is_array($item['warnings'])) {
                    $note = $this->appendItemNote($note, implode(' | ', array_values(array_unique(array_filter($item['warnings'])))));
                }

                return [
                    'line_number' => (int) ($item['line_number'] ?? 0),
                    'product_code' => $this->normalizeScannedProductCode((string) ($item['product_code'] ?? '')),
                    'product_name' => $this->normalizeScannedProductName((string) ($item['product_name'] ?? '')),
                    'drawing_reference' => trim((string) ($item['drawing_reference'] ?? '')),
                    'material_hint' => trim((string) ($item['material_hint'] ?? '')),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit' => $this->normalizeScannedUnit((string) ($item['unit'] ?? '')),
                    'delivery_deadline' => trim((string) ($item['delivery_deadline'] ?? '')),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                    'vat_rate' => 0.0,
                    'vat_code' => (string) config('ai-order-scan.default_vat_code', 'P1'),
                    'discount_percent' => 0.0,
                    'priority' => '',
                    'note' => $note,
                ];
            }, $parsedItems)),
            'summary' => [
                'subtotal' => $netTotal,
                'vat_total' => max(0, round($grossTotal - $netTotal, 4)),
                'grand_total' => $grossTotal,
            ],
        ];

        return $this->buildResult($scan, $preparedDocument, $payload, 'grob_rules_v1', $startedAt, [
            'profile' => 'grob',
            'matched_item_count' => count($parsedItems),
            'line_total_sum' => $lineTotalSum,
        ]);
    }

    private function parseTrendyDe(OrderAiScan $scan, array $preparedDocument, float $startedAt): ?array
    {
        $searchableText = trim((string) ($preparedDocument['searchable_text'] ?? ''));
        $lines = $this->flattenPreparedLines($preparedDocument);
        $items = $this->parseTrendyDeItems($lines);

        if ($items === []) {
            return null;
        }

        $header = $this->extractTrendyDeHeader($preparedDocument, $searchableText);
        $documentNumber = $header['document_number'] !== ''
            ? $header['document_number']
            : $this->extractTrendyDeDocumentNumber(
                $searchableText,
                (string) ($scan->source_file_name ?? '')
            );
        $deliveryDeadline = $header['delivery_deadline'];
        if ($deliveryDeadline !== '') {
            $items = array_values(array_map(function (array $item) use ($deliveryDeadline): array {
                $item['delivery_deadline'] = $deliveryDeadline;

                return $item;
            }, $items));
        }
        $contactName = $header['contact_name'];
        $receiverName = $header['receiver_name'];
        $currency = $this->extractCurrency($searchableText);
        $pageCount = max(
            0,
            (int) ($preparedDocument['effective_page_count'] ?? 0),
            (int) ($preparedDocument['source_page_count'] ?? 0)
        );
        $lineTotalSum = round(array_reduce($items, function (float $carry, array $item): float {
            return $carry + max(0, (float) ($item['line_total'] ?? 0));
        }, 0.0), 4);
        $netTotal = $this->extractTrendyDeTotal($preparedDocument, $searchableText);

        if ($netTotal <= 0) {
            $netTotal = $this->extractLastGermanAmountAfterLabels($searchableText, ['Nettowert', 'Gesamtbetrag', 'Betrag']);
        }

        if ($netTotal <= 0 || ($lineTotalSum > 0 && $netTotal < $lineTotalSum)) {
            $netTotal = $lineTotalSum;
        }

        $noteParts = [];

        foreach ([$header['supplier_note']] as $notePart) {
            if ($notePart === '') {
                continue;
            }

            $noteParts[$notePart] = $notePart;
        }

        $payload = [
            'order' => [
                'customer_name' => self::TRENDY_DE_PARTY_NAME,
                'supplier_name' => self::TRENDY_DE_PARTY_NAME,
                'page_count' => $pageCount,
                'receiver_name' => $receiverName !== '' ? $receiverName : self::TRENDY_DE_PARTY_NAME,
                'contact_name' => $contactName,
                'external_document_number' => $documentNumber,
                'document_type' => (string) config('ai-order-scan.default_doc_type', '0110'),
                'currency' => $currency !== '' ? $currency : 'EUR',
                'delivery_deadline' => $deliveryDeadline,
                'note' => implode(' | ', array_values($noteParts)),
                'way_of_sale' => (string) config('ai-order-scan.default_way_of_sale', 'D'),
                'confidence' => $this->resolveTrendyDeConfidence($items, $documentNumber, $receiverName),
                'warnings' => [],
            ],
            'items' => $items,
            'summary' => [
                'subtotal' => $netTotal,
                'vat_total' => 0.0,
                'grand_total' => $netTotal,
            ],
        ];

        return $this->buildResult($scan, $preparedDocument, $payload, 'trendy_de_rules_v1', $startedAt, [
            'profile' => 'trendy_de',
            'matched_item_count' => count($items),
            'line_total_sum' => $lineTotalSum,
        ]);
    }

    private function buildResult(
        OrderAiScan $scan,
        array $preparedDocument,
        array $payload,
        string $parserName,
        float $startedAt,
        array $rawMeta = []
    ): ?array {
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($items === []) {
            return null;
        }

        $parseDurationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $payload['order']['warnings'] = array_values(array_unique(array_filter(array_map(function ($warning) {
            return Utf8Sanitizer::clean(trim((string) $warning));
        }, is_array($payload['order']['warnings'] ?? null) ? $payload['order']['warnings'] : []))));

        return [
            'provider' => self::LOCAL_PROVIDER,
            'model' => self::LOCAL_MODEL,
            'credits_spent' => 0.0,
            'raw_response' => Utf8Sanitizer::cleanRecursive(array_merge([
                'strategy' => 'digital_pdf_rules',
                'parser' => $parserName,
                'profile' => trim((string) ($scan->document_profile ?? '')),
                'provider_input_mode' => trim((string) ($preparedDocument['provider_input_mode'] ?? '')),
                'pdf_type' => trim((string) ($preparedDocument['pdf_type'] ?? '')),
            ], $rawMeta)),
            'normalized_payload' => Utf8Sanitizer::cleanRecursive($payload),
            'prepared_document' => $preparedDocument,
            'extraction_duration_ms' => max(
                0,
                (int) ($preparedDocument['extraction_duration_ms'] ?? 0) + $parseDurationMs
            ),
            'ai_duration_ms' => 0,
            'supports_live_transfer' => true,
        ];
    }

    private function parseTrendyDeItems(array $lines): array
    {
        $items = [];
        $openItem = null;
        $queuedItem = null;
        $tableStarted = false;

        foreach ($lines as $line) {
            $line = trim($line);
            $keywordLine = $this->normalizeKeywordText($line);
            $codeAmountItem = $this->parseTrendyDeCodeAmountRow($line);
            $codeOnly = $this->parseTrendyDeCodeOnlyRow($line);

            if (!$tableStarted) {
                if ($this->isTrendyDeTableHeaderLine($keywordLine) || $codeAmountItem !== null || $codeOnly !== '') {
                    $tableStarted = true;
                } else {
                    continue;
                }
            }

            if ($line === '' || $this->isTrendyDeNoiseLine($keywordLine)) {
                continue;
            }

            if ($this->isTrendyDeSummaryLine($keywordLine)) {
                break;
            }

            if ($codeAmountItem !== null) {
                if ($this->isTrendyDeItemReady($openItem)) {
                    $items[] = $this->finalizeTrendyDeItem($openItem);
                }

                $openItem = $codeAmountItem;
                $queuedItem = null;

                continue;
            }

            if ($codeOnly !== '') {
                if ($this->isTrendyDeItemReady($openItem)) {
                    $items[] = $this->finalizeTrendyDeItem($openItem);
                }

                $openItem = $this->initializeTrendyDeParsedItem(0, $codeOnly, '');
                $queuedItem = null;

                continue;
            }

            if ($queuedItem !== null && $this->isTrendyDeItemReady($openItem)) {
                $items[] = $this->finalizeTrendyDeItem($openItem);
                $openItem = $queuedItem;
                $queuedItem = null;
            }

            [$contentPart, $detectedNextItem, $startsNewItem] = $this->splitTrendyDeLineIntoContentAndNextItem($line);

            if ($startsNewItem && $detectedNextItem !== null && $this->isTrendyDeItemReady($openItem)) {
                $items[] = $this->finalizeTrendyDeItem($openItem);
                $openItem = null;
            }

            if ($openItem === null && $detectedNextItem !== null) {
                $openItem = $detectedNextItem;
                $detectedNextItem = null;
            }

            if ($openItem === null && $queuedItem !== null) {
                $openItem = $queuedItem;
                $queuedItem = null;
            }

            if ($contentPart !== '' && $openItem !== null) {
                $this->applyTrendyDeLineToItem($openItem, $contentPart);
            }

            if ($detectedNextItem !== null) {
                if ($openItem === null) {
                    $openItem = $detectedNextItem;
                } else {
                    $queuedItem = $detectedNextItem;
                }
            }
        }

        if ($queuedItem !== null && $this->isTrendyDeItemReady($openItem)) {
            $items[] = $this->finalizeTrendyDeItem($openItem);
            $openItem = $queuedItem;
            $queuedItem = null;
        }

        if (is_array($openItem)) {
            $items[] = $this->finalizeTrendyDeItem($openItem);
        }

        if (is_array($queuedItem)) {
            $items[] = $this->finalizeTrendyDeItem($queuedItem);
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item)
                && trim((string) ($item['product_code'] ?? '')) !== '';
        }));
    }

    private function isTrendyDeTableHeaderLine(string $normalizedLine): bool
    {
        return str_contains($normalizedLine, 'artikel nr')
            && preg_match('/\bpos\.?\b/u', $normalizedLine) === 1;
    }

    private function parseTrendyDeCodeAmountRow(string $line): ?array
    {
        $normalized = trim($line);

        if (preg_match('/^(\d{6,12})\s+(.+)$/u', $normalized, $matches) !== 1) {
            return null;
        }

        $productCode = trim((string) ($matches[1] ?? ''));
        $remainder = trim((string) ($matches[2] ?? ''));
        $unit = $this->extractTrendyDeUnitToken($remainder);
        $amounts = $this->extractGermanAmounts($remainder, true);

        if ($productCode === '' || $unit === '' || count($amounts) < 3) {
            return null;
        }

        $item = $this->initializeTrendyDeParsedItem(0, $productCode, '');
        $item['line_total'] = (float) ($amounts[0] ?? 0);

        if (count($amounts) >= 4) {
            $item['vat_rate'] = (float) ($amounts[1] ?? 0);
            $item['quantity'] = (float) ($amounts[2] ?? 0);
            $item['unit_price'] = (float) ($amounts[3] ?? 0);
        } else {
            $item['quantity'] = (float) ($amounts[1] ?? 0);
            $item['unit_price'] = (float) ($amounts[2] ?? 0);
        }

        $item['unit'] = $unit;

        return $item;
    }

    private function parseTrendyDeCodeOnlyRow(string $line): string
    {
        $normalized = trim($line);

        if (preg_match('/^\d{6,12}$/u', $normalized) === 1) {
            return $normalized;
        }

        return '';
    }

    private function splitTrendyDeLineIntoContentAndNextItem(string $line): array
    {
        $normalized = trim($line);

        if ($normalized === '') {
            return ['', null, false];
        }

        if (preg_match('/^(\d{1,3})\s+(\d{8})(?:\s+(.*))?$/u', $normalized, $matches) === 1) {
            $inlineContent = trim((string) ($matches[3] ?? ''));

            return [
                $inlineContent,
                $this->initializeTrendyDeParsedItem(
                    (int) ($matches[1] ?? 0),
                    (string) ($matches[2] ?? ''),
                    ''
                ),
                true,
            ];
        }

        if (
            preg_match('/^(.*?)(?:\s+)(\d{1,3})\s+(\d{8})(?:\s+([^\d].*))?$/u', $normalized, $matches) === 1
            && trim((string) ($matches[1] ?? '')) !== ''
        ) {
            return [
                trim((string) ($matches[1] ?? '')),
                $this->initializeTrendyDeParsedItem(
                    (int) ($matches[2] ?? 0),
                    (string) ($matches[3] ?? ''),
                    trim((string) ($matches[4] ?? ''))
                ),
                false,
            ];
        }

        return [$normalized, null, false];
    }

    private function initializeTrendyDeParsedItem(int $lineNumber, string $productCode, string $initialDescription = ''): array
    {
        return [
            'line_number' => $lineNumber,
            'product_code' => trim($productCode),
            'description_lines' => $initialDescription !== '' ? [trim($initialDescription)] : [],
            'quantity' => 0.0,
            'unit' => '',
            'unit_price' => 0.0,
            'vat_rate' => 0.0,
            'line_total' => 0.0,
            'delivery_deadline' => '',
        ];
    }

    private function applyTrendyDeLineToItem(array &$item, string $line): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        if (
            (int) ($item['line_number'] ?? 0) <= 0
            && preg_match('/^(.+?)\s+(\d{1,3})$/u', $line, $matches) === 1
        ) {
            $descriptionCandidate = trim((string) ($matches[1] ?? ''));

            if ($descriptionCandidate !== '') {
                $item['line_number'] = (int) ($matches[2] ?? 0);
                $line = $descriptionCandidate;
            }
        }

        if (str_contains($this->normalizeKeywordText($line), 'liefertermin')) {
            $deliveryDeadline = $this->extractVisibleDateFromLine($line);

            if ($deliveryDeadline !== '') {
                $item['delivery_deadline'] = $deliveryDeadline;
            }

            return;
        }

        $unitMatch = $this->extractTrendyDeUnitToken($line);
        $amounts = $this->extractGermanAmounts($line, true);
        $keywordLine = $this->normalizeKeywordText($line);

        if (
            $unitMatch !== ''
            && count($amounts) >= 4
            && (str_contains($keywordLine, 'betrag') || str_contains($keywordLine, 'vat %'))
        ) {
            $item['line_total'] = (float) ($amounts[0] ?? $item['line_total']);
            $item['vat_rate'] = (float) ($amounts[1] ?? $item['vat_rate']);
            $item['quantity'] = (float) ($amounts[2] ?? $item['quantity']);
            $item['unit_price'] = (float) ($amounts[3] ?? $item['unit_price']);
            $item['unit'] = $unitMatch;

            return;
        }

        $descriptionText = $this->stripTrendyDeNumbersAndUnits($line);

        if ($descriptionText !== '') {
            $item['description_lines'][] = $descriptionText;
        }

        if ($unitMatch !== '') {
            $item['unit'] = $unitMatch;
        }

        if ($amounts === []) {
            return;
        }

        if (count($amounts) >= 4) {
            $item['quantity'] = (float) ($amounts[0] ?? $item['quantity']);
            $item['unit_price'] = (float) ($amounts[1] ?? $item['unit_price']);
            $item['vat_rate'] = (float) ($amounts[2] ?? $item['vat_rate']);
            $item['line_total'] = (float) ($amounts[3] ?? $item['line_total']);
            return;
        }

        if (count($amounts) === 3) {
            $item['quantity'] = (float) ($amounts[0] ?? $item['quantity']);
            $item['vat_rate'] = (float) ($amounts[1] ?? $item['vat_rate']);
            $item['line_total'] = (float) ($amounts[2] ?? $item['line_total']);
            return;
        }

        if (count($amounts) === 2) {
            if ($descriptionText === '' && $unitMatch === '' && (float) ($item['quantity'] ?? 0) <= 0) {
                $item['quantity'] = (float) ($amounts[0] ?? 0);
                $item['unit_price'] = (float) ($amounts[1] ?? 0);
                return;
            }

            if ($descriptionText === '' && (float) ($item['quantity'] ?? 0) > 0 && (float) ($item['line_total'] ?? 0) <= 0) {
                $item['vat_rate'] = (float) ($amounts[0] ?? $item['vat_rate']);
                $item['line_total'] = (float) ($amounts[1] ?? $item['line_total']);
                return;
            }

            if ($unitMatch !== '' && (float) ($item['unit_price'] ?? 0) <= 0) {
                $item['unit_price'] = (float) ($amounts[1] ?? 0);
                return;
            }

            if ((float) ($item['quantity'] ?? 0) <= 0) {
                $item['quantity'] = (float) ($amounts[0] ?? 0);
            }

            if ((float) ($item['unit_price'] ?? 0) <= 0) {
                $item['unit_price'] = (float) ($amounts[1] ?? 0);
            }

            return;
        }

        if (count($amounts) === 1 && $unitMatch !== '' && (float) ($item['unit_price'] ?? 0) <= 0) {
            $item['unit_price'] = (float) ($amounts[0] ?? 0);
        }
    }

    private function isTrendyDeItemReady(?array $item): bool
    {
        if (!is_array($item)) {
            return false;
        }

        return trim((string) ($item['product_code'] ?? '')) !== ''
            && !empty($item['description_lines'])
            && (float) ($item['quantity'] ?? 0) > 0
            && trim((string) ($item['unit'] ?? '')) !== ''
            && (float) ($item['line_total'] ?? 0) > 0;
    }

    private function extractTrendyDeUnitToken(string $line): string
    {
        if (preg_match('/\b(STU|ST|PCS|PIECE|KO)\b/u', $line, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function stripTrendyDeNumbersAndUnits(string $line): string
    {
        $value = preg_replace('/' . $this->germanAmountPattern() . '/u', ' ', $line) ?? $line;
        $value = preg_replace('/\b(STU|ST|PCS|PIECE|KO)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function finalizeTrendyDeItem(array $item): array
    {
        if (
            (float) ($item['unit_price'] ?? 0) <= 0
            && (float) ($item['quantity'] ?? 0) > 0
            && (float) ($item['line_total'] ?? 0) > 0
        ) {
            $item['unit_price'] = round((float) $item['line_total'] / max(0.0001, (float) $item['quantity']), 4);
        }

        $descriptionLines = array_values(array_filter(array_map(function ($line) {
            return trim((string) $line);
        }, $item['description_lines'] ?? [])));
        $productName = trim((string) ($descriptionLines[0] ?? ''));
        $note = implode(' | ', array_values(array_filter(array_slice($descriptionLines, 1))));

        return [
            'line_number' => (int) ($item['line_number'] ?? 0),
            'product_code' => $this->normalizeScannedProductCode((string) ($item['product_code'] ?? '')),
            'product_name' => $this->normalizeScannedProductName($productName),
            'drawing_reference' => '',
            'material_hint' => '',
            'quantity' => (float) ($item['quantity'] ?? 0),
            'unit' => $this->normalizeScannedUnit((string) ($item['unit'] ?? '')),
            'delivery_deadline' => trim((string) ($item['delivery_deadline'] ?? '')),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'line_total' => (float) ($item['line_total'] ?? 0),
            'vat_rate' => (float) ($item['vat_rate'] ?? 0),
            'vat_code' => (string) config('ai-order-scan.default_vat_code', 'P1'),
            'discount_percent' => 0.0,
            'priority' => '',
            'note' => $note,
        ];
    }

    private function isTrendyDeSummaryLine(string $normalizedLine): bool
    {
        return $normalizedLine !== ''
            && (
                str_contains($normalizedLine, 'nettowert')
                || str_contains($normalizedLine, 'gesamtbetrag')
                || preg_match('/\btotal\b/u', $normalizedLine) === 1
                || str_contains($normalizedLine, 'subtotal')
                || str_contains($normalizedLine, 'grand total')
            );
    }

    private function isTrendyDeNoiseLine(string $normalizedLine): bool
    {
        return $normalizedLine === ''
            || str_contains($normalizedLine, '%pdf-')
            || preg_match('/^page\s+\d+\/\d+$/u', $normalizedLine) === 1
            || str_contains($normalizedLine, 'trendy germany gmbh')
            || str_contains($normalizedLine, 'lieferant')
            || str_contains($normalizedLine, 'anlieferadresse')
            || str_contains($normalizedLine, 'person responsible')
            || str_contains($normalizedLine, 'bestellung')
            || str_contains($normalizedLine, 'datum ');
    }

    private function flattenPreparedLines(array $preparedDocument): array
    {
        $pages = is_array($preparedDocument['processed_pages'] ?? null)
            ? $preparedDocument['processed_pages']
            : [];
        $lines = [];

        foreach ($pages as $page) {
            foreach ($this->extractPageLines($page) as $line) {
                $lines[] = $line;
            }
        }

        if ($lines !== []) {
            return $lines;
        }

        return $this->splitVisibleTextLines((string) ($preparedDocument['searchable_text'] ?? ''));
    }

    private function extractTrendyDeHeader(array $preparedDocument, string $searchableText): array
    {
        $partyRows = [];

        foreach ((array) ($preparedDocument['processed_pages'] ?? []) as $page) {
            foreach ((array) ($page['items'] ?? []) as $row) {
                if (is_array($row)) {
                    $partyRows[] = $row;
                }
            }
        }

        $supplierLines = [];
        $receiverLines = [];
        $partySectionMode = null;
        $deliveryDeadline = '';
        $contactName = '';
        $documentNumber = '';

        foreach ($partyRows as $row) {
            $text = trim((string) ($row['text'] ?? ''));
            $normalized = $this->normalizeKeywordText($text);
            $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];

            if ($normalized === '') {
                continue;
            }

            if (str_contains($normalized, 'lieferant') && str_contains($normalized, 'anlieferadresse')) {
                if (str_contains($normalized, 'artikel nr') || str_contains($normalized, 'pos.')) {
                    $partySectionMode = null;
                    continue;
                }

                $partySectionMode = 'combined';
                continue;
            }

            if ($normalized === 'lieferant:') {
                $partySectionMode = 'supplier';
                continue;
            }

            if ($normalized === 'anlieferadresse:') {
                $partySectionMode = 'receiver';
                continue;
            }

            if ($partySectionMode !== null) {
                if (str_starts_with($normalized, 'datum ') || str_contains($normalized, 'liefertermin')) {
                    $partySectionMode = null;
                } elseif ($this->isTrendyDeTableOrItemRow($normalized, $text)) {
                    $partySectionMode = null;
                } else {
                    if ($partySectionMode === 'combined') {
                        [$leftText, $rightText] = $this->splitTrendyDeRowByColumns($cells, 220.0);

                        if ($leftText !== '') {
                            $supplierLines[$leftText] = $leftText;
                        }

                        if ($rightText !== '') {
                            $receiverLines[$rightText] = $rightText;
                        }
                    } elseif ($partySectionMode === 'supplier') {
                        $supplierLines[$text] = $text;
                    } elseif ($partySectionMode === 'receiver') {
                        $receiverLines[$text] = $text;
                    }

                    continue;
                }
            }

            if (
                ($documentNumber === '' || $contactName === '')
                && preg_match('/\b([0-9]{2}-[0-9]{3}-[0-9]+)\b\s+(.+)$/u', $text, $matches) === 1
            ) {
                $documentNumber = $documentNumber !== ''
                    ? $documentNumber
                    : trim((string) ($matches[1] ?? ''));
                $contactName = $contactName !== ''
                    ? $contactName
                    : $this->normalizeProfileWhitespace((string) ($matches[2] ?? ''));
            }

            if ($deliveryDeadline === '' && str_contains($normalized, 'liefertermin')) {
                $deliveryDeadline = $this->extractVisibleDateFromLine($text);
            }

            if ($contactName === '' && preg_match('/person\s+responsible\s+(.+)$/iu', $text, $matches) === 1) {
                $contactName = $this->normalizeProfileWhitespace((string) ($matches[1] ?? ''));
            }

            if ($documentNumber === '' && str_contains($normalized, 'bestellung')) {
                $documentNumber = $this->extractTrendyDeDocumentNumber($text, '');
            }
        }

        if ($contactName === '') {
            $contactName = $this->extractProfileFieldValue($searchableText, 'Person responsible');
        }

        if ($deliveryDeadline === '') {
            $deliveryDeadline = $this->extractVisibleDateFromLine(
                $this->extractProfileFieldValue($searchableText, 'Liefertermin')
            );
        }

        if ($documentNumber === '') {
            $documentNumber = $this->extractTrendyDeDocumentNumber($searchableText, '');
        }

        $receiverName = '';

        if ($receiverLines !== []) {
            $receiverName = trim((string) reset($receiverLines));
        }

        if ($receiverName === '') {
            $receiverName = $this->extractTrendyDeReceiverFallback($preparedDocument, $searchableText);
        }

        return [
            'supplier_note' => implode(' | ', array_values($supplierLines)),
            'receiver_name' => $receiverName,
            'delivery_deadline' => $deliveryDeadline,
            'contact_name' => $contactName,
            'document_number' => $documentNumber,
        ];
    }

    private function isTrendyDeTableOrItemRow(string $normalizedLine, string $line): bool
    {
        return $this->isTrendyDeTableHeaderLine($normalizedLine)
            || $this->isTrendyDeSummaryLine($normalizedLine)
            || $this->parseTrendyDeCodeAmountRow($line) !== null
            || $this->parseTrendyDeCodeOnlyRow($line) !== '';
    }

    private function extractTrendyDeReceiverFallback(array $preparedDocument, string $searchableText): string
    {
        foreach ($this->flattenPreparedLines($preparedDocument) as $line) {
            if (preg_match('/\bTrendy\s+d\.?o\.?o\.?\b/iu', $line, $matches) === 1) {
                return $this->normalizeProfileWhitespace((string) ($matches[0] ?? ''));
            }
        }

        if (preg_match('/\bTrendy\s+d\.?o\.?o\.?\b/iu', $searchableText, $matches) === 1) {
            return $this->normalizeProfileWhitespace((string) ($matches[0] ?? ''));
        }

        return '';
    }

    private function extractTrendyDeTotal(array $preparedDocument, string $searchableText): float
    {
        foreach ($this->flattenPreparedLines($preparedDocument) as $line) {
            $normalized = $this->normalizeKeywordText($line);

            if (!str_contains($normalized, 'total') && !str_contains($normalized, 'gesamtpreis')) {
                continue;
            }

            $amounts = $this->extractGermanAmounts($line, true);

            if ($amounts !== []) {
                return (float) ($amounts[0] ?? 0);
            }
        }

        if (preg_match('/Total\s*(' . $this->germanAmountPattern() . ')/iu', $searchableText, $matches) === 1) {
            return $this->parseGermanNumber((string) ($matches[1] ?? ''));
        }

        return 0.0;
    }

    private function splitTrendyDeRowByColumns(array $cells, float $rightColumnThreshold): array
    {
        if ($cells === []) {
            return ['', ''];
        }

        $leftParts = [];
        $rightParts = [];

        foreach ($cells as $cell) {
            if (!is_array($cell)) {
                continue;
            }

            $text = trim((string) ($cell['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if ((float) ($cell['x'] ?? 0) >= $rightColumnThreshold) {
                $rightParts[] = $text;
            } else {
                $leftParts[] = $text;
            }
        }

        return [
            trim(implode(' ', $leftParts)),
            trim(implode(' ', $rightParts)),
        ];
    }

    private function parseGrobItemsFromPages(array $pages): array
    {
        $items = [];
        $currentItem = null;
        $stopParsing = false;

        foreach ($pages as $page) {
            $pageLines = $this->extractPageLines($page);

            if ($pageLines === []) {
                continue;
            }

            if (($items !== [] || is_array($currentItem)) && $this->isGrobAttachmentPageForParsing($pageLines)) {
                break;
            }

            $page['lines'] = $pageLines;

            foreach ($this->prepareGrobPageLinesForParsing($page, is_array($currentItem)) as $line) {
                if ($this->isGrobAttentionMarkerLine($line)) {
                    $stopParsing = true;
                    break;
                }

                $newItem = $this->createGrobParsedItemFromLine($line);

                if ($newItem !== null) {
                    if (is_array($currentItem)) {
                        $items[] = $this->finalizeGrobParsedItem($currentItem);
                    }

                    $currentItem = $newItem;
                    continue;
                }

                if (!is_array($currentItem) || $this->isGrobNonItemNoiseLine($line)) {
                    continue;
                }

                if (
                    trim((string) ($currentItem['unit'] ?? '')) === ''
                    && preg_match('/^[A-Z]{1,5}$/u', $line) === 1
                ) {
                    $currentItem['unit'] = trim($line);
                    continue;
                }

                if (
                    $currentItem['product_code'] === ''
                    && preg_match('/^[A-Z0-9][A-Z0-9.\-\/]{2,}$/iu', $line) === 1
                    && !$this->isGrobKeywordLine($line)
                ) {
                    $currentItem['product_code'] = trim($line);
                    continue;
                }

                if ($this->extractKeywordSegment($line, 'zeichnung') !== null) {
                    $prefix = $this->extractGrobPrefixBeforeKeyword($line, 'zeichnung');

                    if ($prefix !== '') {
                        $currentItem['product_name_lines'][] = $prefix;
                    }

                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['drawing_reference'] = trim(implode(' | ', array_filter([
                        $currentItem['drawing_reference'],
                        $this->extractKeywordSegment($line, 'zeichnung'),
                    ])));
                    continue;
                }

                $materialHint = $this->extractGrobMaterialHint($line);

                if ($materialHint !== '') {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['material_hint'] = $materialHint;
                    continue;
                }

                if ($this->containsGrobKeyword($line, ['kontierung', 'ref. des.'])) {
                    $currentItem['product_name_capture_complete'] = true;
                    $this->appendGrobItemNoteLine($currentItem, $line);
                    continue;
                }

                if (preg_match('/\bpreiseinheit\b.*?\b([A-Z]{1,5})\b/iu', $line, $matches) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $candidateUnit = trim((string) ($matches[1] ?? ''));

                    if (strcasecmp($candidateUnit, 'PRO') !== 0) {
                        $currentItem['unit'] = $candidateUnit;
                    }

                    continue;
                }

                if ($this->containsGrobKeyword($line, ['bruttopreis'])) {
                    $currentItem['product_name_capture_complete'] = true;
                    continue;
                }

                $nettoSegment = $this->extractKeywordSegment($line, 'nettopreis');

                if ($nettoSegment !== null) {
                    $currentItem['product_name_capture_complete'] = true;
                    $this->populateGrobItemFromNettoLine($currentItem, $nettoSegment);
                    continue;
                }

                if (preg_match('/\bwert\b.*?(' . $this->germanAmountPattern() . ')/iu', $line, $matches) === 1) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['line_total'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                    $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                    continue;
                }

                $deliverySegment = $this->extractKeywordSegment($line, 'lieferdatum');

                if ($deliverySegment !== null) {
                    $currentItem['product_name_capture_complete'] = true;
                    $currentItem['delivery_deadline'] = $this->extractVisibleDateFromLine($deliverySegment);

                    if (
                        !($currentItem['line_total_found'] ?? false)
                        && preg_match('/(' . $this->germanAmountPattern() . ')\s+lieferdatum\b/iu', $line, $matches) === 1
                    ) {
                        $currentItem['line_total'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                        $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                    }

                    if (preg_match('/(' . $this->germanAmountPattern(true) . ')\s+([A-Z]{1,5})\b/u', $deliverySegment, $matches) === 1) {
                        if ((float) ($currentItem['quantity'] ?? 0) <= 0) {
                            $currentItem['quantity'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                        }

                        if (trim((string) ($currentItem['unit'] ?? '')) === '') {
                            $currentItem['unit'] = trim((string) ($matches[2] ?? ''));
                        }
                    } elseif (
                        (float) ($currentItem['quantity'] ?? 0) <= 0
                        && preg_match('/(' . $this->germanAmountPattern(true) . ')\s+([A-Z]{1,5})\b/u', $line, $matches) === 1
                    ) {
                        $currentItem['quantity'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));

                        if (trim((string) ($currentItem['unit'] ?? '')) === '') {
                            $currentItem['unit'] = trim((string) ($matches[2] ?? ''));
                        }
                    }

                    continue;
                }

                if (
                    ($currentItem['netto_price_found'] ?? false)
                    && !($currentItem['line_total_found'] ?? false)
                    && preg_match('/^' . $this->germanAmountPattern() . '$/u', trim($line)) === 1
                ) {
                    $currentItem['line_total'] = $this->parseGermanNumber(trim($line));
                    $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                    continue;
                }

                if ($this->isGrobKeywordLine($line)) {
                    $currentItem['product_name_capture_complete'] = true;
                    continue;
                }

                if (!($currentItem['product_name_capture_complete'] ?? false)) {
                    $currentItem['product_name_lines'][] = $line;
                    continue;
                }

                $this->appendGrobItemNoteLine($currentItem, $line);
            }

            if ($stopParsing) {
                break;
            }
        }

        if (is_array($currentItem)) {
            $items[] = $this->finalizeGrobParsedItem($currentItem);
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item)
                && (((int) ($item['line_number'] ?? 0)) > 0 || trim((string) ($item['product_code'] ?? '')) !== '');
        }));
    }

    private function parseGrobItemsFromText(string $searchableText): array
    {
        $items = [];
        $currentItem = null;

        foreach ($this->splitVisibleTextLines($searchableText) as $line) {
            $newItem = $this->createGrobParsedItemFromLine($line);

            if ($newItem !== null) {
                if (is_array($currentItem)) {
                    $items[] = $this->finalizeGrobParsedItem($currentItem);
                }

                $currentItem = $newItem;
                continue;
            }

            if (!is_array($currentItem)) {
                continue;
            }

            if (
                trim((string) ($currentItem['unit'] ?? '')) === ''
                && preg_match('/^[A-Z]{1,5}$/u', $line) === 1
            ) {
                $currentItem['unit'] = trim($line);
                continue;
            }

            if (
                $currentItem['product_code'] === ''
                && preg_match('/^[A-Z0-9][A-Z0-9.\-\/]{2,}$/iu', $line) === 1
                && !$this->isGrobKeywordLine($line)
            ) {
                $currentItem['product_code'] = trim($line);
                continue;
            }

            if ($this->extractKeywordSegment($line, 'zeichnung') !== null) {
                $prefix = $this->extractGrobPrefixBeforeKeyword($line, 'zeichnung');

                if ($prefix !== '') {
                    $currentItem['product_name_lines'][] = $prefix;
                }

                $currentItem['product_name_capture_complete'] = true;
                $currentItem['drawing_reference'] = trim(implode(' | ', array_filter([
                    $currentItem['drawing_reference'],
                    $this->extractKeywordSegment($line, 'zeichnung'),
                ])));
                continue;
            }

            $materialHint = $this->extractGrobMaterialHint($line);

            if ($materialHint !== '') {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['material_hint'] = $materialHint;
                continue;
            }

            if ($this->containsGrobKeyword($line, ['kontierung', 'ref. des.'])) {
                $currentItem['product_name_capture_complete'] = true;
                $this->appendGrobItemNoteLine($currentItem, $line);
                continue;
            }

            if (preg_match('/\bpreiseinheit\b.*?\b([A-Z]{1,5})\b/iu', $line, $matches) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $candidateUnit = trim((string) ($matches[1] ?? ''));

                if (strcasecmp($candidateUnit, 'PRO') !== 0) {
                    $currentItem['unit'] = $candidateUnit;
                }

                continue;
            }

            if ($this->containsGrobKeyword($line, ['bruttopreis'])) {
                $currentItem['product_name_capture_complete'] = true;
                continue;
            }

            $nettoSegment = $this->extractKeywordSegment($line, 'nettopreis');

            if ($nettoSegment !== null) {
                $currentItem['product_name_capture_complete'] = true;
                $this->populateGrobItemFromNettoLine($currentItem, $nettoSegment);
                continue;
            }

            if (preg_match('/\bwert\b.*?(' . $this->germanAmountPattern() . ')/iu', $line, $matches) === 1) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['line_total'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                continue;
            }

            $deliverySegment = $this->extractKeywordSegment($line, 'lieferdatum');

            if ($deliverySegment !== null) {
                $currentItem['product_name_capture_complete'] = true;
                $currentItem['delivery_deadline'] = $this->extractVisibleDateFromLine($deliverySegment);

                if (
                    !($currentItem['line_total_found'] ?? false)
                    && preg_match('/(' . $this->germanAmountPattern() . ')\s+lieferdatum\b/iu', $line, $matches) === 1
                ) {
                    $currentItem['line_total'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                    $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                }

                if (preg_match('/(' . $this->germanAmountPattern(true) . ')\s+([A-Z]{1,5})\b/u', $deliverySegment, $matches) === 1) {
                    if ((float) ($currentItem['quantity'] ?? 0) <= 0) {
                        $currentItem['quantity'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));
                    }

                    if (trim((string) ($currentItem['unit'] ?? '')) === '') {
                        $currentItem['unit'] = trim((string) ($matches[2] ?? ''));
                    }
                } elseif (
                    (float) ($currentItem['quantity'] ?? 0) <= 0
                    && preg_match('/(' . $this->germanAmountPattern(true) . ')\s+([A-Z]{1,5})\b/u', $line, $matches) === 1
                ) {
                    $currentItem['quantity'] = $this->parseGermanNumber((string) ($matches[1] ?? ''));

                    if (trim((string) ($currentItem['unit'] ?? '')) === '') {
                        $currentItem['unit'] = trim((string) ($matches[2] ?? ''));
                    }
                }

                continue;
            }

            if (
                ($currentItem['netto_price_found'] ?? false)
                && !($currentItem['line_total_found'] ?? false)
                && preg_match('/^' . $this->germanAmountPattern() . '$/u', trim($line)) === 1
            ) {
                $currentItem['line_total'] = $this->parseGermanNumber(trim($line));
                $currentItem['line_total_found'] = $currentItem['line_total'] > 0;
                continue;
            }

            if ($this->isGrobKeywordLine($line)) {
                $currentItem['product_name_capture_complete'] = true;
                continue;
            }

            if (!($currentItem['product_name_capture_complete'] ?? false)) {
                $currentItem['product_name_lines'][] = $line;
                continue;
            }

            $this->appendGrobItemNoteLine($currentItem, $line);
        }

        if (is_array($currentItem)) {
            $items[] = $this->finalizeGrobParsedItem($currentItem);
        }

        return array_values(array_filter($items, function ($item) {
            return is_array($item)
                && (((int) ($item['line_number'] ?? 0)) > 0 || trim((string) ($item['product_code'] ?? '')) !== '');
        }));
    }

    private function finalizeGrobParsedItem(array $item): array
    {
        $productNameLines = array_values(array_filter(array_map(function ($line) {
            return $this->stripGrobProductNameUnitPrefix((string) $line);
        }, $item['product_name_lines'] ?? [])));

        $item['product_name'] = $this->normalizeScannedProductName(
            trim(implode(' ', $productNameLines))
        );
        $item['note'] = implode(' | ', array_values(array_unique(array_filter($item['note_lines'] ?? []))));
        unset($item['product_name_lines']);
        unset($item['note_lines']);
        unset($item['product_name_capture_complete']);

        if (!($item['netto_price_found'] ?? false)) {
            $item['unit_price'] = 0.0;
            $item['line_total'] = ($item['line_total_found'] ?? false) ? (float) ($item['line_total'] ?? 0) : 0.0;
            $item['warnings'][] = 'NettoPreis nije pronadjen u GROB dokumentu.';
        } elseif (!($item['line_total_found'] ?? false)) {
            $item['line_total'] = 0.0;
            $item['warnings'][] = 'Wert za GROB stavku nije pronadjen uz NettoPreis.';
        }

        if (trim((string) ($item['unit'] ?? '')) !== '') {
            $item['unit'] = $this->normalizeScannedUnit((string) $item['unit']);
        }

        return $item;
    }

    private function stripGrobProductNameUnitPrefix(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return trim((string) (preg_replace('/^(?:ST|STK|STUECK|STUCK|STU|PCS|PIECE|KO)\s+(?=\S)/iu', '', $value) ?? $value));
    }

    private function createGrobParsedItemFromLine(string $line): ?array
    {
        $candidate = $this->extractGrobItemStartCandidate($line);

        if (preg_match('/^(\d{1,4})\s+(\d{5,10})\s+(' . $this->germanAmountPattern(true) . ')\s+([A-Z]{1,5})\s*(.*)$/iu', $candidate, $matches) === 1) {
            return $this->initializeGrobParsedItem(
                (int) ($matches[1] ?? 0),
                trim((string) ($matches[2] ?? '')),
                $this->parseGermanNumber((string) ($matches[3] ?? '')),
                trim((string) ($matches[4] ?? '')),
                trim((string) ($matches[5] ?? ''))
            );
        }

        if (preg_match('/^(\d{1,4})\s+(\d{5,10})\s+(' . $this->germanAmountPattern(true) . ')\s*$/iu', $candidate, $matches) === 1) {
            return $this->initializeGrobParsedItem(
                (int) ($matches[1] ?? 0),
                trim((string) ($matches[2] ?? '')),
                $this->parseGermanNumber((string) ($matches[3] ?? '')),
                '',
                ''
            );
        }

        if (preg_match('/^pos(?:ition)?\.?\s*([0-9]+)\b(?:\s+([A-Z0-9.\-\/]+))?/iu', $candidate, $matches) === 1) {
            return $this->initializeGrobParsedItem(
                (int) ($matches[1] ?? 0),
                trim((string) ($matches[2] ?? '')),
                0.0,
                '',
                ''
            );
        }

        return null;
    }

    private function initializeGrobParsedItem(int $lineNumber, string $productCode, float $quantity, string $unit, string $productName): array
    {
        return [
            'line_number' => $lineNumber,
            'product_code' => $productCode,
            'quantity' => $quantity,
            'unit' => $unit,
            'product_name_lines' => $productName !== '' ? [$productName] : [],
            'note_lines' => [],
            'product_name_capture_complete' => false,
            'drawing_reference' => '',
            'material_hint' => '',
            'delivery_deadline' => '',
            'unit_price' => 0.0,
            'line_total' => 0.0,
            'line_total_found' => false,
            'netto_price_found' => false,
            'warnings' => [],
        ];
    }

    private function populateGrobItemFromNettoLine(array &$item, string $line): void
    {
        $amounts = $this->extractGermanAmounts($line);

        if ($amounts !== []) {
            $item['unit_price'] = $amounts[0];
            $item['netto_price_found'] = $item['unit_price'] > 0;
        }

        $lineTotal = $this->extractGrobNettoLineTotal($line);

        if ($lineTotal > 0) {
            $item['line_total'] = $lineTotal;
            $item['line_total_found'] = true;
        }

        if (
            trim((string) ($item['unit'] ?? '')) === ''
            && preg_match('/^nettopreis\b.*?\beur\s*([A-Z]{1,5})\b/iu', $line, $matches) === 1
        ) {
            $item['unit'] = trim((string) ($matches[1] ?? ''));
        }
    }

    private function extractGrobNettoLineTotal(string $line): float
    {
        $normalized = trim((string) (preg_replace('/\s+/u', ' ', $line) ?? $line));

        if ($normalized === '') {
            return 0.0;
        }

        if (
            preg_match(
                '/^nettopreis\b.*?\beur(?:\s*[A-Z]{1,5})?\b\s+1\s+(' . $this->germanAmountPattern() . ')\b/iu',
                $normalized,
                $matches
            ) === 1
        ) {
            return $this->parseGermanNumber((string) ($matches[1] ?? ''));
        }

        if (preg_match_all('/' . $this->germanAmountPattern() . '/u', $normalized, $matches) < 2 || empty($matches[0])) {
            return 0.0;
        }

        return $this->parseGermanNumber((string) end($matches[0]));
    }

    private function prepareGrobPageLinesForParsing(array $page, bool $hasOpenItem): array
    {
        $lines = $this->extractPageLines($page);

        if ($lines === []) {
            return [];
        }

        $attentionMarkerIndex = $this->findGrobAttentionMarkerLineIndex($lines);

        if ($attentionMarkerIndex !== null) {
            $lines = array_slice($lines, 0, $attentionMarkerIndex);
        }

        if ($lines === []) {
            return [];
        }

        $startIndex = 0;

        foreach ($lines as $index => $line) {
            if ($hasOpenItem) {
                if (!$this->isGrobNonItemNoiseLine($line)) {
                    $startIndex = $index;
                    break;
                }

                continue;
            }

            if ($this->createGrobParsedItemFromLine($line) !== null) {
                $startIndex = $index;
                break;
            }
        }

        $lines = array_slice($lines, $startIndex);

        while (!empty($lines) && $this->isGrobNonItemNoiseLine((string) end($lines))) {
            array_pop($lines);
        }

        return array_values($lines);
    }

    private function extractPageLines(array $page): array
    {
        $lines = is_array($page['lines'] ?? null)
            ? $page['lines']
            : $this->splitVisibleTextLines((string) ($page['text'] ?? ''));

        return array_values(array_filter(array_map(function ($line) {
            return trim((string) $line);
        }, $lines)));
    }

    private function findGrobAttentionMarkerLineIndex(array $lines): ?int
    {
        $lineCount = count($lines);

        for ($index = 0; $index < $lineCount; $index++) {
            $line = trim((string) ($lines[$index] ?? ''));

            if ($this->isGrobAttentionMarkerLine($line)) {
                return $index;
            }

            $nextLine = trim((string) ($lines[$index + 1] ?? ''));
            $context = trim($line . ' ' . $nextLine);

            if ($nextLine !== '' && $this->isGrobAttentionMarkerLine($context)) {
                return $index + 1;
            }
        }

        return null;
    }

    private function isGrobAttentionMarkerLine(string $line): bool
    {
        return $this->normalizeKeywordText($line) !== ''
            && str_contains($this->normalizeKeywordText($line), 'achtung')
            && preg_match('/\*{10,}/u', $line) === 1;
    }

    private function isGrobAttachmentPageForParsing(array $lines): bool
    {
        $joined = $this->normalizeKeywordText(implode("\n", array_filter($lines)));

        return $joined !== ''
            && str_contains($joined, 'warenbegleitschein')
            && (str_contains($joined, 'grob-identnr') || str_contains($joined, 'lieferant'));
    }

    private function isGrobNonItemNoiseLine(string $line): bool
    {
        $normalized = $this->normalizeKeywordText($line);

        if ($normalized === '') {
            return true;
        }

        if (preg_match('/^w.+hrung\b/u', $normalized) === 1) {
            return true;
        }

        if (preg_match('/^\d{2,6}\s+[a-z][a-z\s.\-]+$/u', $normalized) === 1) {
            return true;
        }

        return preg_match(
            '/^(?:grob-werke(?:\s+gmbh\s*&\s*co\.\s*kg)?|gmbh\s*&\s*co\.\s*kg|bestellung|bestell-nr\.:|trendy d\.o\.o\.|mehmeda spahe\b|bosnien-herz|seite\s+\d+\s+von\s+\d+|pos\b|beschreibung\b|menge\b|mengeneinheit\b|wahrung\b|preis\b|_{10,}|\*{20,}|liefer\.-nr\.|kunden-nr\.|zahlungsbed\.|sachb\.\/tel\.|ekg:|bitte weisen sie|lieferant\b|grob-identnr\.:|material:|banf:)/iu',
            $normalized
        ) === 1;
    }

    private function isGrobKeywordLine(string $line): bool
    {
        $normalized = $this->normalizeKeywordText($line);

        return preg_match(
            '/^(?:bruttopreis|nettopreis|wert|preiseinheit|preis|pro|lieferdatum|ruesten\/termin abs\.?|vertrag\b|beschichtung\b|gesamtbetrag|nettowert|seite\b|summe\b|mwst\b|achtung\b|attention\b)/iu',
            $normalized
        ) === 1;
    }

    private function appendGrobItemNoteLine(array &$item, string $line): void
    {
        $trimmedLine = trim($line);

        if ($trimmedLine === '' || $this->isGrobNonItemNoiseLine($trimmedLine)) {
            return;
        }

        $item['note_lines'][] = $trimmedLine;
    }

    private function extractGrobItemStartCandidate(string $line): string
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return '';
        }

        if (
            preg_match(
                '/(?:^|_{5,}\s*|\s+)(\d{1,4}\s+\d{5,10}\s+' . $this->germanAmountPattern(true) . '(?:\s+[A-Z]{1,5})?(?:\s+.*)?)$/iu',
                $trimmed,
                $matches
            ) === 1
        ) {
            return trim((string) ($matches[1] ?? $trimmed));
        }

        return $trimmed;
    }

    private function extractKeywordSegment(string $line, string $keyword): ?string
    {
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b.*$/iu', $line, $matches) === 1) {
            return trim((string) ($matches[0] ?? ''));
        }

        return null;
    }

    private function containsGrobKeyword(string $line, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (stripos($line, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractGrobPrefixBeforeKeyword(string $line, string $keyword): string
    {
        $parts = preg_split('/\b' . preg_quote($keyword, '/') . '\b/iu', $line, 2);

        if (!is_array($parts) || count($parts) < 2) {
            return '';
        }

        return trim((string) ($parts[0] ?? ''));
    }

    private function extractGrobMaterialHint(string $line): string
    {
        if (preg_match('/werkstoff\s*:\s*(.+)$/iu', $line, $matches) !== 1) {
            return '';
        }

        $material = trim((string) ($matches[1] ?? ''));
        $material = preg_split('/\b(?:beschichtung|bruttopreis|nettopreis|r[[:alpha:]]+sten\/termin abs\.?|lieferdatum|wert|zeichnung)\b/iu', $material)[0] ?? $material;

        return trim($material);
    }

    private function extractGermanAmounts(string $value, bool $preserveZeroValues = false): array
    {
        if (preg_match_all('/' . $this->germanAmountPattern() . '/u', $value, $matches) < 1) {
            return [];
        }

        $amounts = array_map(function ($amount) {
            return $this->parseGermanNumber((string) $amount);
        }, $matches[0] ?? []);

        if ($preserveZeroValues) {
            return array_values($amounts);
        }

        return array_values(array_filter($amounts, function ($amount) {
            return $amount > 0;
        }));
    }

    private function germanAmountPattern(bool $fractionOptional = false): string
    {
        $fractionPattern = $fractionOptional
            ? '(?:,\h*\d+)?'
            : ',\h*\d+';

        return '-?(?:\d{1,3}(?:\h*[.\h]\h*\d{3})+|\d+)' . $fractionPattern;
    }

    private function extractLastGermanAmountAfterLabels(string $value, array $labels): float
    {
        $amount = 0.0;

        foreach ($labels as $label) {
            $pattern = '/' . preg_quote($label, '/') . '[^0-9-]{0,40}(' . $this->germanAmountPattern() . ')/iu';

            if (preg_match_all($pattern, $value, $matches) < 1 || empty($matches[1])) {
                continue;
            }

            $lastMatch = (string) end($matches[1]);
            $parsed = $this->parseGermanNumber($lastMatch);

            if ($parsed > 0) {
                $amount = $parsed;
            }
        }

        return $amount;
    }

    private function extractVisibleDateFromLine(string $value): string
    {
        if (preg_match('/(\d{1,2}\.\s*\d{1,2}\.\s*\d{2,4}\.?)/u', $value, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function parseGermanNumber(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        $normalized = preg_replace('/[^\d,.\-]/u', '', $value) ?? $value;
        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (substr_count($normalized, '.') > 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return round((float) $normalized, 4);
    }

    private function normalizeScannedUnit(string $unit): string
    {
        $unit = strtoupper(trim($unit));

        if ($unit === '') {
            return '';
        }

        return match ($unit) {
            'ST', 'STK', 'STUECK', 'STUCK', 'STU', 'PCS', 'PIECE' => strtoupper((string) config('ai-order-scan.default_unit', 'KO')),
            default => $unit,
        };
    }

    private function normalizeScannedProductCode(string $value): string
    {
        $parts = $this->extractEmbeddedProductCodeParts($value);

        if (($parts['product_code'] ?? '') !== '') {
            return (string) $parts['product_code'];
        }

        $value = Utf8Sanitizer::clean($value);
        $value = preg_replace('/\s+/u', '', trim($value)) ?? trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d+)(?:[.,]0+)$/u', $value, $matches) === 1) {
            return (string) ($matches[1] ?? '');
        }

        return $value;
    }

    private function normalizeScannedProductName(string $value): string
    {
        $value = Utf8Sanitizer::repairGermanUmlautSpacing(Utf8Sanitizer::clean($value));
        $normalized = trim((string) (preg_replace('/\s+/u', ' ', $value) ?? $value));

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/(?<=[\p{L}\p{N}\/])\s*-\s*(?=[\p{L}\p{N}\/])/u', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?<=[\p{L}\p{N}])\s*\/\s*(?=[\p{L}\p{N}])/u', '/', $normalized) ?? $normalized;
        $normalized = $this->deduplicateRepeatedProductNameSegments($normalized);

        return trim((string) (preg_replace('/\s+/u', ' ', $normalized) ?? $normalized));
    }

    private function deduplicateRepeatedProductNameSegments(string $value): string
    {
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($token) => trim((string) $token) !== ''));

        if (count($tokens) < 2) {
            return trim($value);
        }

        $changed = true;

        while ($changed) {
            $changed = false;
            $tokenCount = count($tokens);

            for ($length = (int) floor($tokenCount / 2); $length >= 1; $length--) {
                for ($offset = 0; $offset + ($length * 2) <= $tokenCount; $offset++) {
                    $left = array_slice($tokens, $offset, $length);
                    $right = array_slice($tokens, $offset + $length, $length);

                    if (!$this->productNameTokenSegmentsMatch($left, $right)) {
                        continue;
                    }

                    array_splice($tokens, $offset + $length, $length);
                    $changed = true;
                    break 2;
                }
            }
        }

        return trim(implode(' ', $tokens));
    }

    private function productNameTokenSegmentsMatch(array $left, array $right): bool
    {
        if ($left === [] || count($left) !== count($right)) {
            return false;
        }

        if (count($left) === 1 && mb_strlen((string) ($left[0] ?? '')) < 4) {
            return false;
        }

        return $this->normalizeProductNameSegmentKey($left) === $this->normalizeProductNameSegmentKey($right);
    }

    private function normalizeProductNameSegmentKey(array $tokens): string
    {
        $value = strtoupper(Str::ascii(implode(' ', array_map('strval', $tokens))));

        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
    }

    private function extractEmbeddedProductCodeParts(string $value): array
    {
        $value = Utf8Sanitizer::clean($value);
        $value = trim((string) (preg_replace('/\s+/u', ' ', $value) ?? $value));

        if ($value === '') {
            return [
                'product_code' => '',
                'quantity' => 0.0,
                'unit' => '',
            ];
        }

        if (preg_match('/^([A-Z0-9.\-\/]+)\s+(' . $this->germanAmountPattern(true) . ')\s+([A-Z]{1,5})$/iu', $value, $matches) === 1) {
            return [
                'product_code' => trim((string) ($matches[1] ?? '')),
                'quantity' => $this->parseGermanNumber((string) ($matches[2] ?? '')),
                'unit' => trim((string) ($matches[3] ?? '')),
            ];
        }

        return [
            'product_code' => '',
            'quantity' => 0.0,
            'unit' => '',
        ];
    }

    private function resolveGrobCustomerName(string $searchableText): string
    {
        $customerName = $this->extractFieldValue($searchableText, [
            '/\b(Trendy\s+d\.o\.o\.)\b/iu',
            '/\b(Trendy\s+doo)\b/iu',
            '/\b(Trendy\s+Germany\s+GmbH)\b/iu',
        ]);

        if ($customerName !== '') {
            return $customerName;
        }

        return trim((string) data_get(config('ai-order-scan.profiles'), 'grob.default_customer_name', ''));
    }

    private function resolveGrobConfidence(array $items, string $documentNumber, string $supplierName): float
    {
        $confidence = 0.82;

        if ($items !== []) {
            $confidence += 0.08;
        }

        if ($documentNumber !== '') {
            $confidence += 0.04;
        }

        if ($supplierName !== '') {
            $confidence += 0.03;
        }

        return min(0.99, $confidence);
    }

    private function resolveTrendyDeConfidence(array $items, string $documentNumber, string $receiverName): float
    {
        $confidence = 0.88;

        if ($items !== []) {
            $confidence += 0.05;
        }

        if ($documentNumber !== '') {
            $confidence += 0.03;
        }

        if ($receiverName !== '') {
            $confidence += 0.02;
        }

        return min(0.99, $confidence);
    }

    private function extractCurrency(string $searchableText): string
    {
        if (preg_match('/\b(EUR|BAM|KM|USD|CHF|GBP)\b/u', $searchableText, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function extractFieldValue(string $text, array $patterns): string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return Utf8Sanitizer::clean(trim((string) ($matches[1] ?? '')));
            }
        }

        return '';
    }

    private function extractTrendyDeDocumentNumber(string $searchableText, string $fileName): string
    {
        foreach ([$searchableText, $fileName] as $source) {
            if (preg_match('/Bestellung[\s_:-]*([0-9]{2}-[0-9]{3}-[0-9]+)/i', $source, $matches) === 1) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }

    private function extractProfileFieldValue(string $searchableText, string $fieldLabel): string
    {
        $pattern = '/' . preg_quote($fieldLabel, '/') . '\s*:?\s*([^\r\n]+)/i';

        if (preg_match($pattern, $searchableText, $matches) === 1) {
            return $this->normalizeProfileWhitespace((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function extractProfileSectionText(string $searchableText, string $startPattern, array $endPatterns): string
    {
        if (preg_match($startPattern, $searchableText, $startMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        $startOffset = (int) ($startMatch[0][1] ?? 0);
        $section = substr($searchableText, $startOffset);
        $endOffset = strlen($section);

        foreach ($endPatterns as $pattern) {
            if (preg_match($pattern, $section, $endMatch, PREG_OFFSET_CAPTURE) === 1) {
                $candidateOffset = (int) ($endMatch[0][1] ?? $endOffset);

                if ($candidateOffset > 0 && $candidateOffset < $endOffset) {
                    $endOffset = $candidateOffset;
                }
            }
        }

        $section = substr($section, 0, $endOffset);
        $section = preg_replace($startPattern, '', $section, 1) ?? $section;

        return $this->normalizeProfileWhitespace($section);
    }

    private function normalizeProfileWhitespace(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = preg_split('/\n+/u', $value) ?: [];
        $lines = array_values(array_filter(array_map(function ($line) {
            $line = preg_replace('/\s+/', ' ', trim((string) $line)) ?? trim((string) $line);

            return trim((string) $line);
        }, $lines)));

        return implode(' | ', $lines);
    }

    private function splitVisibleTextLines(string $value): array
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = preg_split('/\n+/u', $value) ?: [];

        return array_values(array_filter(array_map(function ($line) {
            $line = trim((string) (preg_replace('/\s+/u', ' ', (string) $line) ?? $line));

            return $line;
        }, $lines)));
    }

    private function appendItemNote(string $existingNote, string $extraNote): string
    {
        $notes = [];

        foreach ([trim($existingNote), trim($extraNote)] as $note) {
            if ($note === '') {
                continue;
            }

            $notes[$note] = $note;
        }

        return implode(' | ', array_values($notes));
    }

    private function normalizeKeywordText(string $value): string
    {
        return Str::lower(trim(Str::ascii(Utf8Sanitizer::clean($value))));
    }
}
