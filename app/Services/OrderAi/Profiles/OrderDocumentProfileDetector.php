<?php

namespace App\Services\OrderAi\Profiles;

class OrderDocumentProfileDetector
{
    public function detect(string $fileName, ?string $mimeType, string $bytes): string
    {
        unset($mimeType);

        $searchableText = $this->extractSearchableText($bytes);
        $scores = [
            'trendy_de' => $this->scoreTrendyDe($fileName, $searchableText),
            'grob' => $this->scoreGrob($fileName, $searchableText),
        ];

        arsort($scores);
        $bestProfile = array_key_first($scores) ?: $this->defaultProfileKey();
        $bestScore = (int) ($scores[$bestProfile] ?? 0);

        if ($bestScore > 0) {
            return $bestProfile;
        }

        return $this->defaultProfileKey();
    }

    public function extractSearchableText(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bytes = str_replace("\0", ' ', $bytes);
        $bytes = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $bytes) ?? $bytes;
        $bytes = preg_replace("/\r\n?/", "\n", $bytes) ?? $bytes;
        $bytes = preg_replace("/[ \t]+/", ' ', $bytes) ?? $bytes;
        $bytes = preg_replace("/\n{3,}/", "\n\n", $bytes) ?? $bytes;

        return trim($bytes);
    }

    private function scoreTrendyDe(string $fileName, string $searchableText): int
    {
        $score = 0;
        $upperFileName = strtoupper($fileName);
        $upperText = strtoupper($searchableText);

        if (str_contains($upperFileName, 'BESTELLUNG_')) {
            $score += 100;
        }

        if (str_contains($upperText, 'TRENDY GERMANY GMBH')) {
            $score += 100;
        }

        foreach (['ARTIKEL NR.', 'LIEFERTERMIN', 'EK-PREIS', 'VAT %', 'ANLIEFERADRESSE'] as $marker) {
            if (str_contains($upperText, $marker)) {
                $score += 15;
            }
        }

        return $score;
    }

    private function scoreGrob(string $fileName, string $searchableText): int
    {
        $score = 0;
        $haystack = strtoupper($searchableText);

        foreach (['GROB-WERKE', 'WERKSTOFF', 'ZEICHNUNG', 'NETTOPREIS', 'BRUTTOPREIS'] as $marker) {
            if (str_contains($haystack, $marker)) {
                $score += 20;
            }
        }

        if (preg_match('/45\d{8}/', $fileName) === 1) {
            $score += 10;
        }

        return $score;
    }

    private function defaultProfileKey(): string
    {
        $profiles = config('ai-order-scan.profiles', []);
        $defaultProfile = trim((string) config('ai-order-scan.default_profile', 'grob'));

        if ($defaultProfile !== '' && array_key_exists($defaultProfile, $profiles)) {
            return $defaultProfile;
        }

        return array_key_first($profiles) ?: 'grob';
    }
}
