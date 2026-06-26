<?php

namespace Tests\Unit;

use App\Services\OrderAi\PantheonOrderTransferService;
use Carbon\Carbon;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class PantheonOrderTransferServiceProfileTest extends TestCase
{
    public function test_stu_unit_alias_is_normalized_to_default_unit(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeUnitCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'STU', ['acUM' => 3]);

        $this->assertSame('KO', $result);
    }

    public function test_st_unit_alias_is_normalized_to_default_unit(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeUnitCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ST', ['acUM' => 3]);

        $this->assertSame('KO', $result);
    }

    public function test_trendy_germany_does_not_receive_primary_classification(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrimaryClassification');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ALMG3', [
            'supplier_name' => 'Trendy Germany GmbH',
        ]);

        $this->assertSame('', $result);
    }

    public function test_grob_keeps_primary_classification_detection(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrimaryClassification');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'ALMG3', [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('ALUMINIJUM', $result);
    }

    public function test_subject_lookup_candidates_include_trendy_de_aliases(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('subjectLookupCandidates');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'Trendy Germany');

        $this->assertContains('Trendy Germany', $result);
        $this->assertContains('Trendy Germany GmbH', $result);
    }

    public function test_subject_lookup_candidates_preserve_grob_aliases(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('subjectLookupCandidates');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'GROB-WERKE');

        $this->assertContains('GROB-WERKE', $result);
        $this->assertContains('GROB-WERKE GmbH & Co. KG', $result);
    }

    public function test_extract_transfer_item_metadata_ignores_zeichnung_lines_for_grob_orders(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);
        $traeger = hex2bin('5472c3a4676572');

        $result = $method->invoke($service, [
            'product_name' => $traeger . "\nGCU-040-210-01-GM5511/1-1\nZeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00",
            'drawing_reference' => 'Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00',
            'note' => "Zeichnung GCU-040-210-01-GM5511/1-1 mit Revisionsstand 00\nPrimarna klasifikacija: CELIK",
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame($traeger . ' GCU-040-210-01-GM5511/1-1', $result['product_name']);
        $this->assertSame('', $result['drawing_reference']);
        $this->assertSame('Primarna klasifikacija: CELIK', $result['note']);
    }

    public function test_extract_transfer_item_metadata_compacts_hyphen_spacing_in_grob_product_name(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'product_name' => "Platte\nGM7258/06 - 1350 - 75/1 - 2",
            'drawing_reference' => '',
            'note' => '',
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('Platte GM7258/06-1350-75/1-2', $result['product_name']);
    }

    public function test_extract_transfer_item_metadata_repairs_spaces_around_german_umlauts(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);
        $u = (string) hex2bin('c3bc');
        $o = (string) hex2bin('c3b6');
        $eszett = (string) hex2bin('c39f');

        $result = $method->invoke($service, [
            'product_name' => 'H ' . $u . " lse\nSt " . $o . ' ' . $eszett . ' el',
            'drawing_reference' => '',
            'note' => 'f ' . $u . ' r Montage',
            'material_hint' => 'br ' . $u . ' niert',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame('H' . $u . 'lse St' . $o . $eszett . 'el', $result['product_name']);
        $this->assertSame('f' . $u . 'r Montage', $result['note']);
        $this->assertSame('br' . $u . 'niert', $result['material_hint']);
    }

    public function test_extract_transfer_item_metadata_removes_grob_leading_unit_token_from_product_name(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractTransferItemMetadata');
        $method->setAccessible(true);
        $durchfuehrung = (string) hex2bin('447572636866c3bc6872756e67');

        $result = $method->invoke($service, [
            'product_name' => 'ST ' . $durchfuehrung . "\nG352-1220-206-0000-06-1",
            'drawing_reference' => '',
            'note' => '',
            'material_hint' => '',
        ], [
            'supplier_name' => 'GROB-WERKE GmbH & Co. KG',
        ]);

        $this->assertSame($durchfuehrung . ' G352-1220-206-0000-06-1', $result['product_name']);
        $this->assertStringStartsNotWith('ST ', $result['product_name']);
    }

    public function test_normalize_transfer_product_code_strips_decimal_suffix_from_numeric_code(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeTransferProductCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, '64820441.00');

        $this->assertSame('64820441', $result);
    }

    public function test_new_order_header_note_is_always_empty(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildHeaderNote');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($service));
    }

    public function test_foreign_0110_order_items_use_export_vat_profile(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveOrderItemVatProfile');
        $method->setAccessible(true);

        $result = $method->invoke($service, '0110', 'P1', 17);

        $this->assertSame('I0', $result['code']);
        $this->assertSame(0.0, $result['rate']);
    }

    public function test_domestic_0200_order_items_use_p1_and_keep_their_rate(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveOrderItemVatProfile');
        $method->setAccessible(true);

        $result = $method->invoke($service, '0200', 'I0', 17);

        $this->assertSame('P1', $result['code']);
        $this->assertSame(17.0, $result['rate']);
    }

    public function test_other_document_types_keep_their_existing_vat_profile(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveOrderItemVatProfile');
        $method->setAccessible(true);

        $result = $method->invoke($service, '0300', 'NN', 5);

        $this->assertSame('NN', $result['code']);
        $this->assertSame(5.0, $result['rate']);
    }

    public function test_dotted_german_delivery_date_is_parsed_for_pantheon(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseDateOrFallback');
        $method->setAccessible(true);

        $result = $method->invoke($service, '1. 6. 2026.', Carbon::parse('2026-01-01'));

        $this->assertSame('2026-06-01', $result->format('Y-m-d'));
    }

    public function test_order_item_delivery_date_populates_deadline_and_leaves_dispatch_empty(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildOrderItemDeliveryDatePayload');
        $method->setAccessible(true);

        $result = $method->invoke($service, Carbon::parse('2026-06-11 14:30:00'));

        $this->assertSame('2026-06-11 00:00:00', $result['adDeliveryDeadline']->format('Y-m-d H:i:s'));
        $this->assertNull($result['adDeliveryDate']);

        $trimMethod = $reflection->getMethod('trimPayloadToInsertableColumns');
        $trimMethod->setAccessible(true);
        $insertPayload = $trimMethod->invoke(
            $service,
            $result,
            ['adDeliveryDeadline', 'adDeliveryDate']
        );

        $this->assertArrayHasKey('adDeliveryDeadline', $insertPayload);
        $this->assertArrayNotHasKey('adDeliveryDate', $insertPayload);
    }

    public function test_duplicate_external_document_reference_is_blocked_before_transfer(): void
    {
        $service = new class extends PantheonOrderTransferService {
            public function assertUniqueReferenceForTest(array $prepared): void
            {
                $this->assertUniqueExternalDocumentReference($prepared);
            }

            protected function findExistingOrderByExternalDocumentReference(string $reference): ?array
            {
                return [
                    'key' => '260110001161',
                    'view' => '26-0110-001161',
                    'reference' => $reference,
                ];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Narudžba sa referencom "4512109382" već postoji u bazi kao 26-0110-001161.');

        $service->assertUniqueReferenceForTest([
            'external_document_number' => '4512109382',
        ]);
    }
}
