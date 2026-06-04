<?php

namespace Tests\Unit;

use App\Services\OrderAi\Profiles\OrderDocumentProfileDetector;
use Tests\TestCase;

class OrderDocumentProfileDetectorTest extends TestCase
{
    public function test_detects_trendy_de_from_header_marker(): void
    {
        $bytes = file_get_contents($this->fixturePath('Bestellung_26-020-000675.pdf'));

        $profile = app(OrderDocumentProfileDetector::class)->detect('random-order.pdf', 'application/pdf', (string) $bytes);

        $this->assertSame('trendy_de', $profile);
    }

    public function test_detects_trendy_de_from_filename_only(): void
    {
        $profile = app(OrderDocumentProfileDetector::class)->detect(
            'Bestellung_26-020-000675.pdf',
            'application/pdf',
            '%PDF-1.4 Generic order'
        );

        $this->assertSame('trendy_de', $profile);
    }

    public function test_detects_grob_profile_from_existing_markers(): void
    {
        $bytes = file_get_contents($this->fixturePath('grob-existing-form.pdf'));

        $profile = app(OrderDocumentProfileDetector::class)->detect('4512109380.pdf', 'application/pdf', (string) $bytes);

        $this->assertSame('grob', $profile);
    }

    public function test_falls_back_to_grob_when_no_profile_matches(): void
    {
        $profile = app(OrderDocumentProfileDetector::class)->detect(
            'unknown.pdf',
            'application/pdf',
            '%PDF-1.4 Unknown document without matching markers'
        );

        $this->assertSame('grob', $profile);
    }

    private function fixturePath(string $fileName): string
    {
        return __DIR__ . '/../Fixtures/order-ai/' . $fileName;
    }
}
