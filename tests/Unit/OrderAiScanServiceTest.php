<?php

namespace Tests\Unit;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use App\Services\OrderAi\Support\OrderAiDocumentPreparationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class OrderAiScanServiceTest extends TestCase
{
    public function test_post_process_profile_payload_extracts_trendy_de_header_fields(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000675.pdf';
        Storage::disk('local')->put(
            $sourcePath,
            (string) file_get_contents($this->fixturePath('Bestellung_26-020-000675.pdf'))
        );

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000675.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);

        $payload = $method->invoke($service, $scan, $this->basePayload());

        $this->assertSame('Trendy Germany GmbH', $payload['order']['customer_name']);
        $this->assertSame('Trendy Germany GmbH', $payload['order']['supplier_name']);
        $this->assertSame('26-020-000675', $payload['order']['external_document_number']);
        $this->assertSame('1. 6. 2026.', $payload['order']['delivery_deadline']);
        $this->assertSame('Edina Duzan', $payload['order']['contact_name']);
        $this->assertSame('Trendy Germany 21', $payload['order']['receiver_name']);
        $this->assertSame('KO', $payload['items'][0]['unit']);
        $this->assertStringContainsString('Trendy doo', $payload['order']['note']);
        $this->assertStringContainsString('Bratstvo 11', $payload['order']['note']);
    }

    public function test_post_process_profile_payload_splits_trendy_de_beschreibung_and_item_delivery_deadline(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000999.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => 'order-ai-scans/Bestellung_26-020-000999.pdf',
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['items'][0]['product_name'] = "Halter 884698\nspiegelbildlich\nLiefertermin 14. 6. 2026.";
        $payload['items'][0]['unit'] = 'STU';

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('Halter 884698', $payload['items'][0]['product_name']);
        $this->assertSame('spiegelbildlich', $payload['items'][0]['note']);
        $this->assertSame('14. 6. 2026.', $payload['items'][0]['delivery_deadline']);
        $this->assertSame('KO', $payload['items'][0]['unit']);
    }

    public function test_post_process_profile_payload_corrects_grob_netto_preis_and_summary_before_attention_block(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109380.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '%PDF-1.4',
            'GROB-WERKE GmbH & Co. KG',
            'Bestellung 4512109380',
            'Pos 70',
            '3226090',
            'Klotz',
            'G552-11000-1000-10-80-1-01-1-30',
            'Zeichnung Z-101',
            'Werkstoff: RSt37-2',
            'Bruttopreis 138,70',
            'Ruesten/Termin abs.',
            'Preiseinheit ST',
            'Nettopreis 170,70',
            'Wert 341,40',
            'Nettowert 341,40',
            '*********************************** ACHTUNG * *************************************',
            'Bruttopreis 999,99',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512109380.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 70;
        $payload['items'][0]['product_code'] = '3226090';
        $payload['items'][0]['unit'] = 'ST';
        $payload['items'][0]['unit_price'] = 138.70;
        $payload['items'][0]['line_total'] = 0;
        $payload['summary']['subtotal'] = 0;
        $payload['summary']['grand_total'] = 0;

        $payload = $method->invoke($service, $scan, $payload);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);
        $normalized = $normalizeMethod->invoke($service, $payload);

        $this->assertSame(170.7, $normalized['items'][0]['unit_price']);
        $this->assertSame(341.4, $normalized['items'][0]['line_total']);
        $this->assertSame('KO', $normalized['items'][0]['unit']);
        $this->assertSame(341.4, $normalized['summary']['subtotal']);
    }

    public function test_post_process_profile_payload_keeps_grob_netto_preis_when_it_is_on_same_page(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109381.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '%PDF-1.4',
            'GROB-WERKE GmbH & Co. KG',
            'Pos 10',
            '65010001',
            'Konzola',
            'Preiseinheit ST',
            'Nettopreis 24,50',
            'Wert 49,00',
            'Nettowert 49,00',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512109381.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['items'][0]['line_number'] = 10;
        $payload['items'][0]['product_code'] = '65010001';
        $payload['items'][0]['unit_price'] = 24.50;
        $payload['items'][0]['line_total'] = 49.00;

        $payload = $method->invoke($service, $scan, $payload);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);
        $normalized = $normalizeMethod->invoke($service, $payload);

        $this->assertSame(24.5, $normalized['items'][0]['unit_price']);
        $this->assertSame(49.0, $normalized['items'][0]['line_total']);
        $this->assertSame('KO', $normalized['items'][0]['unit']);
        $this->assertSame(49.0, $normalized['summary']['subtotal']);
    }

    public function test_normalize_payload_marks_summary_mismatch_with_warning(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['unit'] = 'ST';
        $payload['summary']['subtotal'] = 999.99;

        $normalized = $method->invoke($service, $payload);

        $this->assertSame('KO', $normalized['items'][0]['unit']);
        $this->assertNotEmpty($normalized['order']['warnings']);
        $this->assertStringContainsString('Skenirani', implode(' ', $normalized['order']['warnings']));
    }

    public function test_build_status_payload_uses_effective_grob_page_count_until_attention_marker(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109399.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf($this->grobPageFixture()));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'status' => 'uploaded',
            'progress_current' => 25,
            'progress_total' => 100,
            'page_count' => 17,
            'source_file_name' => '4512109399.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
            'normalized_payload' => [],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);

        $this->assertSame(17, $payload['page_count']);
        $this->assertSame(5, $payload['effective_page_count']);
        $this->assertSame('GROB obrada stavki je ograničena do ACHTUNG reda.', $payload['page_processing_limit_reason']);
    }

    public function test_build_status_payload_counts_only_active_elapsed_time_after_transfer(): void
    {
        $scan = new OrderAiScan([
            'status' => 'transferred',
            'processing_step' => 'Transferred',
            'progress_current' => 100,
            'progress_total' => 100,
            'document_profile' => 'grob',
            'processed_at' => Carbon::parse('2026-06-04 08:00:14'),
            'transfer_started_at' => Carbon::parse('2026-06-04 08:05:00'),
            'transferred_at' => Carbon::parse('2026-06-04 08:05:04'),
            'completed_at' => Carbon::parse('2026-06-04 08:05:04'),
        ]);
        $scan->created_at = Carbon::parse('2026-06-04 08:00:00');

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);

        $this->assertSame(18, $payload['elapsed_seconds']);
        $this->assertSame('18s', $payload['elapsed_display']);
    }

    public function test_build_status_payload_falls_back_to_page_count_for_billed_tokens(): void
    {
        $scan = new OrderAiScan([
            'status' => 'completed',
            'progress_current' => 100,
            'progress_total' => 100,
            'normalized_payload' => [
                'order' => [
                    'page_count' => 2,
                    'warnings' => [],
                ],
                'items' => [],
                'summary' => [],
            ],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);

        $this->assertSame(10, $payload['billed_tokens']);
    }

    public function test_grob_document_preparation_limits_ai_input_to_pages_before_attention_marker(): void
    {
        $prepared = app(OrderAiDocumentPreparationService::class)->prepareDocument(
            'grob',
            '4512108386.pdf',
            'application/pdf',
            $this->buildSyntheticPdf($this->grobPageFixture())
        );

        $this->assertSame(17, $prepared['source_page_count']);
        $this->assertSame(5, $prepared['effective_page_count']);
        $this->assertSame(OrderAiDocumentPreparationService::GROB_PAGE_LIMIT_REASON, $prepared['page_processing_limit_reason']);
        $this->assertSame('text', $prepared['provider_input_mode']);
        $this->assertStringContainsString('[Page 5]', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('[Page 6]', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('Fr. 21.08.2026', $prepared['provider_input_text']);
        $this->assertStringContainsString('Gesamtbetrag: 98,15', $prepared['provider_input_text']);
    }

    public function test_post_process_profile_payload_uses_grob_nettopreis_from_next_page_and_discards_bruttopreis(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512108386.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf($this->grobPageFixture()));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512108386.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['order']['page_count'] = 17;
        $payload['items'][0]['line_number'] = 90;
        $payload['items'][0]['product_code'] = '5842536';
        $payload['items'][0]['quantity'] = 16;
        $payload['items'][0]['unit'] = 'ST';
        $payload['items'][0]['unit_price'] = 5.16;
        $payload['items'][0]['line_total'] = 82.56;
        $payload['summary']['subtotal'] = 0;
        $payload['summary']['grand_total'] = 0;

        $payload = $method->invoke($service, $scan, $payload);
        $this->assertSame(5, $payload['order']['effective_page_count']);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);
        $normalized = $normalizeMethod->invoke($service, $payload);

        $this->assertSame(5, $normalized['order']['page_count']);
        $this->assertSame(6.13, $normalized['items'][0]['unit_price']);
        $this->assertSame(98.15, $normalized['items'][0]['line_total']);
        $this->assertSame('KO', $normalized['items'][0]['unit']);
        $this->assertSame(98.15, $normalized['summary']['subtotal']);
    }

    private function basePayload(): array
    {
        return [
            'order' => [
                'customer_name' => '',
                'supplier_name' => '',
                'page_count' => 1,
                'receiver_name' => '',
                'contact_name' => '',
                'external_document_number' => '',
                'document_type' => '',
                'currency' => 'EUR',
                'delivery_deadline' => '',
                'note' => '',
                'way_of_sale' => 'D',
                'confidence' => 0,
                'warnings' => [],
            ],
            'items' => [
                [
                    'line_number' => 1,
                    'product_code' => '65070911',
                    'product_name' => 'Halter 884698',
                    'drawing_reference' => '',
                    'material_hint' => '',
                    'quantity' => 2,
                    'unit' => 'STU',
                    'delivery_deadline' => '',
                    'unit_price' => 308.3,
                    'line_total' => 616.6,
                    'vat_rate' => 0,
                    'vat_code' => 'P1',
                    'discount_percent' => 0,
                    'priority' => '',
                    'note' => '',
                ],
            ],
            'summary' => [
                'subtotal' => 616.6,
                'vat_total' => 0,
                'grand_total' => 616.6,
            ],
        ];
    }

    private function fixturePath(string $fileName): string
    {
        return __DIR__ . '/../Fixtures/order-ai/' . $fileName;
    }

    private function grobPageFixture(): array
    {
        $pages = [];

        for ($page = 1; $page <= 17; $page++) {
            $pages[$page] = [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512108386',
                'Seite ' . $page . ' von 17',
            ];
        }

        $pages[4] = [
            'GROB-WERKE GmbH & Co. KG',
            'BESTELLUNG',
            'Bestell-Nr.: 4512108386',
            '90 5842536 16,00 ST Stift',
            'GM7200/04-2400-29/1-6',
            'Zeichnung GM7200/04-2400-29/1-6 mit Revisionsstand 00',
            'Werkstoff: C45+C',
            'Beschichtung: galvanisch verzinkt',
            'Bruttopreis 5,16 EUR ST 1 82,56',
            'Seite 4 von 17',
        ];

        $pages[5] = [
            'GROB-WERKE GmbH & Co. KG',
            'BESTELLUNG',
            'Bestell-Nr.: 4512108386',
            'Ruesten/Termin abs. 15,59 EUR 15,59',
            'Nettopreis 6,13 EUR ST 1 98,15',
            'Lieferdatum: 11.06.2026 16,00 ST',
            'Nettowert: 98,15',
            'Gesamtbetrag: 98,15',
            '*********************************** ACHTUNG * *************************************',
            'Seite 5 von 17',
        ];

        $pages[6] = [
            'GROB-WERKE GmbH & Co. KG',
            'BESTELLUNG',
            'Bestell-Nr.: 4512108386',
            'Fr. 21.08.2026 07:00 Uhr bis 12:00 Uhr',
            'Achtung: Lieferungen die vise ne pripadaju tabeli.',
            'Seite 6 von 17',
        ];

        return array_values($pages);
    }

    private function buildSyntheticPdf(array $pages): string
    {
        $objectNumber = 2;
        $pageReferences = [];
        $objects = [];

        foreach (array_values($pages) as $pageLines) {
            $pageId = $objectNumber++;
            $contentId = $objectNumber++;
            $pageReferences[] = $pageId . ' 0 R';
            $streamLines = [];
            $y = 800;

            foreach ($pageLines as $line) {
                $encodedLine = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $line);
                $hex = strtoupper(bin2hex(is_string($encodedLine) && $encodedLine !== '' ? $encodedLine : $line));
                $streamLines[] = sprintf('BT 50 %.2F Td <%s>Tj ET', (float) $y, $hex);
                $y -= 14;
            }

            $stream = implode("\n", $streamLines) . "\n";
            $objects[] = $pageId . " 0 obj\n<<\n/Type /Page\n/Parent 1 0 R\n/Contents " . $contentId . " 0 R\n>>\nendobj\n";
            $objects[] = $contentId . " 0 obj\n<<\n/Length " . strlen($stream) . "\n>>\nstream\n" . $stream . "endstream\nendobj\n";
        }

        return "%PDF-1.3\n"
            . "1 0 obj\n<<\n/Type /Pages\n/Kids [" . implode(' ', $pageReferences) . "]\n/Count " . count($pages) . "\n>>\nendobj\n"
            . implode('', $objects)
            . "%%EOF";
    }
}
