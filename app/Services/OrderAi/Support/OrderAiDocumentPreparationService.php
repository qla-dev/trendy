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
        $extracted = app(OrderAiDocumentTextExtractor::class)->extract($bytes, $mimeType, $fileName);
        $pages = is_array($extracted['pages'] ?? null) ? $extracted['pages'] : [];
        $fullText = Utf8Sanitizer::clean(trim((string) ($extracted['text'] ?? '')));
        $sourcePageCount = max(
            0,
            (int) ($extracted['page_count'] ?? 0),
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

            if ($searchableText !== '') {
                $providerInputMode = 'text';
                $providerInputText = $this->buildPreparedGrobInputText(
                    $processedPages,
                    $sourcePageCount,
                    $effectivePageCount,
                    $pageLimitReason
                );
            }
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
                'page_number' => (int) ($page['page_number'] ?? (count($processedPages) + 1)),
                'lines' => $pageLines,
                'text' => $pageText,
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

    private function buildPreparedGrobInputText(
        array $pages,
        int $sourcePageCount,
        int $effectivePageCount,
        string $pageLimitReason
    ): string {
        $headerLines = [
            'Visible text extracted from a GROB-WERKE PDF follows below.',
            'Only the pages that belong to the order table before the ACHTUNG separator are included.',
        ];

        if ($sourcePageCount > 0) {
            $headerLines[] = 'Original PDF pages: ' . $sourcePageCount . '.';
        }

        if ($effectivePageCount > 0) {
            $headerLines[] = 'Pages included for extraction: ' . $effectivePageCount . '.';
        }

        if ($pageLimitReason !== '') {
            $headerLines[] = $pageLimitReason;
        }

        $body = implode("\n\n", array_values(array_filter(array_map(function (array $page) {
            $pageNumber = max(1, (int) ($page['page_number'] ?? 0));
            $pageText = trim((string) ($page['text'] ?? ''));

            if ($pageText === '') {
                return '';
            }

            return '[Page ' . $pageNumber . ']' . "\n" . $pageText;
        }, $pages))));

        return Utf8Sanitizer::clean(trim(implode("\n\n", array_filter([
            implode("\n", $headerLines),
            $body,
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
