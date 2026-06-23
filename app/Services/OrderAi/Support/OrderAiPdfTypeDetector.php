<?php

namespace App\Services\OrderAi\Support;

class OrderAiPdfTypeDetector
{
    public function detect(array $digitalExtraction): array
    {
        $detectedPageCount = max(0, (int) ($digitalExtraction['detected_page_count'] ?? 0));
        $pageCount = $detectedPageCount > 0
            ? $detectedPageCount
            : max(0, (int) ($digitalExtraction['page_count'] ?? 0));
        $meaningfulTextPages = max(0, (int) ($digitalExtraction['meaningful_text_pages'] ?? 0));
        $textCharacterCount = max(0, (int) ($digitalExtraction['text_character_count'] ?? 0));
        $meaningfulRatio = $pageCount > 0 ? ($meaningfulTextPages / $pageCount) : 0.0;
        $minimumTextChars = max(40, (int) config('ai-order-scan.digital_pdf.min_meaningful_document_chars', 80));
        $digitalThreshold = max(0.25, min(1.0, (float) config('ai-order-scan.digital_pdf.digital_page_ratio', 0.8)));
        $hybridThreshold = max(0.05, min($digitalThreshold, (float) config('ai-order-scan.digital_pdf.hybrid_page_ratio', 0.2)));

        $type = 'scanned';
        $method = 'ocr';
        $confidence = 0.2;
        $reason = 'No meaningful embedded PDF text was detected.';

        if (!$digitalExtraction['is_pdf']) {
            return [
                'type' => 'non_pdf',
                'method' => 'ocr',
                'confidence' => 0.0,
                'reason' => 'The uploaded file is not a PDF.',
                'page_count' => 0,
                'meaningful_text_pages' => 0,
                'text_character_count' => 0,
            ];
        }

        if ($pageCount > 0 && $meaningfulTextPages === $pageCount && $textCharacterCount >= $minimumTextChars) {
            $type = 'digital';
            $method = 'digital';
            $confidence = min(1.0, 0.7 + min(0.29, $meaningfulRatio * 0.2) + min(0.09, $textCharacterCount / 4000));
            $reason = 'Meaningful embedded text was detected on every PDF page.';
        } elseif ($pageCount > 0 && $meaningfulRatio >= $digitalThreshold && $textCharacterCount >= $minimumTextChars) {
            $type = 'digital';
            $method = 'digital';
            $confidence = min(0.96, 0.6 + ($meaningfulRatio * 0.25) + min(0.11, $textCharacterCount / 5000));
            $reason = 'Most PDF pages contain meaningful embedded text.';
        } elseif ($pageCount > 0 && $meaningfulRatio >= $hybridThreshold && $textCharacterCount >= max(20, (int) ($minimumTextChars / 2))) {
            $type = 'hybrid';
            $method = 'hybrid';
            $confidence = min(0.84, 0.45 + ($meaningfulRatio * 0.2) + min(0.09, $textCharacterCount / 6000));
            $reason = 'Some PDF pages contain meaningful embedded text, but coverage is incomplete.';
        }

        return [
            'type' => $type,
            'method' => $method,
            'confidence' => round($confidence, 4),
            'reason' => $reason,
            'page_count' => $pageCount,
            'meaningful_text_pages' => $meaningfulTextPages,
            'text_character_count' => $textCharacterCount,
        ];
    }
}
