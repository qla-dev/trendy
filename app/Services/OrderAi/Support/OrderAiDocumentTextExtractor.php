<?php

namespace App\Services\OrderAi\Support;

use App\Support\Utf8Sanitizer;
use Illuminate\Support\Str;

class OrderAiDocumentTextExtractor
{
    public function extract(string $bytes, ?string $mimeType = null, ?string $fileName = null): array
    {
        if ($this->looksLikePdf($bytes, $mimeType, $fileName)) {
            $pages = $this->extractPdfPages($bytes);

            if ($pages !== []) {
                return [
                    'page_count' => count($pages),
                    'pages' => $pages,
                    'text' => trim(implode("\n\n", array_values(array_filter(array_map(function (array $page) {
                        return trim((string) ($page['text'] ?? ''));
                    }, $pages))))),
                ];
            }
        }

        $fallbackText = $this->extractFallbackText($bytes);

        return [
            'page_count' => $fallbackText !== '' ? 1 : 0,
            'pages' => $fallbackText !== ''
                ? [[
                    'page_number' => 1,
                    'lines' => preg_split('/\n+/u', $fallbackText) ?: [],
                    'text' => $fallbackText,
                ]]
                : [],
            'text' => $fallbackText,
        ];
    }

    private function extractPdfPages(string $bytes): array
    {
        $pageObjectNumbers = $this->extractPageObjectNumbers($bytes);
        $pages = [];

        foreach ($pageObjectNumbers as $index => $pageObjectNumber) {
            $pageBody = $this->extractObjectBody($bytes, $pageObjectNumber);

            if ($pageBody === null) {
                continue;
            }

            $contentObjectNumbers = $this->extractPageContentObjectNumbers($pageBody);
            $elements = [];

            foreach ($contentObjectNumbers as $contentObjectNumber) {
                $stream = $this->extractObjectStream($bytes, $contentObjectNumber);

                if ($stream === null) {
                    continue;
                }

                $decodedStream = $this->decodeStream(
                    $stream['raw'],
                    $this->extractFilterNames($stream['dictionary'], $bytes)
                );

                if ($decodedStream === '') {
                    continue;
                }

                array_push($elements, ...$this->extractTextElementsFromStream($decodedStream));
            }

            $lines = $this->buildLines($elements);
            $pageText = trim(implode("\n", $lines));

            $pages[] = [
                'page_number' => $index + 1,
                'lines' => $lines,
                'text' => $pageText,
            ];
        }

        return array_values(array_filter($pages, function (array $page) {
            return trim((string) ($page['text'] ?? '')) !== '';
        }));
    }

    private function extractPageObjectNumbers(string $bytes): array
    {
        if (preg_match_all('/(?<!\d)(\d+)\s+\d+\s+obj\b(.*?)endobj/s', $bytes, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $pageObjectNumbers = [];

        foreach ($matches as $match) {
            $objectBody = (string) ($match[2] ?? '');
            $dictionaryOnly = (string) preg_split('/\bstream\b/s', $objectBody, 2)[0];

            if (preg_match('/\/Type\s*\/Page\b/i', $dictionaryOnly) !== 1) {
                continue;
            }

            $pageObjectNumbers[] = (int) ($match[1] ?? 0);
        }

        return array_values(array_filter($pageObjectNumbers));
    }

    private function extractObjectBody(string $bytes, int $objectNumber): ?string
    {
        if ($objectNumber <= 0) {
            return null;
        }

        if (preg_match('/(?<!\d)' . preg_quote((string) $objectNumber, '/') . '\s+\d+\s+obj\b(.*?)endobj/s', $bytes, $matches) !== 1) {
            return null;
        }

        return (string) preg_split('/\bstream\b/s', (string) ($matches[1] ?? ''), 2)[0];
    }

    private function extractPageContentObjectNumbers(string $pageBody): array
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $pageBody, $matches) === 1) {
            preg_match_all('/(\d+)\s+\d+\s+R/', (string) ($matches[1] ?? ''), $references);

            return array_values(array_map('intval', $references[1] ?? []));
        }

        if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $pageBody, $matches) === 1) {
            return [(int) ($matches[1] ?? 0)];
        }

        return [];
    }

    private function extractObjectStream(string $bytes, int $objectNumber): ?array
    {
        if ($objectNumber <= 0) {
            return null;
        }

        $pattern = '/(?<!\d)' . preg_quote((string) $objectNumber, '/') . '\s+\d+\s+obj\s*<<(.*?)>>\s*stream\r?\n/s';

        if (preg_match($pattern, $bytes, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $dictionary = (string) ($matches[1][0] ?? '');
        $streamStart = (int) ($matches[0][1] ?? 0) + strlen((string) ($matches[0][0] ?? ''));
        $streamEnd = strpos($bytes, 'endstream', $streamStart);

        if ($streamEnd === false || $streamEnd < $streamStart) {
            return null;
        }

        $rawStream = substr($bytes, $streamStart, $streamEnd - $streamStart);

        if ($rawStream === false) {
            return null;
        }

        return [
            'dictionary' => $dictionary,
            'raw' => rtrim($rawStream, "\r\n"),
        ];
    }

    private function extractFilterNames(string $dictionary, string $bytes): array
    {
        if (preg_match('/\/Filter\s+(\d+)\s+\d+\s+R/i', $dictionary, $matches) === 1) {
            $referencedBody = $this->extractObjectBody($bytes, (int) ($matches[1] ?? 0));

            if (is_string($referencedBody) && $referencedBody !== '') {
                return $this->parseFilterNames($referencedBody);
            }
        }

        return $this->parseFilterNames($dictionary);
    }

    private function parseFilterNames(string $value): array
    {
        if (preg_match_all('/\/([A-Za-z0-9]+Decode)\b/', $value, $matches) < 1) {
            return [];
        }

        return array_values(array_map(function ($filterName) {
            return trim((string) $filterName);
        }, $matches[1] ?? []));
    }

    private function decodeStream(string $stream, array $filters): string
    {
        $decoded = $stream;

        foreach ($filters as $filterName) {
            $decoded = match (strtolower($filterName)) {
                'flatedecode' => $this->decodeFlateStream($decoded),
                'runlengthdecode' => $this->decodeRunLengthStream($decoded),
                'asciihexdecode' => $this->decodeAsciiHexStream($decoded),
                default => $decoded,
            };

            if ($decoded === '') {
                break;
            }
        }

        return $decoded;
    }

    private function decodeFlateStream(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = @gzuncompress($value);

        if (is_string($decoded)) {
            return $decoded;
        }

        $decoded = @gzinflate($value);

        if (is_string($decoded)) {
            return $decoded;
        }

        $decoded = @gzinflate(substr($value, 2));

        return is_string($decoded) ? $decoded : '';
    }

    private function decodeRunLengthStream(string $value): string
    {
        $length = strlen($value);
        $offset = 0;
        $decoded = '';

        while ($offset < $length) {
            $control = ord($value[$offset]);
            $offset++;

            if ($control === 128) {
                break;
            }

            if ($control < 128) {
                $segmentLength = $control + 1;
                $decoded .= substr($value, $offset, $segmentLength);
                $offset += $segmentLength;

                continue;
            }

            if ($offset >= $length) {
                break;
            }

            $decoded .= str_repeat($value[$offset], 257 - $control);
            $offset++;
        }

        return $decoded;
    }

    private function decodeAsciiHexStream(string $value): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f>]/', '', $value) ?? $value;
        $hex = rtrim($hex, '>');

        if ($hex === '') {
            return '';
        }

        if ((strlen($hex) % 2) === 1) {
            $hex .= '0';
        }

        $decoded = @hex2bin($hex);

        return is_string($decoded) ? $decoded : '';
    }

    private function extractTextElementsFromStream(string $stream): array
    {
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $stream, $blocks, PREG_SET_ORDER) < 1) {
            return [];
        }

        $elements = [];

        foreach ($blocks as $blockMatch) {
            $block = (string) ($blockMatch[1] ?? '');
            $x = 0.0;
            $y = 0.0;
            $pattern = '/
                (?P<tdx>-?\d+(?:\.\d+)?)\s+(?P<tdy>-?\d+(?:\.\d+)?)\s+Td
                |
                (?P<tm1>-?\d+(?:\.\d+)?)\s+(?P<tm2>-?\d+(?:\.\d+)?)\s+(?P<tm3>-?\d+(?:\.\d+)?)\s+(?P<tm4>-?\d+(?:\.\d+)?)\s+(?P<tmx>-?\d+(?:\.\d+)?)\s+(?P<tmy>-?\d+(?:\.\d+)?)\s+Tm
                |
                <(?P<hex>[0-9A-Fa-f\s]+)>\s*Tj
                |
                \((?P<literal>(?:\\\\.|[^\\\\)])*)\)\s*Tj
                |
                \[(?P<array>.*?)\]\s*TJ
            /sx';

            if (preg_match_all($pattern, $block, $tokens, PREG_SET_ORDER) < 1) {
                continue;
            }

            foreach ($tokens as $token) {
                if (($token['tdx'] ?? '') !== '' && ($token['tdy'] ?? '') !== '') {
                    $x = (float) $token['tdx'];
                    $y = (float) $token['tdy'];
                    continue;
                }

                if (($token['tmx'] ?? '') !== '' && ($token['tmy'] ?? '') !== '') {
                    $x = (float) $token['tmx'];
                    $y = (float) $token['tmy'];
                    continue;
                }

                $decodedText = '';

                if (($token['hex'] ?? '') !== '') {
                    $decodedText = $this->decodePdfHexString((string) $token['hex']);
                } elseif (($token['literal'] ?? '') !== '') {
                    $decodedText = $this->decodePdfLiteralString((string) $token['literal']);
                } elseif (($token['array'] ?? '') !== '') {
                    $decodedText = $this->decodePdfTextArray((string) $token['array']);
                }

                $decodedText = trim($this->normalizeExtractedText($decodedText));

                if ($decodedText === '') {
                    continue;
                }

                $elements[] = [
                    'x' => $x,
                    'y' => $y,
                    'text' => $decodedText,
                ];
            }
        }

        return $elements;
    }

    private function decodePdfTextArray(string $value): string
    {
        if (preg_match_all('/<([0-9A-Fa-f\s]+)>|\((?:\\\\.|[^\\\\)])*\)/s', $value, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return '';
        }

        $decoded = '';

        foreach ($matches[0] as $index => $match) {
            $fragment = (string) ($match[0] ?? '');

            if ($fragment === '') {
                continue;
            }

            if (str_starts_with($fragment, '<')) {
                $decoded .= $this->decodePdfHexString((string) ($matches[1][$index][0] ?? ''));
                continue;
            }

            $decoded .= $this->decodePdfLiteralString(substr($fragment, 1, -1));
        }

        return $decoded;
    }

    private function decodePdfHexString(string $value): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $value) ?? $value;

        if ($hex === '') {
            return '';
        }

        if ((strlen($hex) % 2) === 1) {
            $hex .= '0';
        }

        $decoded = @hex2bin($hex);

        return is_string($decoded) ? $decoded : '';
    }

    private function decodePdfLiteralString(string $value): string
    {
        return preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function (array $matches) {
            $escape = (string) ($matches[1] ?? '');

            return match ($escape) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\f",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => ctype_digit($escape) ? chr(octdec($escape)) : $escape,
            };
        }, $value) ?? $value;
    }

    private function buildLines(array $elements): array
    {
        if ($elements === []) {
            return [];
        }

        usort($elements, function (array $left, array $right) {
            $yComparison = (float) ($right['y'] ?? 0) <=> (float) ($left['y'] ?? 0);

            if ($yComparison !== 0) {
                return $yComparison;
            }

            return (float) ($left['x'] ?? 0) <=> (float) ($right['x'] ?? 0);
        });

        $lineGroups = [];
        $currentGroup = [];
        $currentY = null;

        foreach ($elements as $element) {
            $elementY = (float) ($element['y'] ?? 0);

            if ($currentY === null || abs($currentY - $elementY) <= 1.2) {
                $currentGroup[] = $element;
                $currentY = $currentY ?? $elementY;
                continue;
            }

            $lineGroups[] = $currentGroup;
            $currentGroup = [$element];
            $currentY = $elementY;
        }

        if ($currentGroup !== []) {
            $lineGroups[] = $currentGroup;
        }

        $lines = [];

        foreach ($lineGroups as $group) {
            usort($group, function (array $left, array $right) {
                return (float) ($left['x'] ?? 0) <=> (float) ($right['x'] ?? 0);
            });

            $line = '';

            foreach ($group as $element) {
                $fragment = trim((string) ($element['text'] ?? ''));

                if ($fragment === '') {
                    continue;
                }

                $line = $this->appendLineFragment($line, $fragment);
            }

            $line = trim($this->normalizeExtractedText($line));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return array_values(array_filter($lines));
    }

    private function appendLineFragment(string $line, string $fragment): string
    {
        $line = trim($line);
        $fragment = trim($fragment);

        if ($line === '') {
            return $fragment;
        }

        if ($fragment === '') {
            return $line;
        }

        return $line . ($this->shouldJoinLineFragmentsWithoutSpace($line, $fragment) ? '' : ' ') . $fragment;
    }

    private function shouldJoinLineFragmentsWithoutSpace(string $left, string $right): bool
    {
        return preg_match('/\pL$/u', $left) === 1
            && preg_match('/^\pL/u', $right) === 1
            && (mb_strlen($left) <= 2 || mb_strlen($right) <= 2);
    }

    private function normalizeExtractedText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        $normalized = is_string($converted) && $converted !== '' ? $converted : $value;
        $normalized = str_replace("\0", ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return Utf8Sanitizer::clean(trim($normalized));
    }

    private function extractFallbackText(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $text = str_replace("\0", ' ', $bytes);
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $text) ?? $text;
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return Utf8Sanitizer::clean(trim($text));
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
