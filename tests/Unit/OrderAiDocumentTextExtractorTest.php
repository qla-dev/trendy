<?php

namespace Tests\Unit;

use App\Services\OrderAi\Support\OrderAiDocumentTextExtractor;
use ReflectionClass;
use Tests\TestCase;

class OrderAiDocumentTextExtractorTest extends TestCase
{
    public function test_normalize_extracted_text_preserves_valid_utf8_german_characters(): void
    {
        $extractor = app(OrderAiDocumentTextExtractor::class);
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('normalizeExtractedText');
        $method->setAccessible(true);
        $utf8Text = hex2bin('5374c3b6c39f656c204dc3bc6c6c65722052c3bc7374656e');

        $normalized = $method->invoke($extractor, $utf8Text);

        $this->assertSame($utf8Text, $normalized);
    }

    public function test_normalize_extracted_text_converts_windows_1252_german_characters_to_utf8(): void
    {
        $extractor = app(OrderAiDocumentTextExtractor::class);
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('normalizeExtractedText');
        $method->setAccessible(true);
        $utf8Text = hex2bin('5374c3b6c39f656c204dc3bc6c6c65722052c3bc7374656e');
        $windows1252Text = iconv('UTF-8', 'Windows-1252//IGNORE', $utf8Text);

        $normalized = $method->invoke($extractor, $windows1252Text);

        $this->assertSame($utf8Text, $normalized);
    }

    public function test_normalize_extracted_text_repairs_valid_utf8_mojibake_sequences(): void
    {
        $extractor = app(OrderAiDocumentTextExtractor::class);
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('normalizeExtractedText');
        $method->setAccessible(true);
        $mojibakeText = hex2bin('5374c383c2b6c383c5b8656c2066c383c2bc7220c382c2a7203134');
        $expected = hex2bin('5374c3b6c39f656c2066c3bc7220c2a7203134');

        $normalized = $method->invoke($extractor, $mojibakeText);

        $this->assertSame($expected, $normalized);
    }
}
