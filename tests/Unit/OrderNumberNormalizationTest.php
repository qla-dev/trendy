<?php

namespace Tests\Unit;

use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\WorkOrderOrderItemLinkController;
use App\Http\Controllers\WorkOrderController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OrderNumberNormalizationTest extends TestCase
{
    public function test_order_locator_keeps_thirteen_digit_order_number(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('normalizeOrderLocatorNumber');
        $method->setAccessible(true);

        $this->assertSame('2201100000004', $method->invoke($controller, '22-0110-0000004'));
    }

    public function test_order_search_variants_cover_thirteen_and_legacy_twelve_digits(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('orderNumberDigitSearchVariants');
        $method->setAccessible(true);

        $fromThirteenDigits = $method->invoke($controller, '22-0110-0000004');
        $fromTwelveDigits = $method->invoke($controller, '22-0110-000004');

        $this->assertContains('2201100000004', $fromThirteenDigits);
        $this->assertContains('220110000004', $fromThirteenDigits);
        $this->assertContains('220110000004', $fromTwelveDigits);
        $this->assertContains('2201100000004', $fromTwelveDigits);
    }

    public function test_order_display_formats_only_thirteen_digit_database_number(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('formatOrderNumberForDisplay');
        $method->setAccessible(true);

        $this->assertSame('22-0110-0000004', $method->invoke($controller, '2201100000004'));
        $this->assertSame('220110000004', $method->invoke($controller, '220110000004'));
        $this->assertSame('22-0110-000004', $method->invoke($controller, '22-0110-000004'));
    }

    public function test_order_display_prefers_database_key_over_twelve_digit_view(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveOrderDisplayNumber');
        $method->setAccessible(true);

        $row = [
            'acKey' => '2201100000004',
            'acKeyView' => '22-0110-000004',
        ];

        $this->assertSame('22-0110-0000004', $method->invoke($controller, $row, '220110000004'));
        $this->assertSame('22-0110-0000004', $method->invoke($controller, $row, '2201100000004'));
    }

    public function test_order_item_display_formats_only_thirteen_digit_database_number(): void
    {
        $controller = new OrderItemController();
        $method = (new ReflectionClass($controller))->getMethod('formatDisplayOrderNumber');
        $method->setAccessible(true);

        $this->assertSame('22-0110-0000004', $method->invoke($controller, '2201100000004'));
        $this->assertSame('220110000004', $method->invoke($controller, '220110000004'));
        $this->assertSame('22-0110-000004', $method->invoke($controller, '22-0110-000004'));
    }

    public function test_work_order_display_formats_only_thirteen_digit_database_number(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('formatWorkOrderNumberForCalendar');
        $method->setAccessible(true);

        $this->assertSame('26-6000-0002370', $method->invoke($controller, '2660000002370'));
        $this->assertSame('26-6000-002370', $method->invoke($controller, '26-6000-002370'));
    }

    public function test_work_order_search_accepts_legacy_twelve_digit_number(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('searchVariants');
        $method->setAccessible(true);

        $variants = $method->invoke($controller, '26-6000-002370');

        $this->assertContains('2660000002370', $variants);
        $this->assertContains('26-6000-0002370', $variants);
    }

    public function test_work_order_row_display_prefers_database_key_over_twelve_digit_view(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('mapRow');
        $method->setAccessible(true);

        $mapped = $method->invoke($controller, [
            'acKey' => '2660000002370',
            'acRefNo1' => '26-6000-002370',
            'acKeyView' => '26-6000-002370',
            'acStatusMF' => 'N',
        ]);

        $this->assertSame('26-6000-0002370', $mapped['broj_naloga']);
    }

    public function test_work_order_note_uses_database_order_number_format(): void
    {
        $controller = new WorkOrderController();
        $method = (new ReflectionClass($controller))->getMethod('resolveWorkOrderNote');
        $method->setAccessible(true);

        $note = 'Kreirano iz preko eNalog.app preko QR skena narudžbe 26-0110-000990 / poz 3 / šifra 626102019';
        $row = [
            'acNote' => $note,
            'acLnkKey' => '2601100000990',
        ];

        $this->assertStringContainsString(
            'Kreirano iz eNalog.app preko QR skena narudžbe 26-0110-0000990 / poz 3',
            $method->invoke($controller, $row)
        );
    }

    public function test_order_modals_use_thirteen_digit_work_order_document_number(): void
    {
        $itemController = new OrderItemController();
        $documentMethod = (new ReflectionClass($itemController))->getMethod('transferStatusDocument');
        $documentMethod->setAccessible(true);

        $this->assertSame(
            '26-6000-0002370',
            $documentMethod->invoke($itemController, ['acKeyView' => '26-6000-002370'], ['acKey' => '2660000002370'])
        );

        $linkController = new WorkOrderOrderItemLinkController();
        $mapMethod = (new ReflectionClass($linkController))->getMethod('mapLinkRow');
        $mapMethod->setAccessible(true);
        $mapped = $mapMethod->invoke(
            $linkController,
            ['acKeyView' => '26-6000-002370', 'anLnkNo' => 1],
            [],
            ['acKey' => '2660000002370', 'acStatus' => 'N']
        );

        $this->assertSame('26-6000-0002370', $mapped['dokument']);
    }
}
