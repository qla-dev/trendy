<?php

namespace Tests\Unit;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Contracts\OrderAiScanProvider;
use App\Services\OrderAi\OpenRouterOrderAiScanProvider;
use App\Services\OrderAi\OrderAiScanService;
use App\Services\OrderAi\PantheonOrderTransferService;
use App\Services\OrderAi\Support\OrderAiDigitalPdfRulesParser;
use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use App\Services\OrderAi\Support\OrderAiDocumentPreparationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use RuntimeException;
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
        $this->assertSame('Trendy Germany GmbH-21', $payload['order']['supplier_name']);
        $this->assertSame('26-020-000675', $payload['order']['external_document_number']);
        $this->assertSame('1. 6. 2026.', $payload['order']['delivery_deadline']);
        $this->assertSame('Edina Duzan', $payload['order']['contact_name']);
        $this->assertSame('Trendy Germany 21', $payload['order']['receiver_name']);
        $this->assertSame('KO', $payload['items'][0]['unit']);
        $this->assertStringContainsString('Trendy doo', $payload['order']['note']);
        $this->assertStringContainsString('Bratstvo 11', $payload['order']['note']);
    }

    public function test_normalization_preserves_trendy_de_numbered_supplier_subject(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);

        $payload = $method->invoke($service, [
            'order' => [
                'customer_name' => 'Trendy Germany GmbH',
                'supplier_name' => 'Trendy Germany GmbH-45',
            ],
            'items' => [],
            'summary' => [],
        ]);

        $this->assertSame('Trendy Germany GmbH-45', $payload['order']['supplier_name']);
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

    public function test_post_process_profile_payload_merges_trendy_de_note_fragment_and_renumbers_lines(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000959.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => 'order-ai-scans/Bestellung_26-020-000959.pdf',
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['items'] = [
            [
                'line_number' => 0,
                'product_code' => '65018647',
                'product_name' => 'Lagerzapfen',
                'quantity' => 1,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 80.7,
                'line_total' => 80.7,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '',
            ],
            [
                'line_number' => 0,
                'product_code' => '65037490',
                'product_name' => 'Platte',
                'quantity' => 2,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 34.65,
                'line_total' => 69.3,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '',
            ],
            [
                'line_number' => 0,
                'product_code' => '65039927',
                'product_name' => 'Halterung',
                'quantity' => 1,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 139.2,
                'line_total' => 139.2,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '',
            ],
            [
                'line_number' => 0,
                'product_code' => '65011594',
                'product_name' => 'Einsatz',
                'quantity' => 2,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 350,
                'line_total' => 700,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '',
            ],
            [
                'line_number' => 4,
                'product_code' => '823926',
                'product_name' => 'a',
                'quantity' => 0,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 0,
                'line_total' => 0,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '',
            ],
        ];

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertCount(4, $payload['items']);
        $this->assertSame([1, 2, 3, 4], array_column($payload['items'], 'line_number'));
        $this->assertSame('Einsatz', $payload['items'][3]['product_name']);
        $this->assertSame('823926 a', $payload['items'][3]['note']);
        $this->assertSame(['65018647', '65037490', '65039927', '65011594'], array_column($payload['items'], 'product_code'));
    }

    public function test_post_process_profile_payload_splits_trendy_de_embedded_note_positions_with_crtez(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000959.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => 'order-ai-scans/Bestellung_26-020-000959.pdf',
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['items'] = [
            [
                'line_number' => 10,
                'product_code' => 'DN731970',
                'product_name' => 'STICK',
                'quantity' => 2,
                'unit' => 'KO',
                'delivery_deadline' => '07.09.2026',
                'unit_price' => 46.9,
                'line_total' => 93.8,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => 'BRASS | Crtež N01814580 | 11 DN731973_A SIDE PLATE, RIGHT | ANODIZED | Crtež N0181479A | 12 DN731976_A SIDE PLATE, LEFT | ANODIZED | Crtež N0181491A',
            ],
            [
                'line_number' => 13,
                'product_code' => 'DS1250A070070',
                'product_name' => 'Klemmhülse',
                'quantity' => 6,
                'unit' => 'KO',
                'delivery_deadline' => '07.09.2026',
                'unit_price' => 26.7,
                'line_total' => 160.2,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => 'ANODIZED',
            ],
        ];

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame([10, 11, 12, 13], array_column($payload['items'], 'line_number'));
        $this->assertSame('DN731970', $payload['items'][0]['product_code']);
        $this->assertSame('BRASS | Crtež N01814580', $payload['items'][0]['note']);
        $this->assertSame('DN731973_A', $payload['items'][1]['product_code']);
        $this->assertSame('SIDE PLATE, RIGHT', $payload['items'][1]['product_name']);
        $this->assertSame('ANODIZED | Crtež N0181479A', $payload['items'][1]['note']);
        $this->assertSame('DN731976_A', $payload['items'][2]['product_code']);
        $this->assertSame('SIDE PLATE, LEFT', $payload['items'][2]['product_name']);
        $this->assertSame('ANODIZED | Crtež N0181491A', $payload['items'][2]['note']);
    }

    public function test_post_process_profile_payload_uses_item_dates_when_trendy_de_header_date_is_blank(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            '27. 6. 2026.',
            'Trendy Germany GmbH',
            'Lieferant:',
            'Trendy doo',
            'Anlieferadresse:',
            'Trendy Germany 21',
            'Bestellung 26-020-000963',
            'Person responsible Edina Duzan',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1 BYPR05C120030 Abdeckblech Oben 15,00 STU 34,65 0,00 519,75',
            'CHEM.NICKEL PLATED',
            'Liefertermin: 20.07.2026',
            '2 EVE280A675030 Gehaeuseblock 6,00 STU 207,10 0,00 1.242,60',
            'WARM BROWNED',
            'Liefertermin: 27.07.2026',
            '1.762,35 Total',
            'Gesamtpreis EUR',
        ]]));

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['delivery_deadline'] = '27. 6. 2026.';
        $payload['items'] = [
            [
                'line_number' => 1,
                'product_code' => 'BYPR05C120030',
                'product_name' => 'Abdeckblech Oben',
                'quantity' => 15,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 34.65,
                'line_total' => 519.75,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '1 | CHEM.NICKEL PLATED',
            ],
            [
                'line_number' => 2,
                'product_code' => 'EVE280A675030',
                'product_name' => 'Gehaeuseblock',
                'quantity' => 6,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 207.1,
                'line_total' => 1242.6,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '2 | WARM BROWNED',
            ],
        ];

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('', $payload['order']['delivery_deadline']);
        $this->assertSame('20.07.2026', $payload['items'][0]['delivery_deadline']);
        $this->assertSame('CHEM.NICKEL PLATED', $payload['items'][0]['note']);
        $this->assertSame('27.07.2026', $payload['items'][1]['delivery_deadline']);
        $this->assertSame('WARM BROWNED', $payload['items'][1]['note']);
    }

    public function test_trendy_de_item_deadline_map_reads_page_lines_when_structured_table_items_exist(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTrendyDeItemDeliveryDeadlines');
        $method->setAccessible(true);

        $deadlines = $method->invoke($service, [[
            'items' => [
                ['text' => '1 BYPR05C120030 Abdeckblech Oben 15,00 STU 34,65 0,00 519,75'],
                ['text' => '2 EVE280A675030 Gehaeuseblock 6,00 STU 207,10 0,00 1.242,60'],
            ],
            'lines' => [
                '27. 6. 2026.',
                'Trendy Germany GmbH',
                'Bestellung 26-020-000963',
                'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
                '1 BYPR05C120030 Abdeckblech Oben 15,00 STU 34,65 0,00 519,75',
                'CHEM.NICKEL PLATED',
                'Liefertermin: 20.07.2026',
                '2 EVE280A675030 Gehaeuseblock 6,00 STU 207,10 0,00 1.242,60',
                'WARM BROWNED',
                'Liefertermin: 27.07.2026',
            ],
            'text' => '',
        ]], '');

        $this->assertSame('20.07.2026', $deadlines['line:1']);
        $this->assertSame('20.07.2026', $deadlines['code:BYPR05C120030']);
        $this->assertSame('27.07.2026', $deadlines['line:2']);
        $this->assertSame('27.07.2026', $deadlines['code:EVE280A675030']);
    }

    public function test_trendy_de_item_deadline_map_prefers_raw_text_when_structured_lines_shift_dates_to_next_row(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTrendyDeItemDeliveryDeadlines');
        $method->setAccessible(true);

        $deadlines = $method->invoke($service, [[
            'lines' => [
                'Anlieferadresse: Lieferant: Artikel Nr. Pos. Beschreibung Menge EK-Preis Einheit',
                'BYPR05C120030 519,75 0,00 Betrag 15,00 VAT % STU 34,65',
                'Abdeckblech Oben',
                '1',
                'CHEM.NICKEL PLATED',
                'EVE280A675030 1.242,60 0,00 6,00 Liefertermin: 20.07.2026 STU 207,10',
                'Gehaeuseblock',
                '2',
                'WARM BROWNED',
                'EVRB10B912110 55,30 0,00 1,00 Liefertermin: 27.07.2026 STU 55,30',
            ],
            'text' => '',
            'items' => [],
        ]], implode("\n", [
            'Artikel Nr.Pos. Beschreibung MengeEinheit EK-Preis BetragVAT %',
            '15,00 34,65STU 519,750,00BYPR05C120030 Abdeckblech Oben1',
            'CHEM.NICKEL PLATED',
            'Liefertermin: 20.07.2026',
            '6,00 207,10STU 1.242,600,00EVE280A675030 Gehaeuseblock2',
            'WARM BROWNED',
            'Liefertermin: 27.07.2026',
        ]));

        $this->assertSame('20.07.2026', $deadlines['line:1']);
        $this->assertSame('20.07.2026', $deadlines['code:BYPR05C120030']);
        $this->assertSame('27.07.2026', $deadlines['line:2']);
        $this->assertSame('27.07.2026', $deadlines['code:EVE280A675030']);
    }

    public function test_trendy_de_items_do_not_keep_ai_datum_deadline_when_source_context_is_available(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessTrendyDeItems');
        $method->setAccessible(true);

        $items = $method->invoke($service, [[
            'line_number' => 1,
            'product_code' => 'BYPR05C120030',
            'product_name' => 'Abdeckblech Oben',
            'quantity' => 15,
            'unit' => 'KO',
            'delivery_deadline' => '27. 6. 2026.',
            'unit_price' => 34.65,
            'line_total' => 519.75,
            'vat_rate' => 0,
            'vat_code' => 'P1',
            'discount_percent' => 0,
            'note' => 'CHEM.NICKEL PLATED',
        ]], '', [], false, ['27. 6. 2026.']);

        $this->assertSame('', $items[0]['delivery_deadline']);
    }

    public function test_post_process_profile_payload_keeps_valid_parser_item_deadline_when_source_map_is_empty(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '27. 6. 2026.',
            'Trendy Germany GmbH',
            'Bestellung 26-020-000963',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['delivery_deadline'] = '';
        $payload['items'] = [[
            'line_number' => 1,
            'product_code' => 'BYPR05C120030',
            'product_name' => 'Abdeckblech Oben',
            'quantity' => 15,
            'unit' => 'KO',
            'delivery_deadline' => '20.07.2026',
            'unit_price' => 34.65,
            'line_total' => 519.75,
            'vat_rate' => 0,
            'vat_code' => 'P1',
            'discount_percent' => 0,
            'note' => 'CHEM.NICKEL PLATED',
        ]];

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('', $payload['order']['delivery_deadline']);
        $this->assertSame('20.07.2026', $payload['items'][0]['delivery_deadline']);
    }

    public function test_post_process_profile_payload_rejects_existing_item_deadline_when_it_matches_trendy_de_datum(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '27. 6. 2026.',
            'Trendy Germany GmbH',
            'Bestellung 26-020-000963',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['delivery_deadline'] = '27. 6. 2026.';
        $payload['items'] = [[
            'line_number' => 1,
            'product_code' => 'BYPR05C120030',
            'product_name' => 'Abdeckblech Oben',
            'quantity' => 15,
            'unit' => 'KO',
            'delivery_deadline' => '27.06.2026',
            'unit_price' => 34.65,
            'line_total' => 519.75,
            'vat_rate' => 0,
            'vat_code' => 'P1',
            'discount_percent' => 0,
            'note' => 'CHEM.NICKEL PLATED',
        ]];

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('', $payload['order']['delivery_deadline']);
        $this->assertSame('', $payload['items'][0]['delivery_deadline']);
    }

    public function test_post_process_profile_payload_rejects_ai_datum_deadline_without_confirmed_trendy_de_header(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'Trendy Germany GmbH',
            'Bestellung 26-020-000963',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1 BYPR05C120030 Abdeckblech Oben 15,00 STU 34,65 0,00 519,75',
            'CHEM.NICKEL PLATED',
            'Liefertermin: 20.07.2026',
            '2 EVE280A675030 Gehaeuseblock 6,00 STU 207,10 0,00 1.242,60',
            'WARM BROWNED',
            'Liefertermin: 27.07.2026',
        ]]));

        $scan = new OrderAiScan([
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['delivery_deadline'] = '27. 6. 2026.';
        $payload['items'] = [
            [
                'line_number' => 1,
                'product_code' => 'BYPR05C120030',
                'product_name' => 'Abdeckblech Oben',
                'quantity' => 15,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 34.65,
                'line_total' => 519.75,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '1 | CHEM.NICKEL PLATED',
            ],
            [
                'line_number' => 2,
                'product_code' => 'EVE280A675030',
                'product_name' => 'Gehaeuseblock',
                'quantity' => 6,
                'unit' => 'KO',
                'delivery_deadline' => '27. 6. 2026.',
                'unit_price' => 207.1,
                'line_total' => 1242.6,
                'vat_rate' => 0,
                'vat_code' => 'P1',
                'discount_percent' => 0,
                'note' => '2 | WARM BROWNED',
            ],
        ];

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('', $payload['order']['delivery_deadline']);
        $this->assertSame('20.07.2026', $payload['items'][0]['delivery_deadline']);
        $this->assertSame('CHEM.NICKEL PLATED', $payload['items'][0]['note']);
        $this->assertSame('27.07.2026', $payload['items'][1]['delivery_deadline']);
        $this->assertSame('WARM BROWNED', $payload['items'][1]['note']);
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

    public function test_post_process_profile_payload_extracts_grob_ekg_requester_code(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512100377.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '%PDF-1.4',
            'GROB-WERKE GmbH & Co. KG',
            'Bestell-Nr.: 4512100377',
            'incl. Verpackung Zahlungsbed.: Trenkenschuh, Paul / 5358 Ekg:',
            'Kunden-Nr.: Lieferbed.: FCA, Trendy d.o.o. , 72290 Novi Travnik, Incoterms',
            '2010 Abweichend davon: Versicherung:',
            'incl. Versicherung nach ICC A Verpackung:',
            '040 Bitte weisen Sie in Ihrer Auftragsbestatigung / Rechnung die praferenzbegunstigte Ursprungsware aus.',
            '10 65010001 1,00 ST',
            'Konzola',
            'Nettopreis 24,50 EUR ST 1 24,50',
            'Lieferdatum: 14.06.2026 1,00 ST',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512100377.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['order']['requester_code'] = 'Kunden-Nr.';
        $payload['order']['note'] = 'GROB header note must not survive.';
        $payload['items'][0]['note'] = 'Kontierung: U38871-GM7260 | Lackierung: RAL 7035 Lichtgrau Glatt';

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('040', $payload['order']['requester_code']);
        $this->assertSame('', $payload['order']['note']);
        $this->assertSame('', $payload['items'][0]['note']);
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

    public function test_post_process_profile_payload_extracts_grob_totals_from_spaced_german_amount_variants(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $postProcessMethod = $reflection->getMethod('postProcessProfilePayload');
        $postProcessMethod->setAccessible(true);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);

        foreach (['10.807,71', '10 807,71', '10. 807,71'] as $index => $amountText) {
            $sourcePath = 'order-ai-scans/4512109381-amount-' . $index . '.pdf';
            Storage::disk('local')->put($sourcePath, implode("\n", [
                '%PDF-1.4',
                'GROB-WERKE GmbH & Co. KG',
                'Bestellung 4512109381',
                'Pos 10',
                '65010001',
                'Konzola',
                'Preiseinheit ST',
                'Nettopreis 10.807,71 EUR ST',
                'Wert ' . $amountText,
                'Nettowert ' . $amountText,
                'Gesamtbetrag ' . $amountText,
            ]));

            $scan = new OrderAiScan([
                'document_profile' => 'grob',
                'source_file_name' => '4512109381-amount-' . $index . '.pdf',
                'source_mime_type' => 'application/pdf',
                'source_file_path' => $sourcePath,
            ]);
            $payload = $this->basePayload();
            $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
            $payload['items'][0]['line_number'] = 10;
            $payload['items'][0]['product_code'] = '65010001';
            $payload['items'][0]['quantity'] = 1;
            $payload['items'][0]['unit'] = 'ST';
            $payload['items'][0]['unit_price'] = 0;
            $payload['items'][0]['line_total'] = 0;
            $payload['summary']['subtotal'] = 0;
            $payload['summary']['grand_total'] = 0;

            $normalized = $normalizeMethod->invoke(
                $service,
                $postProcessMethod->invoke($service, $scan, $payload)
            );

            $this->assertSame(10807.71, $normalized['items'][0]['unit_price'], 'Variant: ' . $amountText);
            $this->assertSame(10807.71, $normalized['items'][0]['line_total'], 'Variant: ' . $amountText);
            $this->assertSame('KO', $normalized['items'][0]['unit'], 'Variant: ' . $amountText);
            $this->assertSame(10807.71, $normalized['summary']['subtotal'], 'Variant: ' . $amountText);
            $this->assertSame(10807.71, $normalized['summary']['grand_total'], 'Variant: ' . $amountText);
        }
    }

    public function test_post_process_profile_payload_avoids_false_grob_summary_mismatch_warning_when_visible_total_matches_items(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109381-warning-check.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '%PDF-1.4',
            'GROB-WERKE GmbH & Co. KG',
            'Bestellung 4512109381',
            'Pos 10',
            '65010001',
            'Konzola',
            'Preiseinheit ST',
            'Nettopreis 10.807,71 EUR ST',
            'Wert 10. 807,71',
            'Nettowert 10. 807,71',
            'Gesamtbetrag 10. 807,71',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512109381-warning-check.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $postProcessMethod = $reflection->getMethod('postProcessProfilePayload');
        $postProcessMethod->setAccessible(true);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 10;
        $payload['items'][0]['product_code'] = '65010001';
        $payload['items'][0]['quantity'] = 1;
        $payload['items'][0]['unit'] = 'ST';
        $payload['items'][0]['unit_price'] = 0;
        $payload['items'][0]['line_total'] = 0;
        $payload['summary']['subtotal'] = 0;
        $payload['summary']['grand_total'] = 0;

        $normalized = $normalizeMethod->invoke(
            $service,
            $postProcessMethod->invoke($service, $scan, $payload)
        );
        $warningText = implode(' ', is_array($normalized['order']['warnings'] ?? null) ? $normalized['order']['warnings'] : []);

        $this->assertSame(10807.71, $normalized['summary']['subtotal']);
        $this->assertStringNotContainsString('Skenirani Nettowert/Gesamtbetrag', $warningText);
        $this->assertStringNotContainsString('Skenirani dokumentni iznos', $warningText);
    }

    public function test_post_process_profile_payload_ignores_stuck_one_before_grob_netto_line_total(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109381-netto-line-total.pdf';
        Storage::disk('local')->put($sourcePath, implode("\n", [
            '%PDF-1.4',
            'GROB-WERKE GmbH & Co. KG',
            'Bestellung 4512109381',
            '100 3052783 16,00 ST',
            'Klotz',
            'TM-HSK63-1865-0000-01-19',
            'Zeichnung TM-HSK63-1865-0000-01-19 mit Revisionsstand 02',
            'Werkstoff: St37-2K',
            'Beschichtung: brüniert',
            'Bruttopreis 16,36 EUR ST 1 261,76',
            'Ruesten/Termin abs. 40,63 EUR 40,63',
            'Nettopreis 18,90 EUR ST 1 302,39',
            'Lieferdatum: 18.06.2026 16,00 ST',
            'Nettowert 302,39',
            'Gesamtbetrag 302,39',
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512109381-netto-line-total.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $postProcessMethod = $reflection->getMethod('postProcessProfilePayload');
        $postProcessMethod->setAccessible(true);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 100;
        $payload['items'][0]['product_code'] = '3052783';
        $payload['items'][0]['quantity'] = 16;
        $payload['items'][0]['unit'] = 'ST';
        $payload['items'][0]['unit_price'] = 0;
        $payload['items'][0]['line_total'] = 0;
        $payload['summary']['subtotal'] = 0;
        $payload['summary']['grand_total'] = 0;

        $normalized = $normalizeMethod->invoke(
            $service,
            $postProcessMethod->invoke($service, $scan, $payload)
        );

        $this->assertSame(18.9, $normalized['items'][0]['unit_price']);
        $this->assertSame(302.39, $normalized['items'][0]['line_total']);
        $this->assertSame(302.39, $normalized['summary']['subtotal']);
        $this->assertSame(302.39, $normalized['summary']['grand_total']);
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

    public function test_normalize_payload_repairs_spaces_around_german_umlauts_in_names(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);
        $u = (string) hex2bin('c3bc');
        $o = (string) hex2bin('c3b6');
        $eszett = (string) hex2bin('c39f');
        $payload = $this->basePayload();
        $payload['order']['customer_name'] = 'M ' . $u . ' ller GmbH';
        $payload['order']['receiver_name'] = 'Gr ' . $u . ' n Werk';
        $payload['order']['contact_name'] = 'J ' . $o . ' rg M ' . $u . ' ller';
        $payload['order']['note'] = 'f ' . $u . ' r Produktion';
        $payload['items'][0]['product_name'] = 'St ' . $o . ' ' . $eszett . ' el';
        $payload['items'][0]['material_hint'] = 'br ' . $u . ' niert';
        $payload['items'][0]['note'] = 'f ' . $u . ' r Montage';

        $normalized = $method->invoke($service, $payload);

        $this->assertSame('M' . $u . 'ller GmbH', $normalized['order']['customer_name']);
        $this->assertSame('Gr' . $u . 'n Werk', $normalized['order']['receiver_name']);
        $this->assertSame('J' . $o . 'rg M' . $u . 'ller', $normalized['order']['contact_name']);
        $this->assertSame('f' . $u . 'r Produktion', $normalized['order']['note']);
        $this->assertSame('St' . $o . $eszett . 'el', $normalized['items'][0]['product_name']);
        $this->assertSame('br' . $u . 'niert', $normalized['items'][0]['material_hint']);
        $this->assertSame('f' . $u . 'r Montage', $normalized['items'][0]['note']);
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
            'processed_at' => Carbon::parse('2026-06-04 08:00:14'),
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

    public function test_build_status_payload_uses_page_based_billed_tokens_for_local_digital_rules_scan(): void
    {
        $scan = new OrderAiScan([
            'status' => 'completed',
            'provider' => 'digital_pdf_rules',
            'model' => 'local-digital-pdf-rules-v1',
            'progress_current' => 100,
            'progress_total' => 100,
            'processed_at' => Carbon::parse('2026-06-23 10:00:14'),
            'billed_tokens' => 0,
            'ai_duration_ms' => 0,
            'credits_spent' => 0,
            'normalized_payload' => [
                'order' => [
                    'page_count' => 5,
                    'warnings' => [],
                ],
                'items' => [
                    [
                        'product_code' => '6449473',
                    ],
                ],
                'summary' => [],
            ],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);

        $this->assertSame(10, $payload['billed_tokens']);
    }

    public function test_build_status_payload_blocks_scanned_order_when_duplicate_reference_preview_exists(): void
    {
        $scan = new OrderAiScan([
            'status' => 'completed',
            'progress_current' => 100,
            'progress_total' => 100,
            'processed_at' => Carbon::parse('2026-06-19 10:15:00'),
            'pantheon_transfer_payload' => [
                'preview_version' => 2,
                'preview_error' => 'Narudžba sa referencom "4512109382" već postoji u bazi kao 26-0110-001161.',
                'preview_error_code' => 'duplicate_reference',
                'transfer_blocked' => true,
                'transfer_hint' => 'Narudžba sa ovom referencom već postoji.',
            ],
            'normalized_payload' => [
                'order' => [
                    'customer_name' => 'Trendy d.o.o.',
                    'supplier_name' => 'GROB-WERKE',
                    'external_document_number' => '4512109382',
                    'document_type' => '0110',
                    'currency' => 'EUR',
                    'warnings' => [],
                ],
                'items' => [
                    [
                        'line_number' => 10,
                        'product_code' => '4008746',
                        'product_name' => 'Platte SVTP1841-01',
                        'quantity' => 6,
                        'unit' => 'KO',
                        'unit_price' => 26.70,
                        'line_total' => 160.20,
                        'vat_rate' => 17,
                        'vat_code' => 'P1',
                    ],
                ],
                'summary' => [
                    'subtotal' => 160.20,
                    'vat_total' => 27.23,
                    'grand_total' => 187.43,
                ],
            ],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);
        $this->assertFalse($payload['transfer_ready']);
        $this->assertTrue($payload['transfer_blocked']);
        $this->assertSame('duplicate_reference', $payload['transfer_preview_error_code']);
        $this->assertSame(
            'Narudžba sa referencom "4512109382" već postoji u bazi kao 26-0110-001161.',
            $payload['transfer_block_reason']
        );
        $this->assertSame(
            'Narudžba sa ovom referencom već postoji u bazi kao 26-0110-001161.',
            $payload['transfer_button_hint']
        );
    }

    public function test_normalize_payload_splits_embedded_grob_quantity_and_unit_out_of_product_code(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['items'][0]['product_code'] = '6482044 1,00 ST';
        $payload['items'][0]['quantity'] = 0;
        $payload['items'][0]['unit'] = '';

        $normalized = $method->invoke($service, $payload);

        $this->assertSame('6482044', $normalized['items'][0]['product_code']);
        $this->assertSame(1.0, $normalized['items'][0]['quantity']);
        $this->assertSame('KO', $normalized['items'][0]['unit']);
    }

    public function test_normalize_payload_deduplicates_repeated_grob_product_name_segments(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['product_code'] = '';
        $payload['items'][0]['product_name'] = 'Klotz GM4395/01-70-126/1-2-18 Klotz GM4395/01-70-126/1-2-18';

        $normalized = $method->invoke($service, $payload);

        $this->assertSame('Klotz GM4395/01-70-126/1-2-18', $normalized['items'][0]['product_name']);
    }

    public function test_normalize_payload_uses_catalog_product_name_for_grob_product_code(): void
    {
        $service = new class extends OrderAiScanService {
            protected function resolveCatalogProductNameByCode(string $productCode): string
            {
                return $productCode === '5842536'
                    ? 'Catalog Klotz GM4395/01-70-126/1-2-18'
                    : '';
            }
        };
        $reflection = new ReflectionClass(OrderAiScanService::class);
        $method = $reflection->getMethod('normalizePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['product_code'] = '5842536';
        $payload['items'][0]['product_name'] = 'Klotz GM4395/01-70-126/1-2-18 Klotz GM4395/01-70-126/1-2-18';

        $normalized = $method->invoke($service, $payload);

        $this->assertSame('5842536', $normalized['items'][0]['product_code']);
        $this->assertSame('Catalog Klotz GM4395/01-70-126/1-2-18', $normalized['items'][0]['product_name']);
    }

    public function test_build_status_payload_splits_embedded_grob_quantity_and_unit_out_of_legacy_product_code(): void
    {
        $scan = new OrderAiScan([
            'status' => 'completed',
            'progress_current' => 100,
            'progress_total' => 100,
            'normalized_payload' => [
                'order' => [
                    'page_count' => 1,
                    'warnings' => [],
                ],
                'items' => [[
                    'line_number' => 10,
                    'product_code' => '6482044 1,00 ST',
                    'product_name' => 'Traeger',
                    'drawing_reference' => '',
                    'material_hint' => '',
                    'quantity' => 0,
                    'unit' => '',
                    'delivery_deadline' => '',
                    'unit_price' => 10,
                    'line_total' => 10,
                    'vat_rate' => 0,
                    'vat_code' => 'P1',
                    'discount_percent' => 0,
                    'priority' => '',
                    'note' => '',
                ]],
                'summary' => [],
            ],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);

        $this->assertSame('6482044', data_get($payload, 'result.items.0.product_code'));
        $this->assertSame(1.0, data_get($payload, 'result.items.0.quantity'));
        $this->assertSame('KO', data_get($payload, 'result.items.0.unit'));
    }

    public function test_resolve_display_document_metrics_uses_effective_grob_page_count_and_zero_tokens_before_successful_extraction(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109400.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf($this->grobPageFixture()));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'status' => 'failed',
            'page_count' => 17,
            'billed_tokens' => 17,
            'source_file_name' => '4512109400.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
            'normalized_payload' => [],
            'processed_at' => null,
        ]);

        $metrics = app(OrderAiScanService::class)->resolveDisplayDocumentMetrics($scan);

        $this->assertSame(5, $metrics['page_count']);
        $this->assertSame(5, $metrics['effective_page_count']);
        $this->assertSame(0, $metrics['billed_tokens']);
    }

    public function test_resolve_stored_document_metrics_limits_grob_pages_and_starts_with_zero_tokens(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512109401.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf($this->grobPageFixture()));

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveStoredDocumentMetrics');
        $method->setAccessible(true);

        $metrics = $method->invoke(
            $service,
            'local',
            $sourcePath,
            '4512109401.pdf',
            'application/pdf',
            'grob'
        );

        $this->assertSame(5, $metrics['page_count']);
        $this->assertSame(0, $metrics['billed_tokens']);
        $this->assertSame(5, $metrics['effective_page_count']);
        $this->assertSame(OrderAiDocumentPreparationService::GROB_PAGE_LIMIT_REASON, $metrics['page_processing_limit_reason']);
    }

    public function test_normalize_source_mime_type_keeps_pdf_attachments_as_application_pdf(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeSourceMimeType');
        $method->setAccessible(true);

        $this->assertSame(
            'application/pdf',
            $method->invoke($service, 'application/octet-stream', 'Bestellung_26-020-000945.pdf', '%PDF-1.7 fake')
        );
        $this->assertSame(
            'application/pdf',
            $method->invoke($service, '', 'document.bin', '%PDF-1.7 fake')
        );
        $this->assertSame(
            'text/plain',
            $method->invoke($service, 'text/plain', 'document.txt', 'hello')
        );
    }

    public function test_build_status_payload_repairs_utf8_mojibake_in_stored_result(): void
    {
        $scan = new OrderAiScan([
            'status' => 'completed',
            'progress_current' => 100,
            'progress_total' => 100,
            'normalized_payload' => [
                'order' => [
                    'page_count' => 1,
                    'warnings' => [],
                    'note' => hex2bin('42697474652077656973656e205369652066c383c2bc7220c382c2a72031342e'),
                ],
                'items' => [[
                    'line_number' => 20,
                    'product_code' => '3480234',
                    'product_name' => hex2bin('5374c383c2b6c383c5b8656c20473531362d313935302d303030302d30322d3130'),
                    'drawing_reference' => '',
                    'material_hint' => '',
                    'quantity' => 30,
                    'unit' => 'KO',
                    'delivery_deadline' => '17.06.2026',
                    'unit_price' => 9.02,
                    'line_total' => 270.72,
                    'vat_rate' => 0,
                    'vat_code' => 'P1',
                    'discount_percent' => 0,
                    'priority' => '',
                    'note' => '',
                ]],
                'summary' => [
                    'subtotal' => 270.72,
                    'vat_total' => 0,
                    'grand_total' => 270.72,
                ],
            ],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);

        $this->assertSame(
            hex2bin('5374c3b6c39f656c20473531362d313935302d303030302d30322d3130'),
            data_get($payload, 'result.items.0.product_name')
        );
        $this->assertSame(
            hex2bin('42697474652077656973656e205369652066c3bc7220c2a72031342e'),
            data_get($payload, 'result.order.note')
        );
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
        $this->assertCount(5, $prepared['pages']);
        $this->assertSame(OrderAiDocumentPreparationService::GROB_PAGE_LIMIT_REASON, $prepared['page_processing_limit_reason']);
        $this->assertSame('text', $prepared['provider_input_mode']);
        $this->assertStringContainsString('"pdf_type": "digital"', $prepared['provider_input_text']);
        $this->assertStringContainsString('"page": 5', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('"page": 6', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('Fr. 21.08.2026', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('Fr. 21.08.2026', $prepared['raw_extracted_text']);
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

    public function test_parse_grob_items_from_pages_ignores_page_header_city_line_for_open_item(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseGrobItemsFromPages');
        $method->setAccessible(true);

        $items = $method->invoke($service, [
            [
                'page_number' => 1,
                'lines' => [
                    '70 3629426 1,00 ST',
                    'Klotz',
                    'G350-1950-0000-50-01-1',
                ],
                'text' => '',
            ],
            [
                'page_number' => 2,
                'lines' => [
                    '72290 NOVI TRAVNIK',
                    'Zeichnung G350-1950-0000-50-01-1 mit Revisionsstand 00',
                    'Werkstoff: St37-2K',
                    'Bruttopreis 3,28 EUR ST 1 3,28',
                    'Ruesten/Termin abs. 9,13 EUR 9,13',
                    'Nettopreis 12,41 EUR ST 1 12,41',
                    'Lieferdatum: 23.07.2026 1,00 ST',
                ],
                'text' => '',
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('Klotz G350-1950-0000-50-01-1', $items[0]['product_name']);
        $this->assertStringNotContainsString('72290 NOVI TRAVNIK', $items[0]['product_name']);
        $this->assertStringNotContainsString('Ruesten/Termin abs.', $items[0]['product_name']);
    }

    public function test_parse_grob_items_from_pages_stops_before_attachment_page_for_open_item(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseGrobItemsFromPages');
        $method->setAccessible(true);

        $items = $method->invoke($service, [
            [
                'page_number' => 1,
                'lines' => [
                    '10 4008746 6,00 ST',
                    'Platte',
                    'SVTP1841-01',
                    'Nettopreis 26,70 EUR ST 1 160,20',
                ],
                'text' => '',
            ],
            [
                'page_number' => 2,
                'lines' => [
                    'Warenbegleitschein',
                    'Lieferant',
                    '7307181, Trendy d.o.o.',
                    'GROB-Identnr.:',
                    'SVTP1841-01',
                    'Material:',
                    '4008746',
                ],
                'text' => '',
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('Platte SVTP1841-01', $items[0]['product_name']);
        $this->assertSame(160.2, $items[0]['line_total']);
        $this->assertStringNotContainsString('Warenbegleitschein', $items[0]['note']);
        $this->assertStringNotContainsString('Lieferant', $items[0]['note']);
    }

    public function test_parse_grob_items_from_pages_drops_leading_unit_token_from_product_name_lines(): void
    {
        $durchfuehrung = (string) hex2bin('447572636866c3bc6872756e67');
        $pages = [[
            'page_number' => 1,
            'lines' => [
                '10 3610143 7,00 ST',
                'ST ' . $durchfuehrung,
                'G352-1220-206-0000-06-1',
                'Zeichnung G352-1220-206-0000-06-1 mit Revisionsstand 00',
                'Werkstoff: AlSi1MgMnT6',
                'Nettopreis 38,50 EUR ST 1 269,52',
                'Lieferdatum: 16.07.2026 7,00 ST',
            ],
            'text' => '',
        ]];
        $expectedName = $durchfuehrung . ' G352-1220-206-0000-06-1';

        $service = app(OrderAiScanService::class);
        $serviceReflection = new ReflectionClass($service);
        $serviceMethod = $serviceReflection->getMethod('parseGrobItemsFromPages');
        $serviceMethod->setAccessible(true);
        $serviceItems = $serviceMethod->invoke($service, $pages);

        $rulesParser = app(OrderAiDigitalPdfRulesParser::class);
        $rulesReflection = new ReflectionClass($rulesParser);
        $rulesMethod = $rulesReflection->getMethod('parseGrobItemsFromPages');
        $rulesMethod->setAccessible(true);
        $rulesItems = $rulesMethod->invoke($rulesParser, $pages);

        $this->assertSame($expectedName, $serviceItems[0]['product_name']);
        $this->assertSame($expectedName, $rulesItems[0]['product_name']);
        $this->assertStringStartsNotWith('ST ', $serviceItems[0]['product_name']);
        $this->assertStringStartsNotWith('ST ', $rulesItems[0]['product_name']);
    }

    public function test_extract_grob_netto_line_total_ignores_stuck_standalone_one_for_compact_eur_unit(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractGrobNettoLineTotal');
        $method->setAccessible(true);

        $this->assertSame(127.8, $method->invoke($service, 'Nettopreis 42,60 EURST 1 127,80'));
        $this->assertSame(302.39, $method->invoke($service, 'Nettopreis 18,90 EURST 1 302,39'));
    }

    public function test_post_process_profile_payload_for_grob_keeps_product_name_until_zeichnung_and_ignores_drawing_reference_line(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512120662.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512120662',
                '10 4008746 6,00 ST',
                'Platte',
                'SVTP1841-01',
                'Zeichnung SVTP1841-01 mit Revisionsstand 01',
                'Werkstoff: 16MnCrS5',
                'Bruttopreis 19,20 EUR ST 1 115,20',
                'Ruesten/Termin abs. 45,00 EUR 45,00',
                'Nettopreis 26,70 EUR ST 1 160,20',
                'Lieferdatum: 09.07.2026 6,00 ST',
                'PLACA DE ACO; USINADA; UTILIZADA EM DISPOSITIVO',
                'DIMENSOES 70mm x 35mm x 12mm',
                'REF. DES. SVTP1841-01',
                '*********************************** ACHTUNG * *************************************',
            ],
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512120662.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 10;
        $payload['items'][0]['product_code'] = '4008746';
        $payload['items'][0]['product_name'] = 'Platte SVTP1841-01 Ruesten/Termin abs. 45,00 EUR 45,00 PLACA DE ACO; USINADA; UTILIZADA EM DISPOSITIVO';
        $payload['items'][0]['note'] = '';

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('Platte SVTP1841-01', $payload['items'][0]['product_name']);
        $this->assertSame('', $payload['items'][0]['drawing_reference']);
        $this->assertSame('16MnCrS5', $payload['items'][0]['material_hint']);
        $this->assertSame('', $payload['items'][0]['note']);
        $this->assertStringNotContainsString('Ruesten/Termin abs.', $payload['items'][0]['product_name']);
    }

    public function test_post_process_profile_payload_for_grob_uses_first_row_as_product_code_and_second_and_third_rows_as_product_name(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $traeger = hex2bin('5472c3a4676572');
        $sourcePath = 'order-ai-scans/4512120888.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512120888',
                '10 6482044 1,00 ST',
                $traeger,
                'GCU-040-210-01-GM5511/1-1',
                'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00',
                'Nettopreis 10,00 EUR ST 1 10,00',
                'Lieferdatum: 09.07.2026 1,00 ST',
                '*********************************** ACHTUNG * *************************************',
            ],
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512120888.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 10;
        $payload['items'][0]['product_code'] = 'GCU-040-210-01-GM5511/1-1';
        $payload['items'][0]['product_name'] = $traeger . ' GCU-040-210-01-GM5511/1-1 Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00';
        $payload['items'][0]['drawing_reference'] = 'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00';
        $payload['items'][0]['note'] = 'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00';

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('6482044', $payload['items'][0]['product_code']);
        $this->assertSame($traeger . ' GCU-040-210-01-GM5511/1-1', $payload['items'][0]['product_name']);
        $this->assertSame('', $payload['items'][0]['drawing_reference']);
        $this->assertStringNotContainsString('Zeichnung', $payload['items'][0]['note']);
    }

    public function test_post_process_profile_payload_for_grob_prefers_parsed_name_rows_even_when_ai_item_identity_is_wrong(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $traeger = hex2bin('5472c3a4676572');
        $sourcePath = 'order-ai-scans/4512120889.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512120889',
                '10 6482044 1,00 ST',
                $traeger,
                'GCU-040-210-01-GM5511/1-1',
                'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00',
                'Kontierung: GM5511',
                'Werkstoff: S235JR',
                'Nettopreis 199,00 EUR ST 1 199,00',
                'Lieferdatum: 25.06.2026 1,00 ST',
                '*********************************** ACHTUNG * *************************************',
            ],
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512120889.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 0;
        $payload['items'][0]['product_code'] = 'GCU-040-210-01-GM5511/1-1';
        $payload['items'][0]['product_name'] = $traeger;
        $payload['items'][0]['drawing_reference'] = 'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00';
        $payload['items'][0]['note'] = 'Kontierung: GM5511 | Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00';

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('6482044', $payload['items'][0]['product_code']);
        $this->assertSame($traeger . ' GCU-040-210-01-GM5511/1-1', $payload['items'][0]['product_name']);
        $this->assertSame(1.0, (float) $payload['items'][0]['quantity']);
        $this->assertSame('KO', $payload['items'][0]['unit']);
        $this->assertStringNotContainsString('Zeichnung', $payload['items'][0]['product_name']);
    }

    public function test_post_process_profile_payload_for_grob_keeps_hyphenated_code_without_spaces_in_product_name(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512120890.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512120890',
                '40 8080808 8,00 ST',
                'Platte',
                'GM7258/06-1350-75/1-2',
                'Zeichnung GM7258/06-1350-75/1-2 mit Revisionsstand 00',
                'Nettopreis 199,00 EUR ST 1 199,00',
                'Lieferdatum: 25.06.2026 8,00 ST',
                '*********************************** ACHTUNG * *************************************',
            ],
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512120890.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('postProcessProfilePayload');
        $method->setAccessible(true);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 40;
        $payload['items'][0]['product_code'] = '8080808';
        $payload['items'][0]['product_name'] = 'Platte GM7258/06 - 1350 - 75/1 - 2';
        $payload['items'][0]['note'] = '';

        $payload = $method->invoke($service, $scan, $payload);

        $this->assertSame('Platte GM7258/06-1350-75/1-2', $payload['items'][0]['product_name']);
        $this->assertStringNotContainsString('GM7258/06 - 1350 - 75/1 - 2', $payload['items'][0]['product_name']);
    }

    public function test_post_process_profile_payload_for_grob_preserves_correct_total_from_compact_eur_unit_row(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512121090.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                'GROB-WERKE GmbH & Co. KG',
                'BESTELLUNG',
                'Bestell-Nr.: 4512121090',
                '20 6449473 3,00 ST',
                'Klotz',
                'GM4395/01-70-126/1-2-18',
                'Nettopreis 42,60 EURST 1 127,80',
                'Lieferdatum: 09.07.2026 3,00 ST',
                'Nettowert: 127,80',
                'Gesamtbetrag: 127,80',
                '*********************************** ACHTUNG * *************************************',
            ],
            [
                'Warenbegleitschein',
                'Lieferant',
                '7307181, Trendy d.o.o.',
                'GROB-Identnr.:',
                'GM4395/01-70-126/1-2-18',
            ],
        ]));

        $scan = new OrderAiScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512121090.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $postProcessMethod = $reflection->getMethod('postProcessProfilePayload');
        $postProcessMethod->setAccessible(true);
        $normalizeMethod = $reflection->getMethod('normalizePayload');
        $normalizeMethod->setAccessible(true);

        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['items'][0]['line_number'] = 20;
        $payload['items'][0]['product_code'] = '6449473';
        $payload['items'][0]['product_name'] = 'Klotz GM4395/01-70-126/1-2-18';
        $payload['items'][0]['quantity'] = 3;
        $payload['items'][0]['unit'] = 'ST';
        $payload['items'][0]['unit_price'] = 42.60;
        $payload['items'][0]['line_total'] = 127.80;
        $payload['summary']['subtotal'] = 0;
        $payload['summary']['grand_total'] = 0;

        $normalized = $normalizeMethod->invoke(
            $service,
            $postProcessMethod->invoke($service, $scan, $payload)
        );

        $this->assertSame(42.6, $normalized['items'][0]['unit_price']);
        $this->assertSame(127.8, $normalized['items'][0]['line_total']);
        $this->assertSame('KO', $normalized['items'][0]['unit']);
        $this->assertSame(127.8, $normalized['summary']['subtotal']);
        $this->assertSame(127.8, $normalized['summary']['grand_total']);
    }

    public function test_grob_document_preparation_stops_before_warenbegleitschein_attachments_when_attention_marker_is_missing(): void
    {
        $prepared = app(OrderAiDocumentPreparationService::class)->prepareDocument(
            'grob',
            '4512120662.pdf',
            'application/pdf',
            $this->buildSyntheticPdf([
                [
                    'GROB-WERKE GmbH & Co. KG',
                    'BESTELLUNG',
                    'Bestell-Nr.: 4512120662',
                    '10 4008746 6,00 ST',
                    'Platte',
                    'SVTP1841-01',
                    'Zeichnung SVTP1841-01 mit Revisionsstand 01',
                    'Werkstoff: 16MnCrS5',
                    'Nettopreis 26,70 EUR ST 1 160,20',
                    'Lieferdatum: 09.07.2026 6,00 ST',
                ],
                [
                    'Nettowert: 160,20',
                    'Gesamtbetrag: 160,20',
                ],
                [
                    'Lieferant',
                    '7307181, Trendy d.o.o.',
                    'Warenbegleitschein',
                    'GROB-Identnr.:',
                    'SVTP1841-01',
                    'Material:',
                    '4008746',
                ],
            ])
        );

        $this->assertSame(3, $prepared['source_page_count']);
        $this->assertSame(2, $prepared['effective_page_count']);
        $this->assertSame(
            OrderAiDocumentPreparationService::GROB_ATTACHMENT_PAGE_LIMIT_REASON,
            $prepared['page_processing_limit_reason']
        );
        $this->assertStringContainsString('"page": 2', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('"page": 3', $prepared['provider_input_text']);
        $this->assertStringNotContainsString('GROB-Identnr.:', $prepared['provider_input_text']);
    }

    public function test_finalize_extraction_result_applies_validation_metadata_for_digital_pdfs(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $sourcePath = 'order-ai-scans/4512108386.pdf';
        $bytes = $this->buildSyntheticPdf($this->grobPageFixture());
        Storage::disk('local')->put($sourcePath, $bytes);

        $scan = $this->makeInMemoryScan([
            'document_profile' => 'grob',
            'source_file_name' => '4512108386.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);
        $service = app(OrderAiScanService::class);
        $payload = $this->basePayload();
        $payload['order']['supplier_name'] = 'GROB-WERKE GmbH & Co. KG';
        $payload['order']['page_count'] = 17;
        $payload['items'][0]['line_number'] = 90;
        $payload['items'][0]['product_code'] = '5842536';
        $payload['items'][0]['quantity'] = 16;
        $payload['items'][0]['unit'] = 'ST';
        $payload['items'][0]['unit_price'] = 5.16;
        $payload['items'][0]['line_total'] = 82.56;
        $payload['summary']['subtotal'] = 82.56;
        $payload['summary']['grand_total'] = 82.56;
        $finalized = $service->finalizeExtractionResult($scan, [
            'provider' => 'mock',
            'model' => 'mock',
            'raw_response' => [],
            'normalized_payload' => $payload,
            'prepared_document' => app(OrderAiDocumentPreparationService::class)->prepareDocument(
                'grob',
                '4512108386.pdf',
                'application/pdf',
                $bytes
            ),
            'extraction_duration_ms' => 12,
            'ai_duration_ms' => 0,
        ], null, false);

        $this->assertSame('digital', $finalized['extraction_method']);
        $this->assertSame(5, data_get($finalized, 'normalized_payload.order.page_count'));
        $this->assertSame(6.13, data_get($finalized, 'normalized_payload.items.0.unit_price'));
        $this->assertSame(98.15, data_get($finalized, 'normalized_payload.items.0.line_total'));
        $this->assertGreaterThanOrEqual(0, $finalized['validation_duration_ms']);
        $this->assertNotEmpty($finalized['validation_warnings']);
        $this->assertIsArray($finalized['extraction_payload']);
        $this->assertSame('digital', data_get($finalized, 'extraction_payload.extraction_method'));
    }

    public function test_execute_extraction_prefers_local_rules_parser_for_digital_grob_pdf(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/4512123001.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'GROB-WERKE GmbH & Co. KG',
            'BESTELLUNG',
            'Bestell-Nr.: 4512123001',
            'incl. Verpackung Zahlungsbed.: Trenkenschuh, Paul / 5358 Ekg:',
            'Kunden-Nr.: Lieferbed.: FCA, Trendy d.o.o. , 72290 Novi Travnik, Incoterms',
            '2010 Abweichend davon: Versicherung:',
            'incl. Versicherung nach ICC A Verpackung:',
            '040 Bitte weisen Sie in Ihrer Auftragsbestatigung / Rechnung die praferenzbegunstigte Ursprungsware aus.',
            '20 6449473 3,00 ST',
            'Klotz',
            'GM4395/01-70-126/1-2-18',
            'Zeichnung GM4395/01-70-126/1-2-18 mit Revisionsstand 01',
            'Kontierung: U38871-GM7260',
            'Werkstoff: C45',
            'Preiseinheit ST',
            'Nettopreis 42,60 EUR ST 1 127,80',
            'Lieferdatum: 18.06.2026 3,00 ST',
            'Lackierung: RAL 7035 Lichtgrau Glatt',
            'Nettowert 127,80',
            'Gesamtbetrag 127,80',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'grob',
            'source_file_name' => '4512123001.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('local-digital-pdf-rules-v1', $result['model']);
        $this->assertSame(0, $result['ai_duration_ms']);
        $this->assertSame('4512123001', data_get($result, 'normalized_payload.order.external_document_number'));
        $this->assertSame('Trendy d.o.o.', data_get($result, 'normalized_payload.order.customer_name'));
        $this->assertSame('GROB-WERKE GmbH & Co. KG', data_get($result, 'normalized_payload.order.supplier_name'));
        $this->assertSame('040', data_get($result, 'normalized_payload.order.requester_code'));
        $this->assertSame('', data_get($result, 'normalized_payload.order.note'));
        $this->assertSame('6449473', data_get($result, 'normalized_payload.items.0.product_code'));
        $this->assertSame('Klotz GM4395/01-70-126/1-2-18', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertSame('KO', data_get($result, 'normalized_payload.items.0.unit'));
        $this->assertSame('18.06.2026', data_get($result, 'normalized_payload.items.0.delivery_deadline'));
        $this->assertSame(42.6, data_get($result, 'normalized_payload.items.0.unit_price'));
        $this->assertSame(127.8, data_get($result, 'normalized_payload.items.0.line_total'));
        $this->assertSame('', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertSame(127.8, data_get($result, 'normalized_payload.summary.subtotal'));
    }

    public function test_digital_grob_parser_deduplicates_repeated_product_name_rows(): void
    {
        $parser = app(OrderAiDigitalPdfRulesParser::class);
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('parseGrobItemsFromText');
        $method->setAccessible(true);

        $items = $method->invoke($parser, implode("\n", [
            'GROB-WERKE GmbH & Co. KG',
            'BESTELLUNG',
            'Bestell-Nr.: 4512100377',
            '20 6449473 3,00 ST',
            'Klotz',
            'GM4395/01-70-126/1-2-18',
            'Klotz',
            'GM4395/01-70-126/1-2-18',
            'Zeichnung GM4395/01-70-126/1-2-18 mit Revisionsstand 01',
            'Nettopreis 42,60 EUR ST 1 127,80',
            'Lieferdatum: 18.06.2026 3,00 ST',
        ]));

        $this->assertSame('Klotz GM4395/01-70-126/1-2-18', $items[0]['product_name']);
    }

    public function test_execute_extraction_prefers_local_rules_parser_for_trendy_de_pdf(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000675.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'Trendy Germany GmbH',
            'Lieferant:',
            'Trendy doo',
            'Bratstvo 11',
            'Anlieferadresse:',
            'Trendy Germany 21',
            'Datum 9. 5. 2026.',
            'Liefertermin 1. 6. 2026.',
            'Person responsible Edina Duzan',
            'Bestellung 26-020-000675',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1 65070911 Halter 884698 2,00 STU 308,30 0,00 616,60',
            '2 65070912 Halter 884699 spiegelbildlich 2,00 STU 281,30 0,00 562,60',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000675.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('26-020-000675', data_get($result, 'normalized_payload.order.external_document_number'));
        $this->assertSame('Trendy Germany GmbH', data_get($result, 'normalized_payload.order.customer_name'));
        $this->assertSame('Trendy Germany GmbH-21', data_get($result, 'normalized_payload.order.supplier_name'));
        $this->assertSame('Trendy Germany 21', data_get($result, 'normalized_payload.order.receiver_name'));
        $this->assertSame('Edina Duzan', data_get($result, 'normalized_payload.order.contact_name'));
        $this->assertSame('1. 6. 2026.', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertSame('1. 6. 2026.', data_get($result, 'normalized_payload.items.0.delivery_deadline'));
        $this->assertSame('1. 6. 2026.', data_get($result, 'normalized_payload.items.1.delivery_deadline'));
        $this->assertSame('65070911', data_get($result, 'normalized_payload.items.0.product_code'));
        $this->assertSame('Halter 884698', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertSame('KO', data_get($result, 'normalized_payload.items.0.unit'));
        $this->assertSame(1179.2, data_get($result, 'normalized_payload.summary.subtotal'));
    }

    public function test_execute_extraction_uses_lieferdatum_date_after_label_when_datum_is_on_same_line(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000959.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            '27. 6. 2026.',
            '28. 6. 2026.',
            'Trendy Germany GmbH',
            'Lieferant:',
            'Trendy doo',
            'Anlieferadresse:',
            'Trendy Germany 21',
            'Person responsible Edina Duzan',
            'Bestellung 26-020-000959',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1 65018647 Lagerzapfen 1,00 STU 80,70 0,00 80,70',
            '830806',
            '2 65037490 Platte 2,00 STU 34,65 0,00 69,30',
            '849341 a',
            '3 65039927 Halterung 1,00 STU 139,20 0,00 139,20',
            '851655 a',
            '4 65011594 Einsatz 2,00 STU 350,00 0,00 700,00',
            '823926 a',
            '989,20 Total',
            'Gesamtpreis EUR',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000959.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('28. 6. 2026.', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertCount(4, data_get($result, 'normalized_payload.items'));
        $this->assertSame([1, 2, 3, 4], array_column(data_get($result, 'normalized_payload.items'), 'line_number'));
        $this->assertSame('28. 6. 2026.', data_get($result, 'normalized_payload.items.0.delivery_deadline'));
        $this->assertSame('Lagerzapfen', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertSame('830806', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertSame('Einsatz', data_get($result, 'normalized_payload.items.3.product_name'));
        $this->assertSame('823926 a', data_get($result, 'normalized_payload.items.3.note'));
        $this->assertSame(989.2, data_get($result, 'normalized_payload.summary.subtotal'));
    }

    public function test_execute_extraction_keeps_blank_header_liefertermin_and_uses_item_dates(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            '27. 6. 2026.',
            'Trendy Germany GmbH',
            'Lieferant:',
            'Trendy doo',
            'Anlieferadresse:',
            'Trendy Germany 21',
            'Person responsible Edina Duzan',
            'Bestellung 26-020-000963',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1 BYPR05C120030 Abdeckblech Oben 15,00 STU 34,65 0,00 519,75',
            'CHEM.NICKEL PLATED',
            'Liefertermin: 20.07.2026',
            '2 EVE280A675030 Gehaeuseblock 6,00 STU 207,10 0,00 1.242,60',
            'WARM BROWNED',
            'Liefertermin: 27.07.2026',
            '1.762,35 Total',
            'Gesamtpreis EUR',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertCount(2, data_get($result, 'normalized_payload.items'));
        $this->assertSame('20.07.2026', data_get($result, 'normalized_payload.items.0.delivery_deadline'));
        $this->assertSame('27.07.2026', data_get($result, 'normalized_payload.items.1.delivery_deadline'));
        $this->assertSame('Abdeckblech Oben', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertSame('CHEM.NICKEL PLATED', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertSame(1762.35, data_get($result, 'normalized_payload.summary.subtotal'));
    }

    public function test_execute_extraction_does_not_use_date_before_blank_trendy_de_liefertermin_label(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            '27. 6. 2026. Liefertermin',
            'Trendy Germany GmbH',
            'Bestellung 26-020-000963',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1 BYPR05C120030 Abdeckblech Oben 15,00 STU 34,65 0,00 519,75',
            'CHEM.NICKEL PLATED',
            'Liefertermin: 20.07.2026',
            '2 EVE280A675030 Gehaeuseblock 6,00 STU 207,10 0,00 1.242,60',
            'WARM BROWNED',
            'Liefertermin: 27.07.2026',
            '1.762,35 Total',
            'Gesamtpreis EUR',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertSame('20.07.2026', data_get($result, 'normalized_payload.items.0.delivery_deadline'));
        $this->assertSame('27.07.2026', data_get($result, 'normalized_payload.items.1.delivery_deadline'));
    }

    public function test_execute_extraction_maps_trendy_de_amount_first_rows_across_page_breaks_to_item_dates(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000963.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                '27. 6. 2026.',
                'Trendy Germany GmbH',
                'Trendy doo',
                'Trendy Germany 45',
                'Datum',
                'Liefertermin',
                'Bestellung26-020-000963',
                'Edina Duzan',
                "Lieferant:\tAnlieferadresse:",
                "Artikel Nr.Pos.\tBeschreibung\tMengeEinheit EK-Preis\tBetragVAT %",
                "15,00\t34,65STU\t519,750,00BYPR05C120030 Abdeckblech Oben1",
                'CHEM.NICKEL PLATED',
                'Liefertermin: 20.07.2026',
                "6,00 207,10STU\t1.242,600,00EVE280A675030 Gehaeuseblock2",
                'WARM BROWNED',
                'Liefertermin: 27.07.2026',
                "1,00\t55,30STU\t55,300,00EVRB10B912110 Grundblech Alu3",
                'UNTREATED',
                'Liefertermin: 27.07.2026',
                "2,00\t38,05STU\t76,100,00EVRB10B912130 Grundblech Alu4",
                'UNTREATED',
                'Liefertermin: 27.07.2026',
                "2,00\t40,10STU\t80,200,00ALE002A422070 Anschlag5",
                'GALVANIZED BLUE',
                'Liefertermin: 27.07.2026',
                "4,00\t45,50STU\t182,000,00HS09A03060 Konsole schraeg6",
                'PAINT',
                'Liefertermin: 31.08.2026',
                "2,00 290,70STU\t581,400,00HS09B09170 Konsole kurz7",
                'PAINT',
                'Liefertermin: 31.08.2026',
                "1,00 220,30STU\t220,300,00EVRS10C91503L Endplattenfuehrung- Pl. breite 103-105mm8",
                'VERNICKELT AUF 50 YM UND POLIERT',
                'NICKEL 50MY+POLISH',
                'Liefertermin: 31.08.2026',
                "1,00 221,30STU\t221,300,00EVRS10C91520L Endplattenfuehrung- Pl. breite 140-142mm9",
                'NICKEL 50MY+POLISH',
                'Liefertermin: 31.08.2026',
                'Page1/2',
            ],
            [
                "Artikel Nr.Pos.\tBeschreibung\tMengeEinheit EK-Preis\tBetragVAT %",
                "2,00\t39,55STU\t79,100,00DN731970 STICK10",
                'BRASS',
                'Crtez N01814580',
                'Liefertermin: 07.09.2026',
                "2,00\t46,90STU\t93,800,00DN731973_A SIDE PLATE, RIGHT11",
                'ANODIZED',
                'Crtez N0181479A',
                'Liefertermin: 07.09.2026',
                "2,00\t46,90STU\t93,800,00DN731976_A SIDE PLATE, LEFT12",
                'ANODIZED',
                'Crtez N0181491A',
                'Liefertermin: 07.09.2026',
                "6,00\t26,70STU\t160,200,00DS1250A070070 Klemmhuelse13",
                'ANODIZED',
                'Liefertermin: 07.09.2026',
                "4,00\t26,00STU\t104,000,00EXCS35A190170 Endanschlag Fuehrungswelle14",
                'NICKEL PLATED',
                'Liefertermin: 07.09.2026',
                "8,00\t35,80STU\t286,400,00HAD125A011030 Lagerklotz15",
                'PAINT ACC. DRAWING',
                'Liefertermin: 07.09.2026',
                "4,00\t21,20STU\t84,800,00HAD125A030030 Haltekonsole bei Schwinge16",
                'GALVANIZED BLUE',
                'Liefertermin: 07.09.2026',
                "4,00\t99,30STU\t397,200,00HAD125A091010 Rollenachse DM 7017",
                'WARM BROWNED',
                'Liefertermin: 07.09.2026',
                "4,00\t18,90STU\t75,600,00HAD125A095020 Haltedorn Dm20-15018",
                'GALVANIZED BLUE',
                'Liefertermin: 07.09.2026',
                "2,00 278,70STU\t557,400,00HS10A01010 Antriebsachse Dm7019",
                'WARM BROWNED',
                'Liefertermin: 07.09.2026',
                "4,00\t45,50STU\t182,000,00HS10A03060 Konsole 2 schraeg20",
                'PAINT',
                'Liefertermin: 07.09.2026',
                "4,00\t19,40STU\t77,600,002154023601 Aufnahme21",
                'GALVANIZED BLUE',
                'Liefertermin: 07.09.2026',
                "Total\t5.370,85",
                'Page2/2',
            ],
        ]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000963.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);
        $items = data_get($result, 'normalized_payload.items');

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('Trendy Germany GmbH-45', data_get($result, 'normalized_payload.order.supplier_name'));
        $this->assertSame('', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertCount(21, $items);
        $this->assertSame(range(1, 21), array_column($items, 'line_number'));
        $this->assertSame('BYPR05C120030', data_get($items, '0.product_code'));
        $this->assertSame('20.07.2026', data_get($items, '0.delivery_deadline'));
        $this->assertSame('EVRS10C91520L', data_get($items, '8.product_code'));
        $this->assertSame('31.08.2026', data_get($items, '8.delivery_deadline'));
        $this->assertSame('DN731970', data_get($items, '9.product_code'));
        $this->assertSame('07.09.2026', data_get($items, '9.delivery_deadline'));
        $this->assertSame('HAD125A091010', data_get($items, '16.product_code'));
        $this->assertSame('Rollenachse DM 70', data_get($items, '16.product_name'));
        $this->assertSame('2154023601', data_get($items, '20.product_code'));
        $this->assertSame('07.09.2026', data_get($items, '20.delivery_deadline'));
    }

    public function test_execute_extraction_splits_trendy_de_underscore_codes_and_keeps_crtez_notes(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000959.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            '27. 6. 2026.',
            '28. 6. 2026.',
            'Trendy Germany GmbH',
            'Lieferant:',
            'Trendy doo',
            'Anlieferadresse:',
            'Trendy Germany 21',
            'Person responsible Edina Duzan',
            'Bestellung 26-020-000959',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '10 DN731970 STICK 2,00 STU 39,55 0,00 79,10',
            'BRASS',
            'Crtež N01814580',
            'Liefertermin: 07.09.2026',
            '11 DN731973_A SIDE PLATE, RIGHT 2,00 STU 46,90 0,00 93,80',
            'ANODIZED',
            'Crtež N0181479A',
            'Liefertermin: 07.09.2026',
            '12 DN731976_A SIDE PLATE, LEFT 2,00 STU 46,90 0,00 93,80',
            'ANODIZED',
            'Crtež N0181491A',
            'Liefertermin: 07.09.2026',
            '266,70 Total',
            'Gesamtpreis EUR',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000959.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('28. 6. 2026.', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertSame([10, 11, 12], array_column(data_get($result, 'normalized_payload.items'), 'line_number'));
        $this->assertSame('DN731970', data_get($result, 'normalized_payload.items.0.product_code'));
        $this->assertSame('STICK', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertSame('BRASS | Crtež N01814580', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertSame('DN731973_A', data_get($result, 'normalized_payload.items.1.product_code'));
        $this->assertSame('SIDE PLATE, RIGHT', data_get($result, 'normalized_payload.items.1.product_name'));
        $this->assertSame('ANODIZED | Crtež N0181479A', data_get($result, 'normalized_payload.items.1.note'));
        $this->assertSame('DN731976_A', data_get($result, 'normalized_payload.items.2.product_code'));
        $this->assertSame('SIDE PLATE, LEFT', data_get($result, 'normalized_payload.items.2.product_name'));
        $this->assertSame('ANODIZED | Crtež N0181491A', data_get($result, 'normalized_payload.items.2.note'));
        $this->assertSame(266.7, data_get($result, 'normalized_payload.summary.subtotal'));
    }

    public function test_execute_extraction_keeps_parsing_trendy_de_items_after_page_subtotal(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000958.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([
            [
                'Trendy Germany GmbH',
                'Lieferant:',
                'Trendy doo',
                'Anlieferadresse:',
                'Trendy Germany 21',
                'Datum 9. 5. 2026.',
                'Liefertermin 1. 6. 2026.',
                'Person responsible Edina Duzan',
                'Bestellung 26-020-000958',
                'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
                '1 65070911 Halter 884698 2,00 STU 308,30 0,00 616,60',
                '616,60 Total',
            ],
            [
                'Trendy Germany GmbH',
                'Page 2/2',
                'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
                '2 65070912 Halter 884699 spiegelbildlich 2,00 STU 281,30 0,00 562,60',
                '1.179,20 Total',
                'Gesamtpreis EUR',
            ],
        ]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000958.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('26-020-000958', data_get($result, 'normalized_payload.order.external_document_number'));
        $this->assertCount(2, data_get($result, 'normalized_payload.items'));
        $this->assertSame('65070911', data_get($result, 'normalized_payload.items.0.product_code'));
        $this->assertSame('65070912', data_get($result, 'normalized_payload.items.1.product_code'));
        $this->assertSame('Halter 884699 spiegelbildlich', data_get($result, 'normalized_payload.items.1.product_name'));
        $this->assertSame(562.6, data_get($result, 'normalized_payload.items.1.line_total'));
        $this->assertSame(1179.2, data_get($result, 'normalized_payload.summary.subtotal'));
        $this->assertSame(2, data_get($result, 'raw_response.matched_item_count'));
    }

    public function test_execute_extraction_splits_trendy_de_items_embedded_after_page_break_text(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000958.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'Trendy Germany GmbH',
            'Liefertermin 26. 6. 2026.',
            'Bestellung 26-020-000958',
            'Pos. Artikel Nr. Beschreibung Menge Einheit EK-Preis VAT % Betrag',
            '1049576 595,20 0,00 Betrag 16,00 VAT % STU 37,20',
            'Hauptplatte 9 Graviranje',
            'Brueniert Page 10 1049658 Stossdaempferanschlag Graviranje Brueniert 30,00 STU 4,90 0,00 147,00 11 1049798 Schwenkantriebbefestigung Graviranje 5,00 STU 50,00 0,00 250,00',
            '992,20 Total',
            'Gesamtpreis EUR',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000958.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertCount(3, data_get($result, 'normalized_payload.items'));
        $this->assertSame(9, data_get($result, 'normalized_payload.items.0.line_number'));
        $this->assertSame('1049576', data_get($result, 'normalized_payload.items.0.product_code'));
        $this->assertSame('Hauptplatte', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertStringNotContainsString('Page', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertStringNotContainsString('9', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertStringContainsString('Graviranje', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertSame(10, data_get($result, 'normalized_payload.items.1.line_number'));
        $this->assertSame('1049658', data_get($result, 'normalized_payload.items.1.product_code'));
        $this->assertSame('Stossdaempferanschlag', data_get($result, 'normalized_payload.items.1.product_name'));
        $this->assertSame(30.0, data_get($result, 'normalized_payload.items.1.quantity'));
        $this->assertSame(147.0, data_get($result, 'normalized_payload.items.1.line_total'));
        $this->assertSame(11, data_get($result, 'normalized_payload.items.2.line_number'));
        $this->assertSame('1049798', data_get($result, 'normalized_payload.items.2.product_code'));
        $this->assertSame('Schwenkantriebbefestigung', data_get($result, 'normalized_payload.items.2.product_name'));
        $this->assertSame(250.0, data_get($result, 'normalized_payload.items.2.line_total'));
        $this->assertSame(992.2, data_get($result, 'normalized_payload.summary.subtotal'));
    }

    public function test_execute_extraction_parses_trendy_de_code_amount_row_split_from_description(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000945.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'Trendy Germany GmbH',
            'Kaiserstraße 150',
            '51643 Gummersbach',
            'Germany',
            'Trendy',
            'Trendy doo',
            'ID: 236318900009',
            'Bratstvo 11',
            '26-020-000945 Edina Duzan',
            'Trendy Germany 16 Liefertermin 25. 6. 2026.',
            'Datum 24. 8. 2026. Deliver via Bestellung',
            'Person responsible',
            'Anlieferadresse: Lieferant: Artikel Nr. Pos. Beschreibung Menge EK-Preis Einheit',
            '503600720 231,00 0,00 Betrag 10,00 VAT % STU 23,10',
            'Hülse 1',
            '231,00 Total',
            'Steuer 231,00 0,00',
            'Gesamtpreis EUR',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for digital rules-first extraction.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'trendy_de',
            'source_file_name' => 'Bestellung_26-020-000945.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('26-020-000945', data_get($result, 'normalized_payload.order.external_document_number'));
        $this->assertSame('Trendy doo', data_get($result, 'normalized_payload.order.receiver_name'));
        $this->assertSame('Edina Duzan', data_get($result, 'normalized_payload.order.contact_name'));
        $this->assertSame('25. 6. 2026.', data_get($result, 'normalized_payload.order.delivery_deadline'));
        $this->assertSame('503600720', data_get($result, 'normalized_payload.items.0.product_code'));
        $this->assertSame('Hülse', data_get($result, 'normalized_payload.items.0.product_name'));
        $this->assertSame(1, data_get($result, 'normalized_payload.items.0.line_number'));
        $this->assertSame(10.0, data_get($result, 'normalized_payload.items.0.quantity'));
        $this->assertSame('KO', data_get($result, 'normalized_payload.items.0.unit'));
        $this->assertSame(23.1, data_get($result, 'normalized_payload.items.0.unit_price'));
        $this->assertSame(231.0, data_get($result, 'normalized_payload.items.0.line_total'));
        $this->assertSame('', data_get($result, 'normalized_payload.items.0.note'));
        $this->assertSame(231.0, data_get($result, 'normalized_payload.summary.subtotal'));
        $this->assertSame(231.0, data_get($result, 'normalized_payload.summary.grand_total'));
    }

    public function test_execute_extraction_detects_trendy_de_profile_when_stored_profile_is_missing(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000945.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'Trendy Germany GmbH',
            '26-020-000945 Edina Duzan',
            'Trendy Germany 16 Liefertermin 25. 6. 2026.',
            'Anlieferadresse: Lieferant: Artikel Nr. Pos. Beschreibung Menge EK-Preis Einheit',
            '503600720 231,00 0,00 Betrag 10,00 VAT % STU 23,10',
            'Hülse 1',
            '231,00 Total',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called when missing profile can be detected.');
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => '',
            'source_file_name' => 'Bestellung_26-020-000945.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('digital_pdf_rules', $result['provider']);
        $this->assertSame('trendy_de', $result['document_profile']);
        $this->assertSame('matched', data_get($result, 'parser_payload.status'));
        $this->assertSame('trendy_de', data_get($result, 'parser_payload.profile'));
        $this->assertSame('26-020-000945', data_get($result, 'normalized_payload.order.external_document_number'));
        $this->assertSame('503600720', data_get($result, 'normalized_payload.items.0.product_code'));
    }

    public function test_run_extraction_overwrites_initial_billed_tokens_with_page_based_value_for_local_rules_parser(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => false,
        ]);

        $sourcePath = 'order-ai-scans/4512123002.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'GROB-WERKE GmbH & Co. KG',
            'BESTELLUNG',
            'Bestell-Nr.: 4512123002',
            '20 6449473 3,00 ST',
            'Klotz',
            'GM4395/01-70-126/1-2-18',
            'Zeichnung GM4395/01-70-126/1-2-18 mit Revisionsstand 01',
            'Werkstoff: C45',
            'Preiseinheit ST',
            'Nettopreis 42,60 EUR ST 1 127,80',
            'Lieferdatum: 18.06.2026 3,00 ST',
            'Nettowert 127,80',
            'Gesamtbetrag 127,80',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new RuntimeException('AI provider should not be called for local digital rules extraction.');
            }
        });

        app()->instance(PantheonOrderTransferService::class, new class extends PantheonOrderTransferService {
            public function isTransferReady(array $normalizedPayload): bool
            {
                return false;
            }
        });

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('runExtraction');
        $method->setAccessible(true);
        $scan = $this->makeInMemoryScan([
            'id' => 888,
            'status' => 'extracting',
            'provider' => 'openrouter',
            'document_profile' => 'grob',
            'page_count' => 17,
            'billed_tokens' => 17,
            'source_file_name' => '4512123002.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = $method->invoke($service, $scan);

        $this->assertSame($scan, $result);
        $this->assertSame('completed', $scan->capturedForceFill['status']);
        $this->assertSame('digital_pdf_rules', $scan->capturedForceFill['provider']);
        $this->assertSame('local-digital-pdf-rules-v1', $scan->capturedForceFill['model']);
        $this->assertSame(10, $scan->capturedForceFill['billed_tokens']);
        $this->assertSame(0.0, $scan->capturedForceFill['credits_spent']);
    }

    public function test_ai_provider_billed_tokens_keep_page_based_estimate_instead_of_raw_usage_tokens(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveExtractionBilledTokens');
        $method->setAccessible(true);

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
        ]);
        $result = [
            'provider' => 'openrouter',
            'credits_spent' => 4.026,
            'ai_duration_ms' => 12000,
            'raw_response' => [
                'usage' => [
                    'total_tokens' => 4026,
                ],
            ],
        ];

        $billedTokens = $method->invoke($service, $scan, $result, 17);

        $this->assertSame(17, $billedTokens);
    }

    public function test_execute_extraction_falls_back_to_provider_when_rules_first_is_disabled(): void
    {
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.digital_pdf.rules_first' => false,
        ]);

        app()->instance(OpenRouterOrderAiScanProvider::class, new class($this->basePayload()) implements OrderAiScanProvider {
            public function __construct(
                private readonly array $payload
            ) {
            }

            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                return [
                    'provider' => 'openrouter',
                    'model' => 'demo-model',
                    'provider_task_id' => 'task-rules-disabled',
                    'credits_spent' => 0.1234,
                    'raw_response' => [
                        'id' => 'task-rules-disabled',
                    ],
                    'normalized_payload' => $this->payload,
                    'ai_duration_ms' => 91,
                ];
            }
        });

        $scan = new OrderAiScan([
            'provider' => 'openrouter',
            'document_profile' => 'grob',
            'source_file_name' => 'noop.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => 'order-ai-scans/noop.pdf',
        ]);

        $result = app(OrderAiScanService::class)->executeExtraction($scan);

        $this->assertSame('openrouter', $result['provider']);
        $this->assertSame('demo-model', $result['model']);
        $this->assertSame('task-rules-disabled', $result['provider_task_id']);
    }

    public function test_run_extraction_persists_openrouter_and_parser_debug_payloads(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => true,
            'ai-order-scan.digital_pdf.fallback_to_ai' => true,
        ]);

        $sourcePath = 'order-ai-scans/Bestellung_26-020-000945-empty.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf([[
            'Trendy Germany GmbH',
            'Bestellung 26-020-000945',
            'Person responsible Edina Duzan',
            'Keine Artikellinien',
        ]]));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class($this->basePayload()) implements OrderAiScanProvider {
            public function __construct(
                private readonly array $payload
            ) {
            }

            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                return [
                    'provider' => 'openrouter',
                    'model' => 'demo-model',
                    'provider_task_id' => 'task-debug-payload',
                    'credits_spent' => 0.1234,
                    'raw_response' => [
                        'id' => 'task-debug-payload',
                        'choices' => [],
                    ],
                    'normalized_payload' => $this->payload,
                    'ai_duration_ms' => 91,
                ];
            }
        });

        app()->instance(PantheonOrderTransferService::class, new class extends PantheonOrderTransferService {
            public function isTransferReady(array $normalizedPayload): bool
            {
                return false;
            }
        });

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $columns = $reflection->getProperty('orderAiScanColumns');
        $columns->setAccessible(true);
        $columns->setValue($service, [
            'document_profile' => true,
            'page_count' => true,
            'billed_tokens' => true,
            'openrouter_payload' => true,
            'parser_payload' => true,
            'extraction_method' => true,
            'raw_extracted_text' => true,
            'extraction_payload' => true,
            'validation_warnings' => true,
            'validation_errors' => true,
            'confidence_score' => true,
            'extraction_duration_ms' => true,
            'ai_duration_ms' => true,
            'validation_duration_ms' => true,
        ]);
        $method = $reflection->getMethod('runExtraction');
        $method->setAccessible(true);
        $scan = $this->makeInMemoryScan([
            'id' => 991,
            'status' => 'extracting',
            'provider' => 'openrouter',
            'document_profile' => '',
            'source_file_name' => 'Bestellung_26-020-000945-empty.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $method->invoke($service, $scan);

        $this->assertSame('openrouter', $scan->capturedForceFill['provider']);
        $this->assertSame('trendy_de', $scan->capturedForceFill['document_profile']);
        $this->assertSame('task-debug-payload', $scan->capturedForceFill['openrouter_payload']['id']);
        $this->assertSame('not_matched', $scan->capturedForceFill['parser_payload']['status']);
        $this->assertSame('trendy_de', $scan->capturedForceFill['parser_payload']['profile']);
        $this->assertStringContainsString(
            'Bestellung 26-020-000945',
            $scan->capturedForceFill['parser_payload']['source_text']['searchable_text']
        );
    }

    public function test_run_extraction_persists_raw_provider_response_when_provider_throws_exception(): void
    {
        config(['ai-order-scan.provider' => 'openrouter']);

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new class('Provider returned malformed JSON.') extends RuntimeException {
                    public function context(): array
                    {
                        return [
                            'provider' => 'openrouter',
                            'model' => 'demo-model',
                            'provider_task_id' => 'task-123',
                            'raw_response' => [
                                'id' => 'task-123',
                                'http_status' => 200,
                                'body' => '{"oops":true}',
                            ],
                        ];
                    }
                };
            }
        });

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('runExtraction');
        $method->setAccessible(true);
        $scan = $this->makeInMemoryScan([
            'id' => 321,
            'status' => 'extracting',
            'provider' => 'openrouter',
            'document_profile' => '',
        ]);

        $result = $method->invoke($service, $scan);

        $this->assertSame($scan, $result);
        $this->assertSame('failed', $scan->capturedForceFill['status']);
        $this->assertSame('task-123', $scan->capturedForceFill['provider_task_id']);
        $this->assertSame('demo-model', $scan->capturedForceFill['model']);
        $this->assertSame('task-123', $scan->capturedForceFill['raw_provider_response']['id']);
        $this->assertSame(
            'Provider returned malformed JSON.',
            $scan->capturedForceFill['raw_provider_response']['_failure']['message']
        );
    }

    public function test_run_extraction_failure_resets_tokens_and_uses_effective_grob_page_count(): void
    {
        Storage::fake('local');
        config([
            'ai-order-scan.provider' => 'openrouter',
            'ai-order-scan.storage_disk' => 'local',
            'ai-order-scan.digital_pdf.rules_first' => false,
        ]);

        $sourcePath = 'order-ai-scans/4512109402.pdf';
        Storage::disk('local')->put($sourcePath, $this->buildSyntheticPdf($this->grobPageFixture()));

        app()->instance(OpenRouterOrderAiScanProvider::class, new class implements OrderAiScanProvider {
            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                throw new class('Provider returned malformed JSON.') extends RuntimeException {
                    public function context(): array
                    {
                        return [
                            'provider' => 'openrouter',
                            'model' => 'demo-model',
                            'provider_task_id' => 'task-124',
                            'raw_response' => [
                                'id' => 'task-124',
                                'http_status' => 200,
                                'body' => '{"oops":true}',
                            ],
                        ];
                    }
                };
            }
        });

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('runExtraction');
        $method->setAccessible(true);
        $scan = $this->makeInMemoryScan([
            'id' => 322,
            'status' => 'extracting',
            'provider' => 'openrouter',
            'document_profile' => 'grob',
            'page_count' => 17,
            'billed_tokens' => 17,
            'source_file_name' => '4512109402.pdf',
            'source_mime_type' => 'application/pdf',
            'source_file_path' => $sourcePath,
        ]);

        $result = $method->invoke($service, $scan);

        $this->assertSame($scan, $result);
        $this->assertSame('failed', $scan->capturedForceFill['status']);
        $this->assertSame(5, $scan->capturedForceFill['page_count']);
        $this->assertSame(0, $scan->capturedForceFill['billed_tokens']);
        $this->assertSame(0, $scan->capturedForceFill['credits_spent']);
    }

    public function test_run_extraction_keeps_provider_response_when_downstream_processing_fails(): void
    {
        config(['ai-order-scan.provider' => 'openrouter']);

        $rawResponse = [
            'id' => 'provider-row-1',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode($this->basePayload()),
                    ],
                ],
            ],
        ];

        app()->instance(OpenRouterOrderAiScanProvider::class, new class($this->basePayload(), $rawResponse) implements OrderAiScanProvider {
            public function __construct(
                private readonly array $payload,
                private readonly array $rawResponse
            ) {
            }

            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                return [
                    'provider' => 'openrouter',
                    'model' => 'demo-model',
                    'provider_task_id' => 'task-789',
                    'credits_spent' => 0.1234,
                    'raw_response' => $this->rawResponse,
                    'normalized_payload' => $this->payload,
                ];
            }
        });

        app()->instance(OrderAiDocumentMetrics::class, new class {
            public function calculateBilledTokens(int $pageCount): int
            {
                throw new RuntimeException('Synthetic downstream failure.');
            }
        });

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('runExtraction');
        $method->setAccessible(true);
        $scan = $this->makeInMemoryScan([
            'id' => 654,
            'status' => 'extracting',
            'provider' => 'openrouter',
            'document_profile' => '',
        ]);

        $result = $method->invoke($service, $scan);

        $this->assertSame($scan, $result);
        $this->assertSame('failed', $scan->capturedForceFill['status']);
        $this->assertSame('task-789', $scan->capturedForceFill['provider_task_id']);
        $this->assertSame('provider-row-1', $scan->capturedForceFill['raw_provider_response']['id']);
        $this->assertSame(
            'Synthetic downstream failure.',
            $scan->capturedForceFill['raw_provider_response']['_failure']['message']
        );
    }

    public function test_run_extraction_retries_failed_ai_scan_only_once_automatically(): void
    {
        config(['ai-order-scan.provider' => 'openrouter']);

        $attempts = new class {
            public int $count = 0;
        };

        app()->instance(OpenRouterOrderAiScanProvider::class, new class($attempts) implements OrderAiScanProvider {
            public function __construct(
                private readonly object $attempts
            ) {
            }

            public function supportsLiveTransfer(): bool
            {
                return true;
            }

            public function scan(OrderAiScan $scan): array
            {
                $this->attempts->count++;

                throw new class('Provider timeout during extraction.') extends RuntimeException {
                    public function context(): array
                    {
                        return [
                            'provider' => 'openrouter',
                            'model' => 'demo-model',
                            'provider_task_id' => 'task-timeout',
                            'raw_response' => [
                                'id' => 'task-timeout',
                                'http_status' => 504,
                                'body' => 'Gateway Timeout',
                            ],
                        ];
                    }
                };
            }
        });

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('runExtraction');
        $method->setAccessible(true);
        $scan = $this->makeInMemoryScan([
            'id' => 777,
            'status' => 'extracting',
            'provider' => 'openrouter',
            'document_profile' => '',
        ]);

        $result = $method->invoke($service, $scan);

        $this->assertSame($scan, $result);
        $this->assertSame(2, $attempts->count);
        $this->assertSame('failed', $scan->capturedForceFill['status']);
        $this->assertSame(
            1,
            $scan->capturedForceFill['raw_provider_response']['_failure']['automatic_retry_attempts_used']
        );
    }

    public function test_can_retry_failed_scan_allows_terminal_failed_history_row_without_transfer(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);
        Storage::disk('local')->put('order-ai-scans/retryable-scan.pdf', '%PDF-1.4');

        $service = app(OrderAiScanService::class);
        $scan = $this->makeInMemoryScan([
            'status' => 'completed',
            'error_message' => 'Neuspjesan AI scan.',
            'transferred_at' => null,
            'pantheon_order_key' => null,
            'pantheon_order_view' => null,
            'pantheon_order_qid' => null,
            'source_file_name' => 'retryable-scan.pdf',
            'source_file_path' => 'order-ai-scans/retryable-scan.pdf',
        ]);

        $this->assertTrue($service->canRetryFailedScan($scan));
    }

    public function test_build_status_payload_exposes_retry_availability(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);
        Storage::disk('local')->put('order-ai-scans/retryable-status.pdf', '%PDF-1.4');
        Storage::disk('local')->put('order-ai-scans/in-progress-status.pdf', '%PDF-1.4');
        Storage::disk('local')->put('order-ai-scans/successful-status.pdf', '%PDF-1.4');

        $service = app(OrderAiScanService::class);

        $retryableScan = $this->makeInMemoryScan([
            'status' => 'completed',
            'error_message' => 'Neuspjesan AI scan.',
            'transferred_at' => null,
            'pantheon_order_key' => null,
            'pantheon_order_view' => null,
            'pantheon_order_qid' => null,
            'source_file_name' => 'retryable-status.pdf',
            'source_file_path' => 'order-ai-scans/retryable-status.pdf',
        ]);
        $inProgressScan = $this->makeInMemoryScan([
            'status' => 'extracting',
            'error_message' => null,
            'source_file_name' => 'in-progress-status.pdf',
            'source_file_path' => 'order-ai-scans/in-progress-status.pdf',
        ]);
        $successfulScan = $this->makeInMemoryScan([
            'status' => 'transferred',
            'error_message' => null,
            'transferred_at' => now(),
            'pantheon_order_key' => '26-0110-001161',
            'pantheon_order_view' => '26-0110-001161',
            'pantheon_order_qid' => 1451,
            'source_file_name' => 'successful-status.pdf',
            'source_file_path' => 'order-ai-scans/successful-status.pdf',
        ]);

        $this->assertTrue($service->buildStatusPayload($retryableScan)['retry_available']);
        $this->assertTrue($service->buildStatusPayload($inProgressScan)['retry_available']);
        $this->assertTrue($service->buildStatusPayload($successfulScan)['retry_available']);
    }

    public function test_rescan_clears_previous_extraction_payloads_before_reprocessing(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);
        Storage::disk('local')->put(
            'order-ai-scans/retry-reset.txt',
            "27. 6. 2026.\nTrendy Germany GmbH\nBestellung 26-020-000963\n"
        );

        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $columns = $reflection->getProperty('orderAiScanColumns');
        $columns->setAccessible(true);
        $columns->setValue($service, [
            'document_profile' => true,
            'page_count' => true,
            'billed_tokens' => true,
            'raw_extracted_text' => true,
            'extraction_payload' => true,
            'openrouter_payload' => true,
            'parser_payload' => true,
            'validation_warnings' => true,
            'validation_errors' => true,
            'confidence_score' => true,
            'extraction_duration_ms' => true,
            'ai_duration_ms' => true,
            'validation_duration_ms' => true,
            'extraction_method' => true,
            'transfer_started_at' => true,
        ]);

        $scan = $this->makeInMemoryScan([
            'id' => 963,
            'status' => 'completed',
            'provider' => 'openrouter',
            'model' => 'old-model',
            'source_origin' => 'manual',
            'source_file_name' => 'retry-reset.txt',
            'source_file_path' => 'order-ai-scans/retry-reset.txt',
            'source_mime_type' => 'text/plain',
            'normalized_payload' => [
                'order' => [
                    'delivery_deadline' => '27. 6. 2026.',
                ],
            ],
            'pantheon_transfer_payload' => [
                'order' => [
                    'RokIsporuke' => '27.06.2026',
                ],
            ],
            'raw_provider_response' => [
                'id' => 'old-response',
            ],
            'parser_payload' => [
                'status' => 'old-parser-result',
            ],
            'raw_extracted_text' => 'old extracted text',
            'validation_warnings' => ['old warning'],
            'validation_errors' => ['old error'],
            'confidence_score' => 0.4,
            'extraction_method' => 'old-method',
            'processed_at' => now(),
            'completed_at' => now(),
            'transferred_at' => now(),
            'transfer_started_at' => now(),
            'pantheon_order_key' => 'old-order-key',
            'pantheon_order_view' => 'old-order-view',
            'pantheon_order_qid' => 123,
            'error_message' => 'previous bad extraction',
            'credits_spent' => 2.5,
        ]);

        $retriedScan = $service->rescan($scan, null, false, false);

        $this->assertSame($scan, $retriedScan);
        $this->assertSame('uploaded', $scan->capturedForceFill['status']);
        $this->assertSame('AI skeniranje je ponovo pokrenuto.', $scan->capturedForceFill['processing_step']);
        $this->assertSame('trendy_de', $scan->capturedForceFill['document_profile']);
        $this->assertStringContainsString('Never use Datum', $scan->capturedForceFill['request_prompt']);
        $this->assertNull($scan->capturedForceFill['normalized_payload']);
        $this->assertNull($scan->capturedForceFill['pantheon_transfer_payload']);
        $this->assertNull($scan->capturedForceFill['raw_provider_response']);
        $this->assertNull($scan->capturedForceFill['raw_extracted_text']);
        $this->assertNull($scan->capturedForceFill['parser_payload']);
        $this->assertNull($scan->capturedForceFill['validation_warnings']);
        $this->assertNull($scan->capturedForceFill['validation_errors']);
        $this->assertNull($scan->capturedForceFill['confidence_score']);
        $this->assertNull($scan->capturedForceFill['extraction_method']);
        $this->assertNull($scan->capturedForceFill['processed_at']);
        $this->assertNull($scan->capturedForceFill['completed_at']);
        $this->assertNull($scan->capturedForceFill['transferred_at']);
        $this->assertNull($scan->capturedForceFill['transfer_started_at']);
        $this->assertNull($scan->capturedForceFill['pantheon_order_key']);
        $this->assertNull($scan->capturedForceFill['pantheon_order_view']);
        $this->assertNull($scan->capturedForceFill['pantheon_order_qid']);
        $this->assertNull($scan->capturedForceFill['error_message']);
        $this->assertSame(0, $scan->capturedForceFill['credits_spent']);
    }

    public function test_can_retry_failed_scan_returns_false_when_source_document_is_missing(): void
    {
        Storage::fake('local');
        config(['ai-order-scan.storage_disk' => 'local']);

        $service = app(OrderAiScanService::class);
        $scan = $this->makeInMemoryScan([
            'status' => 'failed',
            'source_file_name' => 'missing.pdf',
            'source_file_path' => 'order-ai-scans/missing.pdf',
        ]);

        $this->assertFalse($service->canRetryFailedScan($scan));
    }

    public function test_build_request_prompt_instructs_grob_scans_to_preserve_german_characters(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildRequestPrompt');
        $method->setAccessible(true);

        $prompt = (string) $method->invoke($service, 'grob');

        $this->assertStringContainsString('Preserve visible German characters exactly as written', $prompt);
        $this->assertStringContainsString('UTF-8 / Windows-1252 mojibake', $prompt);
        $this->assertStringContainsString('"StÃ¶ÃŸel" as "Stößel"', $prompt);
        $this->assertStringContainsString('Preserve umlauts and eszett in product_name', $prompt);
        $this->assertStringContainsString('Never append decimal places to a numeric-looking product_code', $prompt);
        $this->assertStringContainsString('Never invent an extra leading digit or a +1000 offset in line_total', $prompt);
        $this->assertStringContainsString('42,60 x 3,00 -> 127,80, never 1127,80', $prompt);
        $this->assertStringContainsString('Nettopreis 18,90 EUR ST 1 302,39', $prompt);
        $this->assertStringContainsString('do not keep them inside product_code', $prompt);
        $this->assertStringContainsString('Do not insert spaces around hyphens inside code-like names', $prompt);
        $this->assertStringContainsString('treat the second and third stacked rows as product_name', $prompt);
        $this->assertStringContainsString('ignore it completely', $prompt);
        $this->assertStringContainsString('extract the visible date next to Lieferdatum', $prompt);
        $this->assertStringContainsString('not a dispatch/shipping date', $prompt);
        $this->assertStringContainsString('Extract the exact value after "Ekg:" into order.requester_code', $prompt);
        $this->assertStringContainsString('requester_code "040"', $prompt);
        $this->assertStringContainsString('For GROB, return order.note and every item.note as an empty string', $prompt);
    }

    public function test_build_request_prompt_describes_trendy_liefertermin_header_and_item_fallback(): void
    {
        $service = app(OrderAiScanService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildRequestPrompt');
        $method->setAccessible(true);

        $prompt = (string) $method->invoke($service, 'trendy_de');

        $this->assertStringContainsString('Never use Datum as order.delivery_deadline or item.delivery_deadline', $prompt);
        $this->assertStringContainsString('Trendy Germany GmbH-{number}', $prompt);
        $this->assertStringContainsString('Trendy Germany GmbH-45', $prompt);
        $this->assertStringContainsString('the second standalone date row before "Trendy Germany GmbH"', $prompt);
        $this->assertStringContainsString('If there is only one standalone date before "Trendy Germany GmbH"', $prompt);
        $this->assertStringContainsString('not a dispatch/shipping date', $prompt);
        $this->assertStringContainsString('DN731973_A', $prompt);
        $this->assertStringContainsString('Crtež/Crtez rows', $prompt);
        $this->assertStringContainsString('Every visible Pos. + Artikel Nr. pair starts a separate item', $prompt);
        $this->assertStringContainsString('11 DN731973_A SIDE PLATE, RIGHT', $prompt);
        $this->assertStringContainsString('Ignore page labels such as "Page"', $prompt);
    }

    public function test_build_status_payload_refreshes_legacy_duplicate_reference_preview_and_blocks_transfer(): void
    {
        app()->instance(PantheonOrderTransferService::class, new class extends PantheonOrderTransferService {
            public function isTransferReady(array $normalizedPayload): bool
            {
                return true;
            }

            public function previewFromNormalizedPayload(array $normalizedPayload, mixed $user = null): array
            {
                throw new RuntimeException(
                    'Narudžba sa referencom "4512109382" već postoji u bazi kao 26-0110-001161.'
                );
            }
        });

        $scan = $this->makeInMemoryScan([
            'id' => 905,
            'status' => 'completed',
            'progress_current' => 100,
            'progress_total' => 100,
            'processed_at' => now(),
            'normalized_payload' => [
                'order' => [
                    'customer_name' => 'Trendy d.o.o.',
                    'supplier_name' => 'GROB-WERKE',
                    'external_document_number' => '4512109382',
                    'document_type' => '0110',
                    'currency' => 'EUR',
                    'warnings' => [],
                ],
                'items' => [],
                'summary' => [],
            ],
            'pantheon_transfer_payload' => [
                'payload' => [
                    'customer_name' => 'Trendy d.o.o.',
                    'supplier_name' => 'GROB-WERKE',
                    'external_document_number' => '4512109382',
                    'document_type' => '0110',
                    'currency' => 'EUR',
                    'items' => [],
                ],
            ],
        ]);

        $payload = app(OrderAiScanService::class)->buildStatusPayload($scan);
        $this->assertFalse($payload['transfer_ready']);
        $this->assertTrue($payload['transfer_blocked']);
        $this->assertSame('duplicate_reference', $payload['transfer_preview_error_code']);
        $this->assertSame(
            'Narudžba sa ovom referencom već postoji u bazi kao 26-0110-001161.',
            $payload['transfer_button_hint']
        );
        $this->assertSame(
            'Narudžba sa referencom "4512109382" već postoji u bazi kao 26-0110-001161.',
            $payload['transfer_block_reason']
        );
        $this->assertSame(2, $scan->capturedForceFill['pantheon_transfer_payload']['preview_version']);
        $this->assertTrue($scan->capturedForceFill['pantheon_transfer_payload']['transfer_blocked']);
        $this->assertSame(
            '26-0110-001161',
            $scan->capturedForceFill['pantheon_transfer_payload']['existing_order_view']
        );
    }

    private function basePayload(): array
    {
        return [
            'order' => [
                'customer_name' => '',
                'supplier_name' => '',
                'requester_code' => '',
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

    private function makeInMemoryScan(array $attributes = []): OrderAiScan
    {
        $scan = new class extends OrderAiScan {
            public array $capturedForceFill = [];

            public function forceFill(array $attributes)
            {
                $this->capturedForceFill = $attributes;

                foreach ($attributes as $key => $value) {
                    $this->setAttribute($key, $value);
                }

                return $this;
            }

            public function save(array $options = []): bool
            {
                return true;
            }

            public function fresh($with = [])
            {
                return $this;
            }

            public function refresh()
            {
                return $this;
            }
        };

        foreach ($attributes as $key => $value) {
            $scan->setAttribute($key, $value);
        }

        return $scan;
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
