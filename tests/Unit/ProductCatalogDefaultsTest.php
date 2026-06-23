<?php

namespace Tests\Unit;

use App\Models\Product;
use ReflectionClass;
use Tests\TestCase;

class ProductCatalogDefaultsTest extends TestCase
{
    public function test_build_catalog_preferred_values_applies_expected_enalog_defaults(): void
    {
        $reflection = new ReflectionClass(Product::class);
        $method = $reflection->getMethod('buildCatalogPreferredValues');
        $method->setAccessible(true);

        $values = $method->invoke(
            null,
            [],
            '12312312',
            'Test Product',
            'KO',
            '120',
            'CELIK',
            9001,
            '2026-06-23 10:00:00',
            57
        );

        $this->assertSame('P1', $values['acVATCode']);
        $this->assertSame('P1', $values['acVATCodeLow']);
        $this->assertSame('P1', $values['acVATCodeReceive']);
        $this->assertSame('KM', $values['acCurrency']);
        $this->assertSame('KM', $values['acPurchCurr']);
        $this->assertSame('6000', $values['acDocTypeProd']);
        $this->assertSame('KG', $values['acUMDim2']);
        $this->assertSame(17.0, $values['anVAT']);
        $this->assertSame(17.0, $values['anVATReceive']);
        $this->assertSame(7.0, $values['anDeliveryDeadline']);
        $this->assertSame(-1.0, $values['anAllowedInvShort']);
        $this->assertSame(0.0, $values['anPrStOptimalQty']);
        $this->assertSame(0.0, $values['anPrStDailyQty']);
        $this->assertSame(57, $values['anUserIns']);
        $this->assertSame(57, $values['anUserChg']);
    }

    public function test_build_catalog_preferred_values_respects_explicit_attribute_overrides(): void
    {
        $reflection = new ReflectionClass(Product::class);
        $method = $reflection->getMethod('buildCatalogPreferredValues');
        $method->setAccessible(true);

        $values = $method->invoke(
            null,
            [
                'acVATCode' => 'P2',
                'acCurrency' => 'EUR',
                'acPurchCurr' => 'EUR',
                'acDocTypeProd' => '6001',
                'acUMDim2' => 'KG',
                'anVAT' => 20,
                'anDeliveryDeadline' => 14,
                'anAllowedInvShort' => -2,
                'anPrStOptimalQty' => 3,
                'anPrStDailyQty' => 4,
            ],
            '12312312',
            'Test Product',
            'KO',
            '120',
            'CELIK',
            9001,
            '2026-06-23 10:00:00',
            57
        );

        $this->assertSame('P2', $values['acVATCode']);
        $this->assertSame('P2', $values['acVATCodeLow']);
        $this->assertSame('P2', $values['acVATCodeReceive']);
        $this->assertSame('EUR', $values['acCurrency']);
        $this->assertSame('EUR', $values['acPurchCurr']);
        $this->assertSame('6001', $values['acDocTypeProd']);
        $this->assertSame('KG', $values['acUMDim2']);
        $this->assertSame(20.0, $values['anVAT']);
        $this->assertSame(20.0, $values['anVATReceive']);
        $this->assertSame(14.0, $values['anDeliveryDeadline']);
        $this->assertSame(-2.0, $values['anAllowedInvShort']);
        $this->assertSame(3.0, $values['anPrStOptimalQty']);
        $this->assertSame(4.0, $values['anPrStDailyQty']);
    }

    public function test_build_audit_user_lookup_candidates_normalizes_username_name_and_email(): void
    {
        $reflection = new ReflectionClass(Product::class);
        $method = $reflection->getMethod('buildAuditUserLookupCandidates');
        $method->setAccessible(true);

        $candidates = $method->invoke(null, [
            'username' => 'TREN_KRA',
            'name' => 'Alma Krnjić',
            'email' => 'alma.krnjic@trendy-doo.com',
        ]);

        $this->assertContains('trenkra', $candidates);
        $this->assertContains('almakrnjic', $candidates);
        $this->assertContains('almakrnjictrendydoocom', $candidates);
    }
}
