<?php

namespace App\Services\OrderAi\Support;

use App\Support\Utf8Sanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;

class OrderAiDigitalPdfExtractor
{
    public function extract(
        string $bytes,
        ?string $mimeType = null,
        ?string $fileName = null,
        ?string $documentProfile = null
    ): array
    {
        $startedAt = microtime(true);

        if (!$this->looksLikePdf($bytes, $mimeType, $fileName)) {
            return $this->emptyResult((int) round((microtime(true) - $startedAt) * 1000), false);
        }

        try {
            $document = $this->parseWithSmalot($bytes, $fileName, $mimeType, $documentProfile);
            $documentPages = $document->getPages();
            $sourcePageCount = is_countable($documentPages) ? count($documentPages) : 0;
            $pages = [];
            $truncatedAtAttentionMarker = false;

            foreach ($documentPages as $pageIndex => $page) {
                if (!$page instanceof Page) {
                    continue;
                }

                $pagePayload = $this->extractPage($page, $pageIndex + 1);

                if ($pagePayload === null) {
                    continue;
                }

                if ($this->shouldShortCircuitGrobAtAttentionMarker($documentProfile, $pagePayload)) {
                    $pages[] = $this->truncateGrobPageAtAttentionMarker($pagePayload);
                    $truncatedAtAttentionMarker = true;
                    break;
                }

                $pages[] = $pagePayload;
            }

            if ($pages !== []) {
                return $this->finalizeResult(
                    $pages,
                    'smalot_pdfparser',
                    (int) round((microtime(true) - $startedAt) * 1000),
                    $sourcePageCount > 0 ? $sourcePageCount : null,
                    $truncatedAtAttentionMarker
                );
            }

            Log::warning('Order AI Smalot PDF extraction produced no usable text pages; using legacy stream fallback.', [
                'file_name' => (string) $fileName,
                'mime_type' => (string) $mimeType,
                'document_profile' => (string) $documentProfile,
                'source_page_count' => $sourcePageCount,
                'page_object_count' => is_countable($documentPages) ? count($documentPages) : null,
                'hint' => 'Smalot loaded the PDF, but no page text/table rows could be extracted.',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Order AI Smalot PDF extraction failed; using legacy stream fallback.', [
                'file_name' => (string) $fileName,
                'mime_type' => (string) $mimeType,
                'document_profile' => (string) $documentProfile,
                'parser_class_available' => class_exists(Parser::class),
                'config_class_available' => class_exists(PdfParserConfig::class),
                'message' => Utf8Sanitizer::cleanExceptionMessage($exception),
                'hint' => $this->smalotFailureHint($exception),
            ]);
        }

        $fallbackPages = $this->buildPagesFromFallback(
            app(OrderAiDocumentTextExtractor::class)->extract($bytes, $mimeType, $fileName)
        );
        $fallbackSourcePageCount = count($fallbackPages);
        $fallbackTruncatedAtAttentionMarker = false;

        if (trim((string) $documentProfile) === 'grob') {
            $fallbackPages = $this->truncateGrobPagesCollectionAtAttentionMarker($fallbackPages);
            $fallbackTruncatedAtAttentionMarker = count($fallbackPages) < $fallbackSourcePageCount;
        }

        if ($fallbackPages === []) {
            Log::warning('Order AI legacy PDF fallback produced no text pages.', [
                'file_name' => (string) $fileName,
                'mime_type' => (string) $mimeType,
                'document_profile' => (string) $documentProfile,
                'hint' => 'Both Smalot and the legacy stream extractor failed to produce readable text.',
            ]);

            return $this->emptyResult((int) round((microtime(true) - $startedAt) * 1000), true);
        }

        Log::info('Order AI legacy PDF fallback produced text pages.', [
            'file_name' => (string) $fileName,
            'mime_type' => (string) $mimeType,
            'document_profile' => (string) $documentProfile,
            'page_count' => count($fallbackPages),
            'text_character_count' => mb_strlen(trim(implode("\n\n", array_map(
                fn (array $page) => (string) ($page['text'] ?? ''),
                $fallbackPages
            )))),
            'hint' => 'Rules parser may be less reliable on legacy fallback text because PDF coordinates/table rows are not available.',
        ]);

        return $this->finalizeResult(
            $fallbackPages,
            'legacy_stream_fallback',
            (int) round((microtime(true) - $startedAt) * 1000),
            $fallbackSourcePageCount > 0 ? $fallbackSourcePageCount : null,
            $fallbackTruncatedAtAttentionMarker
        );
    }

    private function parseWithSmalot(string $bytes, ?string $fileName, ?string $mimeType, ?string $documentProfile): mixed
    {
        if (!class_exists(Parser::class)) {
            throw new \RuntimeException('Class "' . Parser::class . '" not found');
        }

        if (!class_exists(PdfParserConfig::class)) {
            Log::warning('Order AI Smalot Config class is missing; using default parser constructor.', [
                'file_name' => (string) $fileName,
                'mime_type' => (string) $mimeType,
                'document_profile' => (string) $documentProfile,
            ]);

            return (new Parser())->parseContent($bytes);
        }

        $config = new PdfParserConfig();

        if (method_exists($config, 'setIgnoreEncryption')) {
            $config->setIgnoreEncryption(true);
        }

        if (method_exists($config, 'setDataTmFontInfoHasToBeIncluded')) {
            $config->setDataTmFontInfoHasToBeIncluded(false);
        }

        return (new Parser([], $config))->parseContent($bytes);
    }

    private function smalotFailureHint(\Throwable $exception): string
    {
        $message = Utf8Sanitizer::cleanExceptionMessage($exception);

        if (str_contains($message, 'Smalot\\PdfParser\\Config')) {
            return 'The installed smalot/pdfparser package is missing the Config class. Run composer install on the server from composer.lock, or update vendor.';
        }

        if (str_contains($message, 'Smalot\\PdfParser\\Parser')) {
            return 'The smalot/pdfparser package is not autoloadable on the server. Run composer install and clear optimized autoload/cache.';
        }

        return 'Smalot threw an exception before text/table extraction completed. Check the exception message and server Composer vendor state.';
    }

    private function extractPage(Page $page, int $pageNumber): ?array
    {
        $pageText = Utf8Sanitizer::clean(trim(str_replace("\r", '', $page->getText())));
        $elements = $this->extractTextElements($page);
        $rows = $this->buildRows($elements);
        $lines = $rows !== []
            ? array_values(array_filter(array_map(fn (array $row) => trim((string) ($row['text'] ?? '')), $rows)))
            : $this->splitPageTextIntoLines($pageText);

        if ($pageText === '' && $lines !== []) {
            $pageText = implode("\n", $lines);
        }

        if ($pageText === '' && $rows === []) {
            return null;
        }

        $pageItems = array_values(array_filter(array_map(function (array $row, int $index) {
            $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];

            if ($cells === [] && trim((string) ($row['text'] ?? '')) === '') {
                return null;
            }

            return [
                'row_number' => $index + 1,
                'y' => (float) ($row['y'] ?? 0),
                'text' => trim((string) ($row['text'] ?? '')),
                'cells' => $cells,
            ];
        }, $rows, array_keys($rows))));

        return [
            'page' => $pageNumber,
            'page_number' => $pageNumber,
            'text' => $pageText,
            'lines' => $lines,
            'items' => $pageItems,
            'text_elements' => $elements,
            'coordinates_available' => $elements !== [],
            'table_candidates' => $pageItems,
        ];
    }

    private function extractTextElements(Page $page): array
    {
        try {
            $dataTm = $page->getDataTm();
        } catch (\Throwable $exception) {
            return [];
        }

        $elements = [];

        foreach ($dataTm as $entry) {
            $matrix = is_array($entry[0] ?? null) ? $entry[0] : [];
            $text = Utf8Sanitizer::clean(trim((string) ($entry[1] ?? '')));

            if ($text === '' || count($matrix) < 6) {
                continue;
            }

            $elements[] = [
                'x' => round((float) ($matrix[4] ?? 0), 2),
                'y' => round((float) ($matrix[5] ?? 0), 2),
                'text' => $text,
            ];
        }

        usort($elements, function (array $left, array $right) {
            $yComparison = (float) ($right['y'] ?? 0) <=> (float) ($left['y'] ?? 0);

            if ($yComparison !== 0) {
                return $yComparison;
            }

            return (float) ($left['x'] ?? 0) <=> (float) ($right['x'] ?? 0);
        });

        return array_values($elements);
    }

    private function buildRows(array $elements): array
    {
        if ($elements === []) {
            return [];
        }

        $rows = [];
        $currentRow = [];
        $currentY = null;
        $yTolerance = max(0.5, (float) config('ai-order-scan.digital_pdf.row_y_tolerance', 2.5));

        foreach ($elements as $element) {
            $elementY = (float) ($element['y'] ?? 0);

            if ($currentY === null || abs($currentY - $elementY) <= $yTolerance) {
                $currentRow[] = $element;
                $currentY = $currentY ?? $elementY;
                continue;
            }

            $rows[] = $this->normalizeRow($currentRow, (float) $currentY);
            $currentRow = [$element];
            $currentY = $elementY;
        }

        if ($currentRow !== []) {
            $rows[] = $this->normalizeRow($currentRow, (float) $currentY);
        }

        return array_values(array_filter($rows));
    }

    private function normalizeRow(array $rowElements, float $rowY): ?array
    {
        if ($rowElements === []) {
            return null;
        }

        usort($rowElements, fn (array $left, array $right) => (float) ($left['x'] ?? 0) <=> (float) ($right['x'] ?? 0));
        $cells = [];
        $text = '';

        foreach ($rowElements as $element) {
            $fragment = trim((string) ($element['text'] ?? ''));

            if ($fragment === '') {
                continue;
            }

            $cells[] = [
                'x' => round((float) ($element['x'] ?? 0), 2),
                'y' => round((float) ($element['y'] ?? 0), 2),
                'text' => $fragment,
            ];
            $text = trim($text === '' ? $fragment : ($text . ' ' . $fragment));
        }

        if ($text === '') {
            return null;
        }

        return [
            'y' => round($rowY, 2),
            'text' => Utf8Sanitizer::clean($text),
            'cells' => $cells,
        ];
    }

    private function buildPagesFromFallback(array $fallback): array
    {
        $pages = [];

        foreach ((array) ($fallback['pages'] ?? []) as $index => $page) {
            $pageText = Utf8Sanitizer::clean(trim((string) ($page['text'] ?? '')));
            $lines = array_values(array_filter(array_map(function ($line) {
                return Utf8Sanitizer::clean(trim((string) $line));
            }, is_array($page['lines'] ?? null) ? $page['lines'] : [])));

            if ($pageText === '' && $lines === []) {
                continue;
            }

            if ($pageText === '' && $lines !== []) {
                $pageText = implode("\n", $lines);
            }

            $pageNumber = max(1, (int) ($page['page_number'] ?? ($index + 1)));
            $rows = array_values(array_map(function ($line, int $rowIndex) {
                return [
                    'row_number' => $rowIndex + 1,
                    'y' => 0.0,
                    'text' => $line,
                    'cells' => [[
                        'x' => 0.0,
                        'y' => 0.0,
                        'text' => $line,
                    ]],
                ];
            }, $lines, array_keys($lines)));

            $pages[] = [
                'page' => $pageNumber,
                'page_number' => $pageNumber,
                'text' => $pageText,
                'lines' => $lines,
                'items' => $rows,
                'text_elements' => [],
                'coordinates_available' => false,
                'table_candidates' => $rows,
            ];
        }

        return $pages;
    }

    private function finalizeResult(
        array $pages,
        string $source,
        int $durationMs,
        ?int $sourcePageCount = null,
        bool $truncatedAtAttentionMarker = false
    ): array
    {
        $fullText = trim(implode("\n\n", array_values(array_filter(array_map(
            fn (array $page) => trim((string) ($page['text'] ?? '')),
            $pages
        )))));
        $meaningfulTextPages = count(array_filter($pages, function (array $page) {
            return $this->looksLikeMeaningfulText((string) ($page['text'] ?? ''));
        }));
        $textCharacterCount = mb_strlen($fullText);
        $tableRowCount = array_sum(array_map(function (array $page) {
            return count(is_array($page['items'] ?? null) ? $page['items'] : []);
        }, $pages));

        return [
            'is_pdf' => true,
            'source' => $source,
            'page_count' => max(0, $sourcePageCount ?? count($pages)),
            'source_page_count' => max(0, $sourcePageCount ?? count($pages)),
            'detected_page_count' => count($pages),
            'truncated_at_attention_marker' => $truncatedAtAttentionMarker,
            'pages' => array_values($pages),
            'text' => $fullText,
            'text_character_count' => $textCharacterCount,
            'meaningful_text_pages' => $meaningfulTextPages,
            'coordinates_available' => (bool) array_reduce($pages, function (bool $carry, array $page) {
                return $carry || !empty($page['coordinates_available']);
            }, false),
            'table_row_count' => $tableRowCount,
            'duration_ms' => $durationMs,
        ];
    }

    private function emptyResult(int $durationMs, bool $isPdf): array
    {
        return [
            'is_pdf' => $isPdf,
            'source' => '',
            'page_count' => 0,
            'pages' => [],
            'text' => '',
            'text_character_count' => 0,
            'meaningful_text_pages' => 0,
            'coordinates_available' => false,
            'table_row_count' => 0,
            'duration_ms' => $durationMs,
        ];
    }

    private function splitPageTextIntoLines(string $pageText): array
    {
        return array_values(array_filter(array_map(function ($line) {
            return Utf8Sanitizer::clean(trim((string) $line));
        }, preg_split('/\n+/u', $pageText) ?: [])));
    }

    private function shouldShortCircuitGrobAtAttentionMarker(?string $documentProfile, array $pagePayload): bool
    {
        if (trim((string) $documentProfile) !== 'grob') {
            return false;
        }

        return $this->findAttentionMarkerLineIndex(
            is_array($pagePayload['lines'] ?? null) ? $pagePayload['lines'] : []
        ) !== null;
    }

    private function truncateGrobPageAtAttentionMarker(array $pagePayload): array
    {
        $lines = is_array($pagePayload['lines'] ?? null) ? $pagePayload['lines'] : [];
        $markerLineIndex = $this->findAttentionMarkerLineIndex($lines);

        if ($markerLineIndex === null) {
            return $pagePayload;
        }

        $truncatedLines = array_values(array_slice($lines, 0, $markerLineIndex));
        $pagePayload['lines'] = $truncatedLines;
        $pagePayload['text'] = Utf8Sanitizer::clean(trim(implode("\n", $truncatedLines)));
        $pagePayload['items'] = $this->filterPageItemsBeforeAttentionMarker(
            is_array($pagePayload['items'] ?? null) ? $pagePayload['items'] : []
        );
        $pagePayload['table_candidates'] = $pagePayload['items'];
        $pagePayload['text_elements'] = $this->filterTextElementsBeforeAttentionMarker(
            is_array($pagePayload['text_elements'] ?? null) ? $pagePayload['text_elements'] : [],
            $pagePayload['items']
        );
        $pagePayload['coordinates_available'] = !empty($pagePayload['text_elements']);

        return $pagePayload;
    }

    private function findAttentionMarkerLineIndex(array $lines): ?int
    {
        $lineCount = count($lines);

        for ($index = 0; $index < $lineCount; $index++) {
            $line = trim((string) ($lines[$index] ?? ''));

            if ($line !== '' && stripos($line, 'ACHTUNG') !== false && preg_match('/\*{10,}/u', $line) === 1) {
                return $index;
            }

            $nextLine = trim((string) ($lines[$index + 1] ?? ''));
            $context = trim($line . ' ' . $nextLine);

            if ($nextLine !== '' && stripos($context, 'ACHTUNG') !== false && preg_match('/\*{10,}/u', $context) === 1) {
                return $index + 1;
            }
        }

        return null;
    }

    private function filterPageItemsBeforeAttentionMarker(array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));

            if ($text !== '' && stripos($text, 'ACHTUNG') !== false) {
                break;
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    private function filterTextElementsBeforeAttentionMarker(array $elements, array $visibleItems): array
    {
        if ($elements === [] || $visibleItems === []) {
            return $elements;
        }

        $minimumVisibleY = null;

        foreach ($visibleItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemY = (float) ($item['y'] ?? 0);

            if ($itemY <= 0) {
                continue;
            }

            if ($minimumVisibleY === null || $itemY < $minimumVisibleY) {
                $minimumVisibleY = $itemY;
            }
        }

        if ($minimumVisibleY === null) {
            return $elements;
        }

        return array_values(array_filter($elements, function (array $element) use ($minimumVisibleY) {
            return (float) ($element['y'] ?? 0) >= ($minimumVisibleY - 1.0);
        }));
    }

    private function truncateGrobPagesCollectionAtAttentionMarker(array $pages): array
    {
        $truncatedPages = [];

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $pageLines = is_array($page['lines'] ?? null) ? $page['lines'] : [];
            $markerLineIndex = $this->findAttentionMarkerLineIndex($pageLines);

            if ($markerLineIndex !== null) {
                $truncatedPages[] = $this->truncateGrobPageAtAttentionMarker($page);
                break;
            }

            $truncatedPages[] = $page;
        }

        return $truncatedPages;
    }

    private function looksLikeMeaningfulText(string $value): bool
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return false;
        }

        $alphaNumericLength = mb_strlen((string) preg_replace('/[^\pL\pN]+/u', '', $normalized));

        return $alphaNumericLength >= max(10, (int) config('ai-order-scan.digital_pdf.min_meaningful_page_chars', 30));
    }

    private function looksLikePdf(string $bytes, ?string $mimeType, ?string $fileName): bool
    {
        $normalizedMime = Str::lower(trim((string) $mimeType));
        $normalizedName = Str::lower(trim((string) $fileName));

        if ($normalizedMime !== '' && str_contains($normalizedMime, 'pdf')) {
            return true;
        }

        if ($normalizedName !== '' && Str::endsWith($normalizedName, '.pdf')) {
            return true;
        }

        return str_starts_with($bytes, '%PDF-');
    }
}
