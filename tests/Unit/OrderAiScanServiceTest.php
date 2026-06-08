<?php

namespace Tests\Unit;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
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
        $this->assertStringContainsString('Trendy doo', $payload['order']['note']);
        $this->assertStringContainsString('Bratstvo 11', $payload['order']['note']);
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
}
