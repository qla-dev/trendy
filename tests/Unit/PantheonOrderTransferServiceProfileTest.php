<?php

namespace Tests\Unit;

use App\Services\OrderAi\PantheonOrderTransferService;
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

    public function test_normalize_transfer_product_code_strips_decimal_suffix_from_numeric_code(): void
    {
        $service = new PantheonOrderTransferService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeTransferProductCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, '64820441.00');

        $this->assertSame('64820441', $result);
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
