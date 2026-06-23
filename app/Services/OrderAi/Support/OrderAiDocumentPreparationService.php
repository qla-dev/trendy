<?php

namespace App\Services\OrderAi\Support;

use App\Support\Utf8Sanitizer;
use Illuminate\Support\Str;

class OrderAiDocumentPreparationService
{
    public const GROB_PAGE_LIMIT_REASON = 'GROB obrada stavki je ograničena do ACHTUNG reda.';
    public const GROB_ATTACHMENT_PAGE_LIMIT_REASON = 'GROB obrada stavki je ograničena prije pratećih Warenbegleitschein stranica.';

    public function prepareDocument(
        string $documentProfile,
        string $fileName,
        ?string $mimeType,
        string $bytes
    ): array {
        $digitalExtraction = app(OrderAiDigitalPdfExtractor::class)->extract($bytes, $mimeType, $fileName, $documentProfile);
        $pdfDetection = app(OrderAiPdfTypeDetector::class)->detect($digitalExtraction);
        $pages = is_array($digitalExtraction['pages'] ?? null) ? $digitalExtraction['pages'] : [];
        $fullText = Utf8Sanitizer::clean(trim((string) ($digitalExtraction['text'] ?? '')));
        $sourcePageCount = max(
            0,
            (int) ($digitalExtraction['source_page_count'] ?? 0),
            (int) ($digitalExtraction['page_count'] ?? 0),
            count($pages)
        );
        $processedPages = $pages;
        $searchableText = $fullText;
        $effectivePageCount = $sourcePageCount;
        $pageLimitReason = '';
        $providerInputMode = '';
        $providerInputText = '';

        if ($this->shouldLimitGrobPdf($documentProfile, $mimeType, $fileName, $bytes)) {
            $grobPreparation = $this->prepareGrobPages($pages, $fullText, $sourcePageCount);
            $processedPages = $grobPreparation['processed_pages'];
            $searchableText = $grobPreparation['searchable_text'];
            $effectivePageCount = $grobPreparation['effective_page_count'];
            $pageLimitReason = $grobPreparation['page_processing_limit_reason'];

            if (
                $pageLimitReason === ''
                && (bool) ($digitalExtraction['truncated_at_attention_marker'] ?? false)
            ) {
                $pageLimitReason = self::GROB_PAGE_LIMIT_REASON;
            }
        }

        if ($this->shouldUseStructuredTextForProvider(
            (string) config('ai-order-scan.digital_pdf.provider_input_mode', 'auto'),
            (string) ($pdfDetection['type'] ?? ''),
            $searchableText
        )) {
            $providerInputMode = 'text';
            $providerInputText = $this->buildPreparedPdfInputText(
                $documentProfile,
                $processedPages,
                $sourcePageCount,
                $effectivePageCount,
                $pageLimitReason,
                $pdfDetection
            );
        }

        return [
            'pages' => $pages,
            'processed_pages' => $processedPages,
            'full_text' => $fullText,
            'searchable_text' => $searchableText,
            'source_page_count' => $sourcePageCount,
            'effective_page_count' => $effectivePageCount > 0 ? $effectivePageCount : $sourcePageCount,
            'page_processing_limit_reason' => $pageLimitReason,
            'provider_input_mode' => $providerInputMode,
            'provider_input_text' => $providerInputText,
            'pdf_type' => (string) ($pdfDetection['type'] ?? ''),
            'extraction_method' => (string) ($pdfDetection['method'] ?? 'ocr'),
            'extraction_confidence' => (float) ($pdfDetection['confidence'] ?? 0),
            'extraction_reason' => (string) ($pdfDetection['reason'] ?? ''),
            'extraction_duration_ms' => (int) ($digitalExtraction['duration_ms'] ?? 0),
            'coordinates_available' => (bool) ($digitalExtraction['coordinates_available'] ?? false),
            'raw_extracted_text' => $fullText,
            'digital_extraction' => [
                'source' => (string) ($digitalExtraction['source'] ?? ''),
                'page_count' => $sourcePageCount,
                'meaningful_text_pages' => (int) ($digitalExtraction['meaningful_text_pages'] ?? 0),
                'text_character_count' => (int) ($digitalExtraction['text_character_count'] ?? 0),
                'table_row_count' => (int) ($digitalExtraction['table_row_count'] ?? 0),
                'coordinates_available' => (bool) ($digitalExtraction['coordinates_available'] ?? false),
                'pages' => $processedPages,
            ],
        ];
    }

    private function prepareGrobPages(array $pages, string $fullText, int $sourcePageCount): array
    {
        if ($pages === []) {
            return [
                'processed_pages' => [],
                'searchable_text' => $this->truncateFallbackAtAttentionMarker($fullText),
                'effective_page_count' => $sourcePageCount,
                'page_processing_limit_reason' => '',
            ];
        }

        $processedPages = [];
        $markerPageNumber = 0;
        $pageLimitReason = '';

        foreach ($pages as $page) {
            $pageLines = array_values(array_filter(array_map(function ($line) {
                return Utf8Sanitizer::clean(trim((string) $line));
            }, is_array($page['lines'] ?? null) ? $page['lines'] : [])));

            if ($pageLines !== [] && $processedPages !== [] && $this->isGrobAttachmentPage($pageLines)) {
                $markerPageNumber = max(0, ((int) ($page['page_number'] ?? (count($processedPages) + 1))) - 1);
                $pageLimitReason = self::GROB_ATTACHMENT_PAGE_LIMIT_REASON;
                break;
            }

            $markerLineIndex = $this->findAttentionMarkerLineIndex($pageLines);

            if ($markerLineIndex !== null) {
                $markerPageNumber = (int) ($page['page_number'] ?? (count($processedPages) + 1));
                $pageLines = array_slice($pageLines, 0, $markerLineIndex);
                $pageLimitReason = self::GROB_PAGE_LIMIT_REASON;
            }

            $pageText = Utf8Sanitizer::clean(trim(implode("\n", $pageLines)));

            $processedPages[] = [
                'page' => (int) ($page['page'] ?? $page['page_number'] ?? (count($processedPages) + 1)),
                'page_number' => (int) ($page['page_number'] ?? $page['page'] ?? (count($processedPages) + 1)),
                'lines' => $pageLines,
                'text' => $pageText,
                'items' => $this->filterPageItemsBeforeMarker((array) ($page['items'] ?? [])),
                'text_elements' => is_array($page['text_elements'] ?? null) ? $page['text_elements'] : [],
                'coordinates_available' => (bool) ($page['coordinates_available'] ?? false),
                'table_candidates' => is_array($page['table_candidates'] ?? null) ? $page['table_candidates'] : [],
            ];

            if ($markerLineIndex !== null) {
                break;
            }
        }

        $effectivePageCount = $markerPageNumber > 0 ? $markerPageNumber : count($processedPages);
        $searchableText = trim(implode("\n\n", array_values(array_filter(array_map(function (array $page) {
            return trim((string) ($page['text'] ?? ''));
        }, $processedPages)))));

        return [
            'processed_pages' => $processedPages,
            'searchable_text' => Utf8Sanitizer::clean($searchableText),
            'effective_page_count' => $effectivePageCount,
            'page_processing_limit_reason' => $pageLimitReason,
        ];
    }

    private function buildPreparedPdfInputText(
        string $documentProfile,
        array $pages,
        int $sourcePageCount,
        int $effectivePageCount,
        string $pageLimitReason,
        array $pdfDetection
    ): string {
        $payload = [
            'document_profile' => trim($documentProfile),
            'pdf_type' => (string) ($pdfDetection['type'] ?? ''),
            'extraction_method' => (string) ($pdfDetection['method'] ?? ''),
            'source_page_count' => $sourcePageCount,
            'effective_page_count' => $effectivePageCount > 0 ? $effectivePageCount : $sourcePageCount,
            'page_processing_limit_reason' => $pageLimitReason !== '' ? $pageLimitReason : null,
            'pages' => array_values(array_filter(array_map(function (array $page) {
                $pageNumber = max(1, (int) ($page['page'] ?? $page['page_number'] ?? 0));
                $pageText = trim((string) ($page['text'] ?? ''));
                $pageItems = is_array($page['items'] ?? null) ? $page['items'] : [];

                if ($pageText === '' && $pageItems === []) {
                    return null;
                }

                return [
                    'page' => $pageNumber,
                    'text' => $pageText,
                    'items' => array_values(array_filter(array_map(function ($item) {
                        if (!is_array($item)) {
                            return null;
                        }

                        return [
                            'row_number' => (int) ($item['row_number'] ?? 0),
                            'y' => (float) ($item['y'] ?? 0),
                            'text' => trim((string) ($item['text'] ?? '')),
                            'cells' => array_values(array_filter(array_map(function ($cell) {
                                if (!is_array($cell)) {
                                    return null;
                                }

                                return [
                                    'x' => round((float) ($cell['x'] ?? 0), 2),
                                    'text' => trim((string) ($cell['text'] ?? '')),
                                ];
                            }, is_array($item['cells'] ?? null) ? $item['cells'] : []))),
                        ];
                    }, $pageItems))),
                ];
            }, $pages))),
        ];

        return Utf8Sanitizer::clean(trim(implode("\n\n", array_filter([
            'Structured text extracted from a digital PDF follows as JSON.',
            'Use NETTOPREIS over BRUTTOPREIS when both values are visible, stop item parsing after footer markers such as ACHTUNG, and keep line totals exactly as shown.',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]))));
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

    private function isGrobAttachmentPage(array $lines): bool
    {
        $joined = Str::lower(implode("\n", array_filter($lines)));

        if ($joined === '') {
            return false;
        }

        return str_contains($joined, 'warenbegleitschein')
            && (str_contains($joined, 'grob-identnr') || str_contains($joined, 'lieferant'));
    }

    private function truncateFallbackAtAttentionMarker(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/\*{10,}.*?ACHTUNG.*$/siu', $normalized, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $normalized;
        }

        $offset = (int) ($matches[0][1] ?? 0);

        return Utf8Sanitizer::clean(trim(mb_substr($normalized, 0, $offset)));
    }

    private function shouldLimitGrobPdf(string $documentProfile, ?string $mimeType, string $fileName, string $bytes): bool
    {
        return trim($documentProfile) === 'grob'
            && $this->looksLikePdf($mimeType, $fileName, $bytes);
    }

    private function shouldUseStructuredTextForProvider(
        string $providerInputStrategy,
        string $pdfType,
        string $searchableText
    ): bool {
        $providerInputStrategy = trim($providerInputStrategy);
        $pdfType = trim($pdfType);

        if ($providerInputStrategy === 'legacy_raw') {
            return false;
        }

        if ($providerInputStrategy === 'text_only') {
            return $searchableText !== '';
        }

        if ($pdfType === 'digital') {
            return $searchableText !== '';
        }

        if ($pdfType === 'hybrid') {
            return $searchableText !== ''
                && (bool) config('ai-order-scan.digital_pdf.use_text_for_hybrid', false);
        }

        return false;
    }

    private function filterPageItemsBeforeMarker(array $items): array
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

    private function looksLikePdf(?string $mimeType, string $fileName, string $bytes): bool
    {
        $normalizedMime = Str::lower(trim((string) $mimeType));
        $normalizedName = Str::lower(trim($fileName));

        if ($normalizedMime !== '' && str_contains($normalizedMime, 'pdf')) {
            return true;
        }

        if ($normalizedName !== '' && Str::endsWith($normalizedName, '.pdf')) {
            return true;
        }

        return str_starts_with($bytes, '%PDF-');
    }
}
