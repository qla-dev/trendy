<?php

namespace Tests\Unit;

use App\Services\WorkOrder\ExcessStockService;
use App\Services\WorkOrder\WorkOrderClosingService;
use Illuminate\Database\ConnectionInterface;
use ReflectionClass;
use Tests\TestCase;

class ExcessStockRetiredTest extends TestCase
{
    public function test_retirement_cannot_be_reenabled_by_old_environment_flags(): void
    {
        $this->assertFalse(config('excess-stock.enabled'));
        $this->assertFalse(config('excess-stock.assign_on_work_order_create'));
        $this->assertFalse(config('excess-stock.allow_apply'));
    }

    public function test_disabled_assignment_does_not_touch_the_database(): void
    {
        config(['excess-stock.enabled' => true]);
        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->never())->method('table');
        $db->expects($this->never())->method('transaction');
        $this->assertSame(0, (new ExcessStockService())->assign($db, [], 1));
    }

    public function test_disabled_recovery_cannot_create_documents_or_issue_stock(): void
    {
        $service = (new ReflectionClass(WorkOrderClosingService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(WorkOrderClosingService::class, 'excessStock'))->setValue($service, new ExcessStockService());
        config(['excess-stock.enabled' => true]);
        $this->assertNull($service->recoverMissingExcessStock6400('unused', 1));
    }

    public function test_historical_markers_remain_recognized(): void
    {
        $service = new ExcessStockService();
        $this->assertTrue($service->isExcessItem(['acNote' => 'Automatski popunjeno sa skladište dodatnih sirovina']));
        $this->assertTrue($service->isExcessItem(['acNote' => 'REZERVACIJA_DODATNIH_SIROVINA|nacin=automatski']));
        $this->assertFalse($service->isExcessItem(['acNote' => 'Automatski preuzeta sastavnica']));
    }
}
