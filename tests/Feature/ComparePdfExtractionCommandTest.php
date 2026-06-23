<?php

namespace Tests\Feature;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use App\Services\OrderAi\MockOrderAiScanProvider;
use App\Services\OrderAi\Support\OrderAiDocumentPreparationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComparePdfExtractionCommandTest extends TestCase
{
    public function test_compare_pdf_extraction_command_generates_json_report(): void
    {
        config([
            'ai-order-scan.provider' => 'mock',
            'ai-order-scan.storage_disk' => 'local',
        ]);

        app()->instance(MockOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return false;
            }

            public function scan(OrderAiScan $scan): array
            {
                $disk = (string) config('ai-order-scan.storage_disk', 'local');
                $bytes = (string) Storage::disk($disk)->get((string) $scan->source_file_path);
                $preparedDocument = app(OrderAiDocumentPreparationService::class)->prepareDocument(
                    (string) ($scan->document_profile ?? ''),
                    (string) ($scan->source_file_name ?? 'document'),
                    (string) ($scan->source_mime_type ?? 'application/pdf'),
                    $bytes
                );
                $isDigital = (($preparedDocument['provider_input_mode'] ?? '') === 'text');
                $payload = [
                    'order' => [
                        'customer_name' => 'Trendy d.o.o.',
                        'supplier_name' => $isDigital ? 'Digital Supplier GmbH' : 'Legacy Supplier GmbH',
                        'page_count' => (int) ($preparedDocument['effective_page_count'] ?? 1),
                        'receiver_name' => 'Trendy d.o.o.',
                        'contact_name' => '',
                        'external_document_number' => $isDigital ? 'PO-2026-002' : 'PO-2026-001',
                        'document_type' => '0110',
                        'currency' => 'EUR',
                        'delivery_deadline' => '',
                        'note' => '',
                        'way_of_sale' => 'D',
                        'confidence' => $isDigital ? 0.92 : 0.61,
                        'warnings' => [],
                    ],
                    'items' => [[
                        'line_number' => 1,
                        'product_code' => $isDigital ? '6345894' : '6345894-OLD',
                        'product_name' => 'Klotz GM4395/01-70-126/1-2-18',
                        'drawing_reference' => '',
                        'material_hint' => '',
                        'quantity' => 3,
                        'unit' => 'KO',
                        'delivery_deadline' => '',
                        'unit_price' => $isDigital ? 42.60 : 42.60,
                        'line_total' => $isDigital ? 127.80 : 1127.80,
                        'vat_rate' => 0,
                        'vat_code' => 'P1',
                        'discount_percent' => 0,
                        'priority' => '',
                        'note' => '',
                    ]],
                    'summary' => [
                        'subtotal' => $isDigital ? 127.80 : 1127.80,
                        'vat_total' => 0,
                        'grand_total' => $isDigital ? 127.80 : 1127.80,
                    ],
                ];

                return [
                    'provider' => 'mock',
                    'model' => 'mock-comparison',
                    'credits_spent' => $isDigital ? 0.8 : 1.4,
                    'raw_response' => [
                        'usage' => [
                            'total_tokens' => $isDigital ? 120 : 240,
                            'prompt_tokens' => $isDigital ? 80 : 180,
                            'completion_tokens' => $isDigital ? 40 : 60,
                        ],
                    ],
                    'normalized_payload' => $payload,
                    'prepared_document' => $preparedDocument,
                    'extraction_duration_ms' => 10,
                    'ai_duration_ms' => $isDigital ? 45 : 90,
                ];
            }
        });

        $pdfPath = storage_path('app/testing-compare-pdf.pdf');
        $jsonPath = storage_path('app/testing-compare-pdf-report.json');
        $maxIdBeforeRun = (int) (OrderAiScan::query()->max('id') ?? 0);
        file_put_contents($pdfPath, $this->buildSyntheticPdf([
            [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512108386',
                '20 6449473 3,00 ST',
                'Klotz',
                'GM4395/01-70-126/1-2-18',
                'Nettopreis 42,60 EUR ST 1 127,80',
                'Nettowert: 127,80',
                'Gesamtbetrag: 127,80',
                '*********************************** ACHTUNG * *************************************',
            ],
        ]));

        try {
            $status = Artisan::call('trendy:compare-pdf-extraction', [
                'path' => $pdfPath,
                '--output' => $jsonPath,
            ]);
            $output = Artisan::output();

            $this->assertSame(0, $status);
            $this->assertStringContainsString('PDF Extraction Comparison', $output);
            $this->assertStringContainsString('Digital PDF', $output);
            $this->assertFileExists($jsonPath);

            $report = json_decode((string) file_get_contents($jsonPath), true);
            $createdScans = OrderAiScan::query()
                ->where('id', '>', $maxIdBeforeRun)
                ->orderBy('id')
                ->get();

            $this->assertSame('PO-2026-001', data_get($report, 'legacy.projection.document_order_number'));
            $this->assertSame('4512108386', data_get($report, 'digital.projection.document_order_number'));
            $this->assertSame(240, data_get($report, 'legacy.usage.total_tokens'));
            $this->assertSame(0, data_get($report, 'digital.usage.total_tokens'));
            $this->assertFalse((bool) data_get($report, 'field_comparisons.1.match', true));
            $this->assertCount(2, $createdScans);
            $this->assertSame('comparison', (string) $createdScans[0]->source_origin);
            $this->assertSame('comparison', (string) $createdScans[1]->source_origin);
            $this->assertSame('completed', (string) $createdScans[0]->status);
            $this->assertSame('completed', (string) $createdScans[1]->status);
            $this->assertSame('mock', (string) $createdScans[0]->provider);
            $this->assertSame('digital_pdf_rules', (string) $createdScans[1]->provider);
            $this->assertNotNull($createdScans[0]->normalized_payload);
            $this->assertNotNull($createdScans[1]->normalized_payload);
            $this->assertSame((int) $createdScans[0]->id, (int) data_get($report, 'legacy.scan_id'));
            $this->assertSame((int) $createdScans[1]->id, (int) data_get($report, 'digital.scan_id'));
        } finally {
            OrderAiScan::query()->where('id', '>', $maxIdBeforeRun)->delete();
            @unlink($pdfPath);
            @unlink($jsonPath);
        }
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
