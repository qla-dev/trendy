<?php

namespace Tests\Unit;

use App\Services\OrderAi\PantheonOrderTransferService;
use ReflectionClass;
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
}
